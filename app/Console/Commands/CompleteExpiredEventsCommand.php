<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\ScholarService;
use Illuminate\Console\Command;

class CompleteExpiredEventsCommand extends Command
{
    protected $signature = 'events:complete-expired';

    protected $description = 'Mark confirmed events as completed once their end date/time has passed';

    public function handle(ScholarService $scholar): int
    {
        $completedIds = Event::completeExpiredConfirmedIds();
        $updated = count($completedIds);

        if ($completedIds !== []) {
            $scholar->notifyScholarsOfCompletedEvents($completedIds);
        }

        $this->info("Completed {$updated} expired confirmed event(s).");

        return self::SUCCESS;
    }
}
