<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bill extends Model
{
    protected $fillable = [
        'invoice_number',
        'student_profile_id',
        'school_term_id',
        'billing_schedule_id',
        'billing_period',
        'scholarship_id',
        'scholarship_name',
        'total_fees',
        'scholarship_discount',
        'net_amount_due',
        'billing_date',
        'due_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_fees' => 'decimal:2',
            'scholarship_discount' => 'decimal:2',
            'net_amount_due' => 'decimal:2',
            'billing_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function schoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class);
    }

    public function billingSchedule(): BelongsTo
    {
        return $this->belongsTo(BillingSchedule::class);
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(BillExtension::class, 'source_bill_id');
    }

    public function extensionsReceived(): HasMany
    {
        return $this->hasMany(BillExtension::class, 'destination_bill_id');
    }

    public function promissoryLetters(): HasMany
    {
        return $this->hasMany(PromissoryLetter::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->ofMany([
            'paid_at' => 'max',
            'id' => 'max',
        ]);
    }

    public function getTotalPaidAttribute(): string
    {
        if ($this->relationLoaded('payments')) {
            $totalCents = $this->payments->sum(fn (Payment $payment): int => Money::toCents($payment->amount));
        } else {
            $totalCents = Money::toCents($this->payments()->sum('amount'));
        }

        return Money::fromCents($totalCents);
    }

    public function getRemainingBalanceAttribute(): string
    {
        $remainingCents = $this->totalDueCents() - Money::toCents($this->total_paid);

        return Money::fromCents(max(0, $remainingCents));
    }

    public function getTotalDueAttribute(): string
    {
        return Money::fromCents($this->totalDueCents());
    }

    public function getNativeDueAmountAttribute(): string
    {
        $extensions = $this->relationLoaded('extensions') ? $this->extensions : $this->extensions()->get();
        $deferredCents = $extensions->sum(fn (BillExtension $extension): int => Money::toCents($extension->amount_at_approval));

        return Money::fromCents(max(0, Money::toCents($this->net_amount_due) - $deferredCents));
    }

    public function getCurrentPeriodBalanceAttribute(): string
    {
        return Money::fromCents(max(
            0,
            Money::toCents($this->native_due_amount) - Money::toCents($this->total_paid)
        ));
    }

    public function getIncomingExtensionBalanceAttribute(): string
    {
        $extensions = $this->relationLoaded('extensionsReceived')
            ? $this->extensionsReceived
            : $this->extensionsReceived()->get();

        return Money::fromCents($extensions->sum(fn (BillExtension $extension): int => Money::toCents($extension->remaining_balance)));
    }

    public function getPeriodBalanceAttribute(): string
    {
        return Money::fromCents(
            Money::toCents($this->current_period_balance)
            + Money::toCents($this->incoming_extension_balance)
        );
    }

    public function getPaymentStatusAttribute(): string
    {
        $amountDueCents = $this->totalDueCents();
        $amountPaidCents = Money::toCents($this->total_paid);

        if ($amountDueCents <= 0) {
            return 'Fully Paid';
        }

        if ($amountPaidCents >= $amountDueCents) {
            return 'Fully Paid';
        }

        $overdueOriginalBalance = $this->due_date && $this->due_date->isBefore(today())
            && Money::toCents($this->current_period_balance) > 0;
        $outgoingExtensions = $this->relationLoaded('extensions') ? $this->extensions : $this->extensions()->get();
        $incomingExtensions = $this->relationLoaded('extensionsReceived') ? $this->extensionsReceived : $this->extensionsReceived()->get();
        $overdueDeferredBalance = $outgoingExtensions->merge($incomingExtensions)
            ->contains(fn (BillExtension $extension): bool => Money::toCents($extension->remaining_balance) > 0
            && $extension->approved_until->isBefore(today()));

        if ($overdueOriginalBalance || $overdueDeferredBalance) {
            return 'Overdue';
        }

        return $amountPaidCents > 0 ? 'Partially Paid' : 'Unpaid';
    }

    private function totalDueCents(): int
    {
        $outgoing = $this->relationLoaded('extensions') ? $this->extensions : $this->extensions()->get();
        $incoming = $this->relationLoaded('extensionsReceived') ? $this->extensionsReceived : $this->extensionsReceived()->get();
        $outgoingCents = $outgoing->sum(fn (BillExtension $extension): int => Money::toCents($extension->amount_at_approval));
        $incomingCents = $incoming->sum(fn (BillExtension $extension): int => Money::toCents($extension->amount_at_approval));

        return max(0, Money::toCents($this->net_amount_due) - $outgoingCents + $incomingCents);
    }
}
