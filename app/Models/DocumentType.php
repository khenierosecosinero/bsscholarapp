<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'required', 'scholarship_program_id'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
