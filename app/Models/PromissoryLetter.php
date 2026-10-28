<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromissoryLetter extends Model
{
    public const STATUSES = ['Pending', 'Approved', 'Rejected', 'Fulfilled', 'Overdue'];

    protected $fillable = [
        'student_profile_id',
        'bill_id',
        'student_name',
        'student_number',
        'course_name',
        'year_level',
        'school_year',
        'semester',
        'billing_period',
        'invoice_number',
        'outstanding_amount',
        'requested_amount',
        'reason',
        'commitment_date',
        'contact_information',
        'submitted_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'next_school_term_id',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_amount' => 'decimal:2',
            'requested_amount' => 'decimal:2',
            'commitment_date' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function nextSchoolTerm(): BelongsTo
    {
        return $this->belongsTo(SchoolTerm::class, 'next_school_term_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PromissoryLetterEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function extension(): HasOne
    {
        return $this->hasOne(BillExtension::class);
    }
}
