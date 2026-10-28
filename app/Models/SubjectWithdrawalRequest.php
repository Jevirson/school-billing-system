<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubjectWithdrawalRequest extends Model
{
    protected $fillable = [
        'enrollment_id',
        'student_profile_id',
        'status',
        'student_note',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'credited_amount',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'credited_amount' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(SubjectWithdrawalRefund::class);
    }
}
