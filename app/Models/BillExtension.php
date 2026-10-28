<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillExtension extends Model
{
    protected $fillable = [
        'promissory_letter_id',
        'source_bill_id',
        'destination_bill_id',
        'next_school_term_id',
        'next_billing_period',
        'amount_at_approval',
        'approved_until',
    ];

    protected function casts(): array
    {
        return [
            'amount_at_approval' => 'decimal:2',
            'approved_until' => 'date',
        ];
    }

    public function promissoryLetter(): BelongsTo
    {
        return $this->belongsTo(PromissoryLetter::class);
    }

    public function sourceBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'source_bill_id');
    }

    public function destinationBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'destination_bill_id');
    }

    public function nextSchoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class);
    }

    public function getRemainingBalanceAttribute(): string
    {
        if (! $this->destination_bill_id) {
            if ($this->next_billing_period === null) {
                $source = $this->sourceBill;
                $sourceBalanceCents = max(
                    0,
                    Money::toCents($source->net_amount_due) - Money::toCents($source->total_paid)
                );

                return Money::fromCents(min(
                    Money::toCents($this->amount_at_approval),
                    $sourceBalanceCents
                ));
            }

            return $this->amount_at_approval;
        }

        $destination = $this->destinationBill;
        $extensions = $destination->relationLoaded('extensionsReceived')
            ? $destination->extensionsReceived
            : $destination->extensionsReceived()->orderBy('id')->get();
        $paidBeyondNativeCents = max(
            0,
            Money::toCents($destination->total_paid)
                - Money::toCents($destination->native_due_amount)
        );
        $priorApprovedCents = $extensions
            ->where('id', '<', $this->id)
            ->sum(fn (self $extension): int => Money::toCents($extension->amount_at_approval));
        $amountCents = Money::toCents($this->amount_at_approval);
        $paidCents = min($amountCents, max(0, $paidBeyondNativeCents - $priorApprovedCents));

        return Money::fromCents($amountCents - $paidCents);
    }
}
