<?php

declare(strict_types=1);

namespace Modules\Rfq\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqEquipment extends Model
{
    protected $table = 'rfq_equipment';

    protected $fillable = ['rfq_id', 'customer_equipment_id', 'item_id'];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'rfq_id');
    }

    public function customerEquipment(): BelongsTo
    {
        return $this->belongsTo(\Modules\Equipment\Models\CustomerEquipment::class, 'customer_equipment_id');
    }
}
