<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromissoryLetterEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['promissory_letter_id', 'changed_by', 'status', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function promissoryLetter(): BelongsTo
    {
        return $this->belongsTo(PromissoryLetter::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
