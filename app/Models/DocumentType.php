<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'required', 'scholarship_program_id',
        'submissions_open', 'submissions_opened_at', 'submissions_closed_at', 'submission_deadline',
    ];

    protected $attributes = [
        'submissions_open' => true,
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'submissions_open' => 'boolean',
            'submissions_opened_at' => 'datetime',
            'submissions_closed_at' => 'datetime',
            'submission_deadline' => 'datetime',
        ];
    }

    public function submissionsAreOpen(): bool
    {
        return (bool) $this->submissions_open;
    }

    public function deadlineHasPassed(): bool
    {
        return $this->submission_deadline !== null && $this->submission_deadline->lte(now());
    }

    public function reopenedAfterDeadline(): bool
    {
        return $this->deadlineHasPassed()
            && $this->submissionsAreOpen()
            && $this->submissions_opened_at !== null
            && $this->submissions_opened_at->gt($this->submission_deadline);
    }

    public function acceptsSubmissions(): bool
    {
        if (! $this->submissionsAreOpen()) {
            return false;
        }

        return $this->submission_deadline === null || $this->submission_deadline->gt(now());
    }

    public function deadlineLabel(): string
    {
        if ($this->submission_deadline === null) {
            return 'No deadline set';
        }

        return $this->submission_deadline
            ->timezone(config('app.timezone'))
            ->format('M j, Y g:i A');
    }

    public function deadlineInputValue(): string
    {
        if ($this->submission_deadline === null) {
            return '';
        }

        return $this->submission_deadline
            ->timezone(config('app.timezone'))
            ->format('Y-m-d\TH:i');
    }

    public function submissionStatusLabel(): string
    {
        return $this->submissionsAreOpen() ? 'OPEN' : 'CLOSED';
    }

    public function submissionStatusBadgeClass(): string
    {
        return $this->submissionsAreOpen() ? 'green' : 'gray';
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
