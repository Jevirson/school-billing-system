<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'course_id',
        'scholarship_id',
        'year_level',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function scholarships(): BelongsToMany
    {
        return $this->belongsToMany(Scholarship::class, 'scholarship_student_profile');
    }

    public function scholarshipAssignments(): Collection
    {
        $scholarships = $this->relationLoaded('scholarships')
            ? $this->getRelation('scholarships')
            : $this->scholarships;
        $legacyScholarship = $this->relationLoaded('scholarship')
            ? $this->getRelation('scholarship')
            : $this->scholarship;

        if ($legacyScholarship && ! $scholarships->contains('id', $legacyScholarship->id)) {
            $scholarships->prepend($legacyScholarship);
        }

        return $scholarships->unique('id')->values();
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(PaymentRequest::class);
    }

    public function promissoryLetters(): HasMany
    {
        return $this->hasMany(PromissoryLetter::class);
    }

    public function getInvoiceNameAttribute(): string
    {
        $middleInitial = $this->middle_name
            ? ' '.mb_strtoupper(mb_substr(trim($this->middle_name), 0, 1)).'.'
            : '';

        return "{$this->last_name}, {$this->first_name}{$middleInitial}";
    }
}
