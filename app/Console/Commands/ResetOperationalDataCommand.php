<?php

namespace App\Console\Commands;

use App\Services\OperationalDataResetService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class ResetOperationalDataCommand extends Command
{
    protected $signature = 'app:reset-operational-data {--force : Run without confirmation}';

    protected $description = 'Wipe operational and test data while keeping schema, programs, and the permanent admin';

    public function handle(OperationalDataResetService $reset): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset operational data to a first-use empty state?')) {
            $this->warn('Reset cancelled.');

            return self::FAILURE;
        }

        try {
            $snapshot = $reset->reset();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Operational data reset failed.');

            return self::FAILURE;
        }

        $this->info('Operational data reset. Features are unchanged; records start empty.');
        $this->line('permanent_admin='.$snapshot['admin_email']);
        $this->line('users='.$snapshot['users']);
        $this->line('scholarship_programs='.$snapshot['scholarship_programs']);
        $this->line('academic_year='.$snapshot['academic_year']);
        $this->line('events='.$snapshot['events']);
        $this->line('attendances='.$snapshot['attendances']);
        $this->line('documents='.$snapshot['documents']);
        $this->line('document_types='.$snapshot['document_types']);
        $this->line('announcements='.$snapshot['announcements']);
        $this->line('notifications='.$snapshot['notifications']);

        return self::SUCCESS;
    }
}
