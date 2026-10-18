<?php

namespace App\Services;

use App\Models\ScholarSuspension;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountAccessService
{
    public const SUSPENSION_DAYS = 7;

    public function __construct(private ScholarService $scholar) {}

    public function restrict(User $target, string $status, User $actor, ?string $reason = null): void
    {
        if ($status === User::STATUS_SUSPENDED && $target->isScholar()) {
            $this->suspendScholarForPeriod($target, $actor, $reason);

            return;
        }

        if ($status === User::STATUS_BLOCKED) {
            $this->closeOpenSuspensions($target, $actor, ScholarSuspension::LIFT_BLOCKED);
        }

        $this->applyRestriction($target, $status, $actor);
    }

    public function reactivate(User $target, User $actor): void
    {
        $this->closeOpenSuspensions($target, $actor, ScholarSuspension::LIFT_MANUAL);
        $this->restoreAccess($target, 'Account reactivated by '.$actor->full_name, $actor);
    }

    public function releaseExpiredSuspensions(): int
    {
        $due = ScholarSuspension::query()
            ->whereNull('lifted_at')
            ->where('ends_at', '<=', now())
            ->with('scholar')
            ->get();

        $released = 0;

        foreach ($due as $suspension) {
            $user = $suspension->scholar;

            $suspension->forceFill([
                'lifted_at' => now(),
                'lift_type' => ScholarSuspension::LIFT_AUTOMATIC,
            ])->save();

            if (! $user || ! $user->isSuspended()) {
                continue;
            }

            $this->restoreAccess(
                $user,
                'Account reactivated automatically after the 7-day suspension ended.',
                null
            );
            $released++;
        }

        return $released;
    }

    private function suspendScholarForPeriod(User $target, User $actor, ?string $reason): void
    {
        $this->closeOpenSuspensions($target, $actor, ScholarSuspension::LIFT_MANUAL);

        $starts = now();
        $reason = filled($reason) ? trim((string) $reason) : null;

        ScholarSuspension::query()->create([
            'user_id' => $target->id,
            'issued_by' => $actor->id,
            'reason' => $reason !== '' ? $reason : null,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDays(self::SUSPENSION_DAYS),
        ]);

        $target->unsetRelation('activeSuspension');
        $this->applyRestriction($target, User::STATUS_SUSPENDED, $actor);
    }

    private function applyRestriction(User $target, string $status, User $actor): void
    {
        $target->forceFill([
            'status' => $status,
            'last_seen_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('sessions')->where('user_id', $target->id)->delete();

        $verb = $status === User::STATUS_BLOCKED ? 'blocked' : 'suspended';

        $this->scholar->logActivity($target, 'account', 'Account '.$verb.' by '.$actor->full_name);
        $this->scholar->notify(
            $target,
            'Account '.ucfirst($verb),
            $target->fresh()->accessRestrictedMessage(),
            'system',
            true
        );
    }

    private function restoreAccess(User $target, string $activity, ?User $actor): void
    {
        $target->update(['status' => User::STATUS_APPROVED]);
        $target->activateFreshEventList();
        $target->unsetRelation('activeSuspension');

        $this->scholar->logActivity($target, 'account', $activity);
        $this->scholar->notify(
            $target,
            'Account Reactivated',
            $actor
                ? 'Your account has been reactivated. You can log in and use the system again.'
                : 'Your 7-day suspension has ended. Your account is active again and you can log in normally.',
            'system',
            true
        );
    }

    private function closeOpenSuspensions(User $target, ?User $actor, string $liftType): void
    {
        ScholarSuspension::query()
            ->where('user_id', $target->id)
            ->whereNull('lifted_at')
            ->update([
                'lifted_at' => now(),
                'lift_type' => $liftType,
                'lifted_by' => $actor?->id,
                'updated_at' => now(),
            ]);

        $target->unsetRelation('activeSuspension');
    }
}
