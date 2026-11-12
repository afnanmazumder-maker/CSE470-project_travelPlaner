{{-- R16: group trip invite email. --}}
<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; background: #f4f7f8; padding: 24px; color: #1f2933;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 10px; padding: 24px;">
        <p style="margin: 0 0 4px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #1d4ed8;">Voyenta group trip</p>
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ $invite->inviter?->name ?? 'A traveler' }} invited you to "{{ $invite->budget->title }}"</h2>
        <p style="line-height: 1.5;">
            You are invited as <strong>{{ $invite->role }}</strong>
            @if ($invite->budget->destination)
                for a trip to {{ $invite->budget->destination->name }}, {{ $invite->budget->destination->country }}
            @endif
            ({{ $invite->budget->days }} days).
            @if ($invite->role === 'viewer')
                You can see the shared itinerary, votes, comments and history.
            @else
                You can add and change itinerary items, vote and comment.
            @endif
        </p>
        <p style="margin: 20px 0;">
            <a href="{{ $joinUrl }}" style="background: #2563eb; color: #ffffff; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: bold;">Join the trip</a>
        </p>
        <p style="color: #64748b; font-size: 13px;">Log in with {{ $invite->email }} to accept. The link works until {{ $invite->expires_at?->format('j M Y') }}.</p>
    </div>
</body>
</html>
