<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    protected $fillable = ['student_profile_id', 'subject_id', 'school_term_id', 'bill_id', 'units', 'withdrawn_at'];

    protected function casts(): array
    {
        return [
            'units' => 'decimal:1',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function withdrawalRequest(): HasOne
    {
        return $this->hasOne(SubjectWithdrawalRequest::class)->latestOfMany();
    }
}
