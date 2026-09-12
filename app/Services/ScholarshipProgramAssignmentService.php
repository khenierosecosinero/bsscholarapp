<?php

namespace App\Services;

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
                'scholarship_program_id' => 'Please select a valid scholarship program for your designated area.',
            ]);
        }

        return [
            'scholarship_program_id' => $program->id,
            ...$program->registrationLocation(),
        ];
    }
}
