{{-- R16: email copy of a reminder. --}}
<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; background: #f4f7f8; padding: 24px; color: #1f2933;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 10px; padding: 24px;">
        <p style="margin: 0 0 4px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #1d4ed8;">Voyenta reminder</p>
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ $notification->title }}</h2>
        <p style="white-space: pre-line; line-height: 1.5;">{{ $notification->body }}</p>
        @if ($notification->budget)
            <p style="color: #64748b; font-size: 13px;">Trip: {{ $notification->budget->title }}</p>
        @endif
        <p style="font-size: 11px; color: #94a3b8; margin-top: 20px;">Choose which reminders you get in Notification settings.</p>
    </div>
</body>
</html>
