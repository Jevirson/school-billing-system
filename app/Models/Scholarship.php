<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Scholarship extends Model
{
    protected $fillable = ['name', 'discount_amount', 'is_active'];

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function assignedStudentProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'scholarship_student_profile');
    }
}
