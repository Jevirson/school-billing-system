<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingSchedule extends Model
{
    public const PERIODS = ['Prelim', 'Midterm', 'Semi-final', 'Final', 'Full Term'];

    protected $fillable = ['school_term_id', 'course_id', 'year_level', 'billing_period', 'due_date', 'scope_key'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function schoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function appliesTo(StudentProfile $student): bool
    {
        return ($this->course_id === null || (int) $this->course_id === (int) $student->course_id)
            && ($this->year_level === null || $this->year_level === $student->year_level);
    }

    public function getSpecificityAttribute(): int
    {
        return ($this->course_id === null ? 0 : 1) + ($this->year_level === null ? 0 : 2);
    }

    public static function resolveFor(StudentProfile $student, int $termId, string $period): ?self
    {
        return self::with('schoolTerm')
            ->where('school_term_id', $termId)
            ->where('billing_period', $period)
            ->get()
            ->filter(fn (self $schedule): bool => $schedule->appliesTo($student))
            ->sortByDesc('specificity')
            ->first();
    }

    public static function nextPeriod(string $period): ?string
    {
        $index = array_search($period, self::PERIODS, true);

        return $index === false || ! isset(self::PERIODS[$index + 1])
            ? null
            : self::PERIODS[$index + 1];
    }

    public static function nextApplicablePeriod(StudentProfile $student, int $termId, string $period): ?string
    {
        $nextPeriod = self::nextPeriod($period);
        if ($nextPeriod === null) {
            return null;
        }

        return self::resolveFor($student, $termId, $nextPeriod) === null
            ? null
            : $nextPeriod;
    }
}
