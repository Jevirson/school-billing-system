<?php

namespace App\Support;

use App\Models\FeeType;

final class TuitionInstallmentPlan
{
    private const TUITION_TOTAL = '21000.00';

    private const PERIODS = ['Prelim', 'Midterm', 'Semi-final', 'Final'];

    public static function calculate(): array
    {
        $upfrontFees = FeeType::query()
            ->where('is_active', true)
            ->get(['name', 'amount'])
            ->reduce(function (array $totals, FeeType $fee): array {
                $name = mb_strtolower(trim($fee->name));

                if (str_contains($name, 'enrollment')) {
                    $totals['enrollment'] += Money::toCents($fee->amount);
                } elseif ($name === 'sf' || str_contains($name, 'sanitation')) {
                    $totals['sanitation'] += Money::toCents($fee->amount);
                }

                return $totals;
            }, ['enrollment' => 0, 'sanitation' => 0]);

        $tuitionCents = Money::toCents(self::TUITION_TOTAL);
        $enrollmentCents = $upfrontFees['enrollment'];
        $sanitationCents = $upfrontFees['sanitation'];
        $remainingTuitionCents = max(0, $tuitionCents - $enrollmentCents - $sanitationCents);
        $baseInstallmentCents = intdiv($remainingTuitionCents, count(self::PERIODS));
        $extraCents = $remainingTuitionCents % count(self::PERIODS);

        $installments = [];
        foreach (self::PERIODS as $index => $period) {
            $installments[] = [
                'period' => $period,
                'amount' => Money::fromCents($baseInstallmentCents + ($index < $extraCents ? 1 : 0)),
            ];
        }

        return [
            'tuition_total' => self::TUITION_TOTAL,
            'enrollment_fee' => Money::fromCents($enrollmentCents),
            'sanitation_fee' => Money::fromCents($sanitationCents),
            'remaining_tuition' => Money::fromCents($remainingTuitionCents),
            'installments' => $installments,
        ];
    }
}
