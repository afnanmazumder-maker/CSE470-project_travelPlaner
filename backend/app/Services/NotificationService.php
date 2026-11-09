<?php

namespace App\Services;

use App\Mail\TravelReminderMail;
use App\Models\Budget;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * R16: the one place alerts are created. It honours the user's channel
 * preferences, stores the in-app copy and sends the email.
 */
class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?Budget $budget = null,
        ?string $dedupeKey = null,
    ): ?Notification {
        if ($dedupeKey !== null && Notification::where('user_id', $user->id)->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $preference = NotificationPreference::forUser($user);
        $inApp = $preference->wants($type, 'in_app');
        $email = $preference->wants($type, 'email');

        if (! $inApp && ! $email) {
            return null;
        }

        $notification = new Notification([
            'budget_id' => $budget?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'show_in_app' => $inApp,
            'dedupe_key' => $dedupeKey,
        ]);
        $notification->user()->associate($user);
        $notification->save();

        if ($email) {
            try {
                Mail::to($user)->send(new TravelReminderMail($notification));
                $notification->update(['emailed_at' => now()]);
            } catch (Throwable $e) {
                // The in-app copy is saved; a failed email must not break the caller.
                Log::error('Reminder email failed', ['notification' => $notification->id, 'error' => $e->getMessage()]);
            }
        }

        return $notification;
    }
}
