<?php

namespace App\Models\Concerns;

use App\Models\ScholarshipClub;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToScholarshipClub
{
    public function scholarshipClub(): BelongsTo
    {
        return $this->belongsTo(ScholarshipClub::class);
    }

    /**
     * Club-owned rows stay with that club. Rows saved before a club was recorded
     * remain visible inside the same municipality or city.
     */
    public function scopeVisibleToClubMember(Builder $query, ?User $user): Builder
    {
        $clubId = $user?->scholarship_club_id;

        if (! $clubId) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $scoped) use ($clubId, $table) {
            $scoped->where("{$table}.scholarship_club_id", $clubId)
                ->orWhereNull("{$table}.scholarship_club_id");
        });
    }
}
