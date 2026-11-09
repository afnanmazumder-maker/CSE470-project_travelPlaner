<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

/** R16: sends due reminders (check-in, flights within 24 hours, payments, cancellations, digest). */
class DispatchReminders extends Command
{
    protected $signature = 'reminders:dispatch';

    protected $description = 'Create and email every reminder that is due';

    public function handle(ReminderService $reminders): int
    {
        $this->info("Sent {$reminders->run()->count()} reminders.");

        return self::SUCCESS;
    }
}
