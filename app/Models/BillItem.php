<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $fillable = ['bill_id', 'fee_type_id', 'description', 'quantity', 'unit_amount', 'line_total'];

    protected function casts(): array
    {
        return [
            'unit_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }
}
