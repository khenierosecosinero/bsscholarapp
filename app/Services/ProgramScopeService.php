<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\DocumentType;
use App\Models\Event;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProgramScopeService
{
    public function programIdsFor(User $user): array
    {
        if ($user->scholarship_program_id) {
            return [(int) $user->scholarship_program_id];
        }

        return [];
    }

    public function scopeByPrograms(Builder $query, array $programIds, string $column = 'scholarship_program_id'): Builder
    {
        return $query->whereIn($column, $programIds ?: [0]);
    }

    public function assertScholarCanAccessProgram(User $user, ?int $programId): void
    {
        abort_unless($user->scholarship_program_id, 403, 'Your account is not linked to a scholarship program.');

        abort_unless(
            $programId && (int) $programId === (int) $user->scholarship_program_id,
            403,
            'This content belongs to a different scholarship program.'
        );
    }

    public function assertStaffManagesProgram(User $staff, ?int $programId): void
    {
        abort_unless(
            $programId && in_array((int) $programId, array_map('intval', $staff->managedLocationIds()), true),
            403,
            'You can only manage records for your assigned scholarship program.'
        );
    }

    public function assertEventVisibleToScholar(Event $event, User $user): void
    {
        $this->assertScholarCanAccessProgram($user, $event->scholarship_program_id);
    }

    public function assertAnnouncementVisibleToScholar(Announcement $announcement, User $user): void
    {
        $this->assertScholarCanAccessProgram($user, $announcement->scholarship_program_id);
    }

    public function assertDocumentTypeVisibleToScholar(DocumentType $type, User $user): void
    {
        $this->assertScholarCanAccessProgram($user, $type->scholarship_program_id);
    }

    public function assertDocumentTypeManagedByStaff(DocumentType $type, User $staff): void
    {
        $this->assertStaffManagesProgram($staff, $type->scholarship_program_id);
    }

    /**
     * @return array{city_programs: int, province_programs: int, city_scholars: int, province_scholars: int}
     */
    public function programTypeTotals(): array
    {
        $cityProgramIds = ScholarshipProgram::active()->cities()->pluck('id');
        $provinceProgramIds = ScholarshipProgram::active()->provinces()->pluck('id');

        return [
            'city_programs' => $cityProgramIds->count(),
            'province_programs' => $provinceProgramIds->count(),
            'city_scholars' => User::query()->where('role', User::ROLE_SCHOLAR)->whereIn('scholarship_program_id', $cityProgramIds)->count(),
            'province_scholars' => User::query()->where('role', User::ROLE_SCHOLAR)->whereIn('scholarship_program_id', $provinceProgramIds)->count(),
        ];
    }
}
