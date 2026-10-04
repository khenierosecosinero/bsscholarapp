<?php

namespace App\Services;

use App\Models\ScholarshipClub;
use App\Models\ScholarshipProgram;
use Illuminate\Validation\ValidationException;

class ScholarshipProgramAssignmentService
{
    /**
     * Resolve an active scholarship program and derive the applicant's city/province.
     *
     * @return array{scholarship_program_id: int, city: ?string, province: string}
     */
    public function resolveRegistrationAssignment(int $programId): array
    {
        $program = ScholarshipProgram::query()
            ->active()
            ->find($programId);

        if (! $program) {
            throw ValidationException::withMessages([
                'scholarship_program_id' => 'Please select a valid City or Province Scholarship Program for your area.',
            ]);
        }

        return [
            'scholarship_program_id' => $program->id,
            ...$program->registrationLocation(),
        ];
    }

    /**
     * Resolve a staff-created Scholarship Club and its location program.
     *
     * @return array{scholarship_club_id: int, scholarship_program_id: int, city: ?string, province: string}
     */
    public function resolveClubAssignment(int $clubId): array
    {
        $club = ScholarshipClub::query()
            ->available()
            ->with('program')
            ->find($clubId);

        if (! $club || ! $club->program || ! $club->program->is_active) {
            throw ValidationException::withMessages([
                'scholarship_club_id' => 'Please select a valid Scholarship Club.',
            ]);
        }

        $location = $club->program->registrationLocation();

        return [
            'scholarship_club_id' => $club->id,
            'scholarship_program_id' => $club->scholarship_program_id,
            'city' => $club->city ?: $location['city'],
            'province' => $club->province ?: $location['province'],
        ];
    }
}
