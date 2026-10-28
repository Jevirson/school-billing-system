<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectWithdrawalRefund extends Model
{
    protected $fillable = [
        'subject_withdrawal_request_id',
        'bill_id',
        'amount',
        'status',
        'recorded_by',
        'paid_at',
        'method',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(SubjectWithdrawalRequest::class, 'subject_withdrawal_request_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
