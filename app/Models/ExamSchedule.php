<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSchedule extends Model
{
    public const PERIODS = ['Prelim', 'Midterm', 'Semi-final', 'Final', 'Full Term'];

    protected $fillable = [
        'school_term_id',
        'course_id',
        'year_level',
        'exam_period',
        'exam_date',
        'scope_key',
    ];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function schoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
