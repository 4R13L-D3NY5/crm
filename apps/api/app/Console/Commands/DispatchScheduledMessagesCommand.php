<?php

namespace App\Console\Commands;

use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Automations\Services\ScheduledMessageDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchScheduledMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:dispatch-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending scheduled messages whose scheduled_at is past or current';

    /**
     * Execute the console command.
     */
    public function handle(ScheduledMessageDispatcher $dispatcher): int
    {
        $now = Carbon::now();

        $pendingMessages = ScheduledMessage::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->with(['contact', 'whatsappAccount'])
            ->get();

        if ($pendingMessages->isEmpty()) {
            $this->info("No pending scheduled messages to dispatch at {$now->toDateTimeString()}.");
            return 0;
        }

        $this->info("Found {$pendingMessages->count()} pending scheduled messages. Dispatching...");

        $successCount = 0;
        $failCount = 0;

        foreach ($pendingMessages as $message) {
            $this->line("Dispatching message {$message->id} to {$message->recipient_phone}...");
            $ok = $dispatcher->dispatch($message);

            if ($ok) {
                $successCount++;
                $this->info("✓ Message {$message->id} sent.");
            } else {
                $failCount++;
                $this->error("✗ Message {$message->id} failed.");
            }
        }

        $this->info("Dispatch completed: {$successCount} succeeded, {$failCount} failed.");

        return 0;
    }
}
