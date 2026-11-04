<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['user_id', 'channels', 'daily_digest', 'digest_time', 'checkin_lead_hours', 'deadline_lead_days'];

    /** Same as the column defaults, so a freshly created row is complete without a reload. */
    protected $attributes = [
        'daily_digest' => true,
        'digest_time' => '07:00',
        'checkin_lead_hours' => 48,
        'deadline_lead_days' => 3,
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'daily_digest' => 'boolean',
            'checkin_lead_hours' => 'integer',
            'deadline_lead_days' => 'integer',
        ];
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public static function defaultChannels(): array
    {
        return array_map(fn (array $type): array => ['in_app' => $type['in_app'], 'email' => $type['email']], Notification::TYPES);
    }

    public static function forUser(User $user): self
    {
        return self::firstOrCreate(['user_id' => $user->id], ['channels' => self::defaultChannels()]);
    }

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public function allChannels(): array
    {
        return array_merge(self::defaultChannels(), $this->channels ?? []);
    }

    public function wants(string $type, string $channel): bool
    {
        return (bool) ($this->allChannels()[$type][$channel] ?? false);
    }
}
