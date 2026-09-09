<?php

declare(strict_types=1);

namespace Modules\Rfq\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Rfq\Models\Rfq;
use Spine\Services\ActivityLogService;
use Spine\Services\SettingService;

/**
 * CRUD dokumen RFQ + transisi status (workflow code-driven, lihat Rfq::TRANSITIONS).
 */
class RfqController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SettingService $settings,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Rfq::with(['customer:id,code,name,type', 'surveyor:id,code,name,type', 'createdBy:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('surveyor_id')) {
            $query->where('surveyor_id', $request->integer('surveyor_id'));
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($q) use ($term) {
                $q->where('formatted_number', 'like', "%{$term}%")
                  ->orWhere('reference_no', 'like', "%{$term}%");
            });
        }

        return response()->json(['data' => $query->orderByDesc('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'            => ['required', 'date'],
            'expirydate'      => ['nullable', 'date', 'after_or_equal:date'],
            'customer_id'     => ['required', 'integer', 'exists:customers,id'],
            'surveyor_id'     => ['nullable', 'integer', 'exists:surveyors,id'],
            'requestor_id'    => ['nullable', 'integer', 'exists:users,id'],
            'status'          => ['sometimes', 'string', 'in:draft,sent,accepted,declined,expired'],
            'terms'           => ['nullable', 'string'],
            'clientnote'      => ['nullable', 'string'],
            'adminnote'       => ['nullable', 'string'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'items'           => ['nullable', 'array'],
            'items.*.description'     => ['required', 'string'],
            'items.*.long_description'=> ['nullable', 'string'],
            'items.*.item_id'         => ['nullable', 'integer', 'exists:equipments,id'],
            'items.*.qty'             => ['required', 'numeric', 'min:0'],
            'items.*.rate'            => ['required', 'numeric', 'min:0'],
            'items.*.unit'            => ['nullable', 'string', 'max:30'],
            'items.*.tax'             => ['nullable', 'string', 'max:100'],
            'equipment'       => ['nullable', 'array'],
            'equipment.*.customer_equipment_id' => ['required', 'integer', 'exists:customer_equipments,id'],
            'equipment.*.item_id'                 => ['nullable', 'integer', 'exists:equipments,id'],
        ]);

        $rfq = DB::transaction(function () use ($validated, $request) {
            $number = (int) Rfq::withTrashed()->max('number') + 1;
            $prefix = (string) ($this->settings->get('rfq_prefix', 'RFQ-'));
            $length = max(1, (int) ($this->settings->get('rfq_number_length', 5)));

            $rfq = Rfq::create([
                ...$validated,
                'number'           => $number,
                'prefix'           => $prefix,
                'formatted_number' => $prefix . str_pad((string) $number, $length, '0', STR_PAD_LEFT),
                'hash'             => Str::random(40),
                'created_by'       => $request->user()->id,
                'status'           => $validated['status'] ?? Rfq::STATUS_DRAFT,
            ]);

            $this->syncItems($rfq, $validated['items'] ?? []);
            $this->syncEquipment($rfq, $validated['equipment'] ?? []);

            return $rfq;
        });

        return response()->json($rfq->load(['items', 'equipment']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $rfq = Rfq::with(['customer:id,code,name,type', 'surveyor:id,code,name,type', 'createdBy:id,name', 'items', 'equipment.customerEquipment:id,unit_code,unit_name'])->find($id);

        if (! $rfq) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        return response()->json($rfq);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $validated = $request->validate([
            'date'            => ['sometimes', 'date'],
            'expirydate'      => ['nullable', 'date', 'after_or_equal:date'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'surveyor_id'     => ['nullable', 'integer', 'exists:surveyors,id'],
            'requestor_id'    => ['nullable', 'integer', 'exists:users,id'],
            'terms'           => ['nullable', 'string'],
            'clientnote'      => ['nullable', 'string'],
            'adminnote'       => ['nullable', 'string'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'items'           => ['nullable', 'array'],
            'items.*.description'     => ['required', 'string'],
            'items.*.long_description'=> ['nullable', 'string'],
            'items.*.item_id'         => ['nullable', 'integer', 'exists:equipments,id'],
            'items.*.qty'             => ['required', 'numeric', 'min:0'],
            'items.*.rate'            => ['required', 'numeric', 'min:0'],
            'items.*.unit'            => ['nullable', 'string', 'max:30'],
            'items.*.tax'             => ['nullable', 'string', 'max:100'],
            'equipment'       => ['nullable', 'array'],
            'equipment.*.customer_equipment_id' => ['required', 'integer', 'exists:customer_equipments,id'],
            'equipment.*.item_id'                 => ['nullable', 'integer', 'exists:equipments,id'],
        ]);

        DB::transaction(function () use ($rfq, $validated, $request) {
            unset($validated['items'], $validated['equipment']);
            $rfq->update($validated);

            if ($request->has('items')) {
                $this->syncItems($rfq, $validated['items'] ?? []);
            }
            if ($request->has('equipment')) {
                $this->syncEquipment($rfq, $validated['equipment'] ?? []);
            }

            $this->recalculate($rfq);
        });

        return response()->json($rfq->load(['items', 'equipment']));
    }

    /**
     * Transisi status workflow. Map: Rfq::TRANSITIONS (draft->sent; sent->draft/accepted/declined/expired).
     */
    public function transition(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,sent,accepted,declined,expired'],
        ]);

        $allowed = Rfq::TRANSITIONS[$rfq->status] ?? [];

        if (! isset($allowed[$validated['status']])) {
            return response()->json([
                'message' => "Transisi {$rfq->status} -> {$validated['status']} tidak diizinkan",
            ], 422);
        }

        $rfq->update(['status' => $validated['status']]);

        return response()->json($rfq);
    }

    public function destroy(int $id): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $rfq->delete();

        return response()->json(['message' => 'Rfq deleted']);
    }

    public function activityLogs(int $id): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $logs = $this->activityLog
            ->query()
            ->where('subject_type', Rfq::class)
            ->where('subject_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($log) => [
                'id'          => $log->id,
                'description' => $log->description,
                'causer'      => $log->causer?->name ?? 'System',
                'properties'  => $log->properties,
                'at'          => $log->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    private function syncItems(Rfq $rfq, array $items): void
    {
        $rfq->items()->delete();

        foreach (array_values($items) as $i => $item) {
            $rfq->items()->create([...$item, 'item_order' => $i]);
        }
    }

    private function syncEquipment(Rfq $rfq, array $equipment): void
    {
        $rfq->equipment()->delete();

        foreach ($equipment as $eq) {
            $rfq->equipment()->create($eq);
        }
    }

    /**
     * Hitung ulang subtotal/total dari line items (pola legacy: tax string "NAMA|rate").
     */
    private function recalculate(Rfq $rfq): void
    {
        $subtotal = 0.0;
        $totalTax = 0.0;

        foreach ($rfq->items as $item) {
            $line = (float) $item->qty * (float) $item->rate;
            $subtotal += $line;

            if ($item->tax) {
                $rate = (float) (Str::of($item->tax)->afterLast('|')->toString() ?: 0);
                $totalTax += $line * ($rate / 100);
            }
        }

        $rfq->forceFill([
            'subtotal'  => $subtotal,
            'total_tax' => $totalTax,
            'total'     => $subtotal + $totalTax,
        ])->saveQuietly();
    }
}
