<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'user_id', 'document_type_id', 'file_path',
        'original_name', 'status', 'uploaded_at',
        'review_notes', 'reviewed_at', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function isSubmitted(): bool
    {
        return $this->hasFile() || in_array($this->status, ['pending', 'submitted', 'approved', 'rejected'], true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewStatus(): string
    {
        return match ($this->status) {
            'approved' => 'approved',
            'rejected' => 'rejected',
            'not_submitted' => 'not_submitted',
            default => 'pending',
        };
    }

    public function reviewBadgeClass(): string
    {
        return match ($this->reviewStatus()) {
            'approved' => 'green',
            'rejected' => 'red',
            'not_submitted' => 'gray',
            default => 'orange',
        };
    }
}
