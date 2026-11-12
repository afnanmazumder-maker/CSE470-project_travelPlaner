<?php

namespace App\Models;

use Database\Factories\TripMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R16: a person on a group trip (an R7 budget). A row starts as an invite
 * (by email, or a link with no email) and becomes active when someone joins.
 * The budget's creator is always the owner and has no row.
 */
class TripMember extends Model
{
    /** @use HasFactory<TripMemberFactory> */
    use HasFactory;

    /** @var list<string> */
    public const ROLES = ['owner', 'editor', 'viewer'];

    /** @var list<string> roles that may change the itinerary, vote and comment */
    public const EDIT_ROLES = ['owner', 'editor'];

    public const INVITE_DAYS = 14;

    protected $fillable = ['email', 'role', 'status', 'invite_token', 'expires_at', 'joined_at'];

    protected $hidden = ['invite_token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * @param  Builder<TripMember>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * @param  Builder<TripMember>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'invited');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
