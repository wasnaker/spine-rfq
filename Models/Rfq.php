<?php

declare(strict_types=1);

namespace Modules\Rfq\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spine\Traits\HasLifecycleHooks;

class Rfq extends Model
{
    use HasLifecycleHooks;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'rfqs';

    protected $fillable = [
        'number', 'prefix', 'formatted_number', 'hash',
        'date', 'expirydate',
        'customer_id', 'surveyor_id', 'created_by', 'requestor_id',
        'status',
        'terms', 'clientnote', 'adminnote', 'reference_no', 'currency',
        'pipeline_order', 'is_expiry_notified',
        'acceptance_firstname', 'acceptance_lastname', 'acceptance_email',
        'acceptance_date', 'acceptance_ip', 'signature', 'short_link',
    ];

    protected $casts = [
        'date'               => 'date',
        'expirydate'         => 'date',
        'is_expiry_notified' => 'boolean',
        'acceptance_date'    => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Workflow transisi (code-driven).
     * dari => [ke => actor]. Actor fase 1 dicatat sebagai dokumentasi;
     * guard actor per entity (customer/surveyor) menyusul saat frontend.
     * ponytail: actor tidak di-enforce di endpoint transition (hanya
     * permission rfq:mark_as) — tambah guard ActorResolver saat UI entity aktif.
     */
    public const TRANSITIONS = [
        self::STATUS_DRAFT   => [self::STATUS_SENT => 'customer'],                          // send
        self::STATUS_SENT    => [
            self::STATUS_DRAFT    => 'customer',   // retract
            self::STATUS_ACCEPTED => 'surveyor',   // accept
            self::STATUS_DECLINED => 'surveyor',   // decline
            self::STATUS_EXPIRED  => 'surveyor',   // expire (juga auto-cron expirydate < hari ini)
        ],
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\Modules\Customer\Models\Customer::class, 'customer_id');
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(\Modules\Surveyor\Models\Surveyor::class, 'surveyor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class, 'rfq_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(RfqEquipment::class, 'rfq_id');
    }

    public static function labels(): array
    {
        return [
            'number'          => 'Nomor',
            'formatted_number'=> 'Nomor RFQ',
            'date'            => 'Tanggal',
            'expirydate'      => 'Berlaku Hingga',
            'customer_id'     => 'Customer',
            'surveyor_id'     => 'Surveyor',
            'status'          => 'Status',
            'terms'           => 'Ketentuan',
            'clientnote'      => 'Catatan Customer',
            'adminnote'       => 'Catatan Admin',
            'reference_no'    => 'No. Referensi',
        ];
    }
}
