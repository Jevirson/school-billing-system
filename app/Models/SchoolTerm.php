<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolTerm extends Model
{
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    protected $fillable = ['school_year', 'semester'];

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function billingSchedules(): HasMany
    {
        return $this->hasMany(BillingSchedule::class);
    }

    public function examSchedules(): HasMany
    {
        return $this->hasMany(ExamSchedule::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(BillExtension::class, 'next_school_term_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->school_year} · {$this->semester}";
    }
}
