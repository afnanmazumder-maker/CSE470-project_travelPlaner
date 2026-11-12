{{-- R9: price-drop alert email. --}}
<!doctype html>
<html>
<body style="font-family: Arial, sans-serif; background: #f4f7f8; padding: 24px; color: #1f2933;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 10px; padding: 24px;">
        <p style="margin: 0 0 4px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #1d4ed8;">Voyenta price alert</p>
        <h2 style="margin: 0 0 12px; font-size: 18px;">{{ $alert->origin }} → {{ $alert->destination }} fares dropped to ${{ number_format($price, 2) }}</h2>
        <p style="line-height: 1.5;">
            The cheapest {{ str_replace('_', ' ', $alert->cabin_class) }} fare
            {{ $alert->depart_date ? 'on '.$alert->depart_date->format('j M Y') : 'in the next 90 days' }}
            is now ${{ number_format($price, 2) }} per passenger.
        </p>
        @if ($previousPrice !== null)
            <p style="color: #64748b; font-size: 13px;">It was ${{ number_format($previousPrice, 2) }} when we last checked.</p>
        @endif
        @if ($alert->target_price !== null)
            <p style="color: #64748b; font-size: 13px;">Your target price is ${{ number_format($alert->target_price, 2) }}.</p>
        @endif
        <p style="font-size: 11px; color: #94a3b8; margin-top: 20px;">You get this because you watch this route in Voyenta's Flights → Price alerts.</p>
    </div>
</body>
</html>
