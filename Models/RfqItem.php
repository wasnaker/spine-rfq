<?php

declare(strict_types=1);

namespace Modules\Rfq\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqItem extends Model
{
    protected $table = 'rfq_items';

    protected $fillable = [
        'rfq_id', 'item_id', 'description', 'long_description',
        'qty', 'rate', 'unit', 'tax', 'item_order',
    ];

    protected $casts = [
        'qty'  => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class, 'rfq_id');
    }
}
