<?php

declare(strict_types=1);

namespace Modules\Rfq\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Connection\Services\ActorResolver;
use Modules\Rfq\Models\Rfq;
use Spine\Services\ActivityLogService;
use Spine\Services\SettingService;
use Spine\Support\EntityCode;

/**
 * CRUD dokumen RFQ + transisi status (workflow code-driven, lihat Rfq::TRANSITIONS).
 *
 * Scoping: user dengan entity customer (customers.admin_id via ActorResolver)
 * HANYA melihat/membuat RFQ milik customer-nya sendiri; entity surveyor hanya
 * RFQ yang di-assign ke dirinya. Non-entity (platform) melihat semua.
 */
class RfqController extends Controller
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SettingService $settings,
        private readonly ActorResolver $actors,
    ) {
    }

    private function isFullAccess(Request $request): bool
    {
        return ! in_array($this->actors->resolve($request->user())['type'] ?? null, ['customer', 'surveyor'], true);
    }

    private function scopeToActor(Request $request, $query): void
    {
        if ($this->isFullAccess($request)) {
            return;
        }

        $actor = $this->actors->resolve($request->user());

        if ($actor['type'] === 'surveyor') {
            $query->where('surveyor_id', $actor['entity']->id);
        } else {
            $query->where('customer_id', $actor['entity']->id);
        }
    }

    private function allowAccessTo(Request $request, Rfq $rfq): bool
    {
        if ($this->isFullAccess($request)) {
            return true;
        }

        $actor = $this->actors->resolve($request->user());

        return $actor['type'] === 'surveyor'
            ? $rfq->surveyor_id === $actor['entity']->id
            : $rfq->customer_id === $actor['entity']->id;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Rfq::with(['customer:id,code,name,type', 'surveyor:id,code,name,type', 'createdBy:id,name']);

        $this->scopeToActor($request, $query);

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

    /**
     * Opsi surveyor utk form create/edit RFQ: surveyor dari connections
     * (status active) yang berelasi dengan customer.
     */
    public function surveyorOptions(Request $request): JsonResponse
    {
        if ($this->isFullAccess($request)) {
            $customerId = $request->integer('customer_id');
            if (! $customerId) {
                return response()->json(['message' => 'The customer id field is required.'], 422);
            }
        } else {
            $actor = $this->actors->resolve($request->user());
            $customerId = $actor['entity']->id;
        }

        $rows = \Illuminate\Support\Facades\DB::table('connections')
            ->join('surveyors', 'surveyors.id', '=', 'connections.surveyor_id')
            ->where('connections.customer_id', $customerId)
            ->where('connections.status', 'active')
            ->whereNull('connections.deleted_at')
            ->select('surveyors.id', 'surveyors.code', 'surveyors.name', 'surveyors.type')
            ->orderBy('surveyors.name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'            => ['required', 'date'],
            'expirydate'      => ['nullable', 'date', 'after_or_equal:date'],
            'customer_id'     => ['nullable', 'integer', 'exists:customers,id'],
            'surveyor_id'     => ['required', 'integer', 'exists:surveyors,id'],
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
            'equipment'       => ['nullable', 'array'],
            'equipment.*.customer_equipment_id' => ['required', 'integer', 'exists:customer_equipments,id'],
            'equipment.*.item_id'                 => ['nullable', 'integer', 'exists:equipments,id'],
        ]);

        $rfq = DB::transaction(function () use ($validated, $request) {
            // Customer entity: RFQ selalu untuk customer-nya sendiri (admin HO/branch).
            if (! $this->isFullAccess($request)) {
                $actor = $this->actors->resolve($request->user());
                $validated['customer_id'] = $actor['entity']->id;
            } elseif (empty($validated['customer_id'])) {
                abort(422, 'The customer id field is required.');
            }

            $this->assertSurveyorConnected((int) $validated['customer_id'], (int) $validated['surveyor_id']);

            // Nomor RFQ = prefix + EntityCode::encode(id, len) (pola customer;
            // tanpa reset tahunan — id auto-increment mulai rfq_start_number,
            // di-set via migration (000003/000004).
            $prefix = (string) ($this->settings->get('rfq_prefix', 'RFQ-'));
            $codeLength = max(1, (int) ($this->settings->get('rfq_code_length', 5)));

            $rfq = Rfq::create([
                ...$validated,
                'number'           => 0,
                'prefix'           => $prefix,
                'hash'             => Str::random(40),
                'created_by'       => $request->user()->id,
                'status'           => $validated['status'] ?? Rfq::STATUS_DRAFT,
            ]);

            $rfq->forceFill([
                'number'           => $rfq->id,
                'formatted_number' => $prefix . EntityCode::encode($rfq->id, $codeLength),
            ])->saveQuietly();

            // Customer pilih customer-equipment miliknya -> otomatis jadi line item
            // (hanya daftar item — tanpa rate/qty).
            $items = $validated['items'] ?? [];
            if (! $items && ! empty($validated['equipment'])) {
                $ces = \Modules\Equipment\Models\CustomerEquipment::with('equipment:id')
                    ->whereIn('id', collect($validated['equipment'])->pluck('customer_equipment_id'))
                    ->get()
                    ->keyBy('id');

                foreach ($validated['equipment'] as $eq) {
                    $ce = $ces->get($eq['customer_equipment_id']);
                    if (! $ce) {
                        continue;
                    }
                    $items[] = [
                        'item_id'     => $eq['item_id'] ?? $ce->equipment_id,
                        'description' => $ce->unit_name,
                    ];
                }
            }

            $this->syncItems($rfq, $items);
            $this->syncEquipment($rfq, $validated['equipment'] ?? []);

            return $rfq;
        });

        return response()->json($rfq->load(['items', 'equipment']), 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::with(['customer:id,code,name,type', 'surveyor:id,code,name,type', 'createdBy:id,name', 'items', 'equipment.customerEquipment:id,unit_code,unit_name'])->find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        return response()->json($rfq);
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $validated = $request->validate([
            'date'            => ['sometimes', 'date'],
            'expirydate'      => ['nullable', 'date', 'after_or_equal:date'],
            'customer_id'     => ['sometimes', 'integer', 'exists:customers,id'],
            'surveyor_id'     => ['sometimes', 'integer', 'exists:surveyors,id'],
            'requestor_id'    => ['nullable', 'integer', 'exists:users,id'],
            'terms'           => ['nullable', 'string'],
            'clientnote'      => ['nullable', 'string'],
            'adminnote'       => ['nullable', 'string'],
            'reference_no'    => ['nullable', 'string', 'max:100'],
            'items'           => ['nullable', 'array'],
            'items.*.description'     => ['required', 'string'],
            'items.*.long_description'=> ['nullable', 'string'],
            'items.*.item_id'         => ['nullable', 'integer', 'exists:equipments,id'],
            'equipment'       => ['nullable', 'array'],
            'equipment.*.customer_equipment_id' => ['required', 'integer', 'exists:customer_equipments,id'],
            'equipment.*.item_id'                 => ['nullable', 'integer', 'exists:equipments,id'],
        ]);

        DB::transaction(function () use ($rfq, $validated, $request) {
            // Tangkap items/equipment SEBELUM unset — sync pakai nilai asli, bukan $validated yang sudah dibuang.
            $items = $validated['items'] ?? null;
            $equipment = $validated['equipment'] ?? null;
            unset($validated['items'], $validated['equipment']);

            if (isset($validated['surveyor_id'])) {
                $customerId = $this->isFullAccess($request) ? $rfq->customer_id : $this->actors->resolve($request->user())['entity']->id;
                $this->assertSurveyorConnected((int) $customerId, (int) $validated['surveyor_id']);
            }

            $rfq->update($validated);

            if ($items !== null) {
                $this->syncItems($rfq, $items);
            }
            if ($equipment !== null) {
                $this->syncEquipment($rfq, $equipment);
            }
        });

        return response()->json($rfq->load(['items', 'equipment']));
    }

    /**
     * Transisi status workflow. Map: Rfq::TRANSITIONS (draft->sent; sent->draft/accepted/declined/expired).
     */
    public function transition(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
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

    public function destroy(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $rfq->delete();

        return response()->json(['message' => 'Rfq deleted']);
    }

    /**
     * Daftar equipment (customer_equipment) yang dipilih di RFQ ini.
     * Flat select — TabContent generik tidak render relasi nested.
     */
    public function equipment(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
            return response()->json(['message' => 'Rfq not found'], 404);
        }

        $rows = \Modules\Rfq\Models\RfqEquipment::query()
            ->join('customer_equipments', 'customer_equipments.id', '=', 'rfq_equipment.customer_equipment_id')
            ->leftJoin('equipments', 'equipments.id', '=', 'rfq_equipment.item_id')
            ->where('rfq_equipment.rfq_id', $id)
            ->select(
                'rfq_equipment.id',
                'customer_equipments.unit_code',
                'customer_equipments.unit_name',
                'customer_equipments.serial_no',
                'customer_equipments.location',
                'equipments.name as katalog'
            )
            ->orderBy('customer_equipments.unit_name')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function activityLogs(int $id, Request $request): JsonResponse
    {
        $rfq = Rfq::find($id);

        if (! $rfq || ! $this->allowAccessTo($request, $rfq)) {
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

    private function assertSurveyorConnected(int $customerId, int $surveyorId): void
    {
        $connected = \Illuminate\Support\Facades\DB::table('connections')
            ->where('customer_id', $customerId)
            ->where('surveyor_id', $surveyorId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->exists();

        if (! $connected) {
            abort(422, 'Surveyor tidak terhubung dengan customer ini.');
        }
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
}
