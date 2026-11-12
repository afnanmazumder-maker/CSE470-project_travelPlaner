import { useEffect, useState } from 'react'
import api, { errorMessage } from '../api'
import FloatingNotice from '../components/FloatingNotice'
import TravelTabs from '../components/TravelTabs'
import { money } from '../format'
import { clock, day, duration, label } from '../travelFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'
const TABS = { search: 'One-way', multi: 'Multi-city', calendar: 'Price calendar', alerts: 'Price alerts' }
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const FIRST_SEARCH = {
  origin: 'DAC', destination: 'BKK', date: '2026-12-05', passengers: '2', cabin_class: 'economy',
  sort: 'price', max_price: '', max_stops: '', max_duration_hours: '', depart_windows: [],
}

function AirportSelect({ airports, value, onChange }) {
  return (
    <select className={inputClass} value={value} onChange={(e) => onChange(e.target.value)}>
      {airports.map((a) => (
        <option key={a.code} value={a.code}>{a.code} · {a.city}</option>
      ))}
    </select>
  )
}

function FlightCard({ flight, onSave }) {
  const stops = flight.stops === 0 ? 'Direct' : `${flight.stops} stop via ${flight.stopover}`
  const nextDay = flight.arrive_at.slice(0, 10) !== flight.depart_at.slice(0, 10)

  return (
    <li className="flex flex-wrap items-center justify-between gap-3 py-3">
      <div>
        <p className="font-semibold">
          {clock(flight.depart_at)} → {clock(flight.arrive_at)}
          {nextDay && <span className="text-xs text-gray-500"> +1</span>}
          <span className="ml-2 text-sm font-normal text-gray-500">{flight.origin} → {flight.destination}</span>
        </p>
        <p className="text-sm text-gray-600">
          {flight.airline} · {flight.flight_number} · {duration(flight.duration_minutes)} · {stops} · {flight.seats_available} seats left
        </p>
      </div>
      <div className="flex items-center gap-3">
        <div className="text-right">
          <p className="font-bold">{money(flight.total_price ?? flight.price)}</p>
          <p className="text-xs text-gray-500">{money(flight.price)} per person</p>
        </div>
        <button className="rounded bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white" onClick={() => onSave(flight)}>
          Save booking
        </button>
      </div>
    </li>
  )
}

export default function FlightSearch() {
  const [tab, setTab] = useState('search')
  const [options, setOptions] = useState(null)
  const [budgets, setBudgets] = useState([])
  const [tripId, setTripId] = useState('')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const [form, setForm] = useState(FIRST_SEARCH)
  const [results, setResults] = useState(null)
  const [searchRequest, setSearchRequest] = useState(null)

  const [legs, setLegs] = useState([
    { origin: 'DAC', destination: 'BKK', date: '2026-12-05' },
    { origin: 'BKK', destination: 'DPS', date: '2026-12-09' },
    { origin: 'DPS', destination: 'DAC', date: '2026-12-15' },
  ])
  const [multiForm, setMultiForm] = useState({ passengers: '1', cabin_class: 'economy' })
  const [multi, setMulti] = useState(null)

  const [calendarForm, setCalendarForm] = useState({ origin: 'DAC', destination: 'CDG', month: '2027-01', cabin_class: 'economy' })
  const [calendar, setCalendar] = useState(null)

  const [alerts, setAlerts] = useState([])
  const [alertForm, setAlertForm] = useState({ origin: 'DAC', destination: 'BKK', depart_date: '', cabin_class: 'economy', target_price: '' })

  useEffect(() => {
    Promise.all([api.get('/flights/options'), api.get('/trips', { params: { can_edit: 1 } }), api.get('/price-alerts')])
      .then(([optionRes, budgetRes, alertRes]) => {
        setOptions(optionRes.data)
        setBudgets(budgetRes.data)
        setAlerts(alertRes.data)
        setSearchRequest(FIRST_SEARCH)
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  function update(setter, current, field) {
    return (e) => setter({ ...current, [field]: e.target.value })
  }

  useEffect(() => {
    if (!searchRequest) return
    let ignore = false
    const params = Object.fromEntries(Object.entries(searchRequest).filter(([, v]) => v !== '' && !(Array.isArray(v) && !v.length)))

    api
      .get('/flights/search', { params })
      .then((res) => {
        if (!ignore) setResults(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [searchRequest])

  function search(e) {
    e.preventDefault()
    setError('')
    setSearchRequest({ ...form })
  }

  async function searchLegs(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.post('/flights/multi-city', {
        legs, passengers: Number(multiForm.passengers), cabin_class: multiForm.cabin_class, sort: 'price',
      })
      setMulti(res.data)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function loadCalendar(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.get('/flights/price-calendar', { params: calendarForm })
      setCalendar(res.data)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  function pickCalendarDay(date) {
    const next = { ...form, origin: calendarForm.origin, destination: calendarForm.destination, cabin_class: calendarForm.cabin_class, date }
    setForm(next)
    setSearchRequest(next)
    setTab('search')
  }

  async function createAlert(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.post('/price-alerts', {
        ...alertForm,
        depart_date: alertForm.depart_date || null,
        target_price: alertForm.target_price === '' ? null : Number(alertForm.target_price),
      })
      setAlerts([res.data.alert, ...alerts])
      setNotice(`Watching ${res.data.alert.origin} → ${res.data.alert.destination}. Cheapest now ${money(res.data.alert.last_seen_price ?? 0)}.`)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function deleteAlert(id) {
    try {
      await api.delete(`/price-alerts/${id}`)
      setAlerts(alerts.filter((a) => a.id !== id))
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function saveBooking(flight) {
    setError('')
    try {
      await api.post('/bookings', {
        type: 'flight',
        flight_id: flight.id,
        budget_id: tripId === '' ? null : Number(tripId),
        title: `${flight.flight_number} ${flight.origin} → ${flight.destination}`,
        provider: flight.airline,
        start_at: flight.depart_at,
        end_at: flight.arrive_at,
        status: 'planned',
        price: flight.total_price ?? flight.price,
        party_size: Number(form.passengers),
      })
      setNotice(`Saved ${flight.flight_number} to My bookings.`)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  if (!options) return <p className="p-6 text-gray-600">{error || 'Loading...'}</p>

  const calendarOffset = calendar ? (new Date(`${calendar.days[0].date}T00:00:00`).getDay() + 6) % 7 : 0

  return (
    <div className="mx-auto max-w-5xl p-6">
      <TravelTabs />
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Flight search</h1>
          <p className="text-gray-600">Fares come from flight data maintained by Voyenta admins. Saving a flight keeps a booking record; nothing is purchased.</p>
        </div>
        <label className="text-sm">
          Save bookings to trip
          <select className={inputClass} value={tripId} onChange={(e) => setTripId(e.target.value)}>
            <option value="">No trip</option>
            {budgets.map((b) => <option key={b.id} value={b.id}>{b.title}</option>)}
          </select>
        </label>
      </div>

      <FloatingNotice notice={notice} error={error} setNotice={setNotice} setError={setError} />

      <div className="mt-6 flex flex-wrap gap-1 border-b text-sm">
        {Object.entries(TABS).map(([key, text]) => (
          <button
            key={key}
            className={`px-3 py-2 ${tab === key ? 'border-b-2 border-blue-700 font-semibold text-blue-700' : 'text-gray-600'}`}
            onClick={() => setTab(key)}
          >
            {text}
          </button>
        ))}
      </div>

      {tab === 'search' && (
        <div className="mt-4 grid gap-6 md:grid-cols-[320px_1fr]">
          <form onSubmit={search} className="space-y-3 rounded-lg bg-white p-4 shadow">
            <div className="grid grid-cols-2 gap-3">
              <label className="text-sm">From<AirportSelect airports={options.airports} value={form.origin} onChange={(v) => setForm({ ...form, origin: v })} /></label>
              <label className="text-sm">To<AirportSelect airports={options.airports} value={form.destination} onChange={(v) => setForm({ ...form, destination: v })} /></label>
              <label className="text-sm">Date<input className={inputClass} type="date" value={form.date} onChange={update(setForm, form, 'date')} required /></label>
              <label className="text-sm">Passengers<input className={inputClass} type="number" min="1" max="9" value={form.passengers} onChange={update(setForm, form, 'passengers')} /></label>
              <label className="text-sm">Cabin
                <select className={inputClass} value={form.cabin_class} onChange={update(setForm, form, 'cabin_class')}>
                  {options.cabin_classes.map((c) => <option key={c} value={c}>{label(c)}</option>)}
                </select>
              </label>
              <label className="text-sm">Sort by
                <select className={inputClass} value={form.sort} onChange={update(setForm, form, 'sort')}>
                  {options.sorts.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
              </label>
              <label className="text-sm">Max price<input className={inputClass} type="number" min="0" placeholder="any" value={form.max_price} onChange={update(setForm, form, 'max_price')} /></label>
              <label className="text-sm">Max stops
                <select className={inputClass} value={form.max_stops} onChange={update(setForm, form, 'max_stops')}>
                  <option value="">Any</option><option value="0">Direct only</option><option value="1">1 stop</option>
                </select>
              </label>
            </div>
            <fieldset className="text-sm">
              <legend>Departure time</legend>
              <div className="mt-1 flex flex-wrap gap-2">
                {Object.entries(options.depart_windows).map(([key, [from, to]]) => (
                  <label key={key} className="flex items-center gap-1 rounded border px-2 py-1">
                    <input
                      type="checkbox"
                      checked={form.depart_windows.includes(key)}
                      onChange={(e) => setForm({
                        ...form,
                        depart_windows: e.target.checked ? [...form.depart_windows, key] : form.depart_windows.filter((w) => w !== key),
                      })}
                    />
                    {key} {from}–{to}h
                  </label>
                ))}
              </div>
            </fieldset>
            <button className="w-full rounded bg-blue-600 px-4 py-2 font-semibold text-white">Search flights</button>
          </form>

          <section className="rounded-lg bg-white p-4 shadow">
            <h2 className="text-xl font-semibold">{results ? `${results.count} flights on ${day(searchRequest.date)}` : 'Results'}</h2>
            {results?.count === 0 && <p className="py-4 text-gray-600">No flights match. Try another date or fewer filters.</p>}
            <ul className="divide-y">
              {results?.results.map((f) => <FlightCard key={f.id} flight={f} onSave={saveBooking} />)}
            </ul>
          </section>
        </div>
      )}

      {tab === 'multi' && (
        <div className="mt-4 grid gap-6 md:grid-cols-[320px_1fr]">
          <form onSubmit={searchLegs} className="space-y-3 rounded-lg bg-white p-4 shadow">
            {legs.map((leg, index) => (
              <div key={index} className="grid grid-cols-3 gap-2 text-sm">
                <AirportSelect airports={options.airports} value={leg.origin} onChange={(v) => setLegs(legs.map((l, i) => (i === index ? { ...l, origin: v } : l)))} />
                <AirportSelect airports={options.airports} value={leg.destination} onChange={(v) => setLegs(legs.map((l, i) => (i === index ? { ...l, destination: v } : l)))} />
                <input className={inputClass} type="date" value={leg.date} onChange={(e) => setLegs(legs.map((l, i) => (i === index ? { ...l, date: e.target.value } : l)))} />
              </div>
            ))}
            <div className="flex gap-2 text-sm">
              <button type="button" className="rounded border px-3 py-1" disabled={legs.length >= 6} onClick={() => setLegs([...legs, { origin: 'DAC', destination: 'SIN', date: '2026-12-20' }])}>+ Add leg</button>
              <button type="button" className="rounded border px-3 py-1" disabled={legs.length <= 2} onClick={() => setLegs(legs.slice(0, -1))}>− Remove leg</button>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <label className="text-sm">Passengers<input className={inputClass} type="number" min="1" max="9" value={multiForm.passengers} onChange={update(setMultiForm, multiForm, 'passengers')} /></label>
              <label className="text-sm">Cabin
                <select className={inputClass} value={multiForm.cabin_class} onChange={update(setMultiForm, multiForm, 'cabin_class')}>
                  {options.cabin_classes.map((c) => <option key={c} value={c}>{label(c)}</option>)}
                </select>
              </label>
            </div>
            <button className="w-full rounded bg-blue-600 px-4 py-2 font-semibold text-white">Search all legs</button>
          </form>

          <section className="rounded-lg bg-white p-4 shadow">
            {!multi && <p className="text-gray-600">Add 2 to 6 legs in date order.</p>}
            {multi && (
              <>
                <p className="rounded bg-blue-50 p-2">
                  Cheapest combination: <b>{multi.cheapest_trip_total === null ? 'not available (a leg has no flights)' : money(multi.cheapest_trip_total)}</b>
                </p>
                {multi.legs.map((leg) => (
                  <div key={leg.leg} className="mt-4">
                    <h3 className="font-semibold">Leg {leg.leg}: {leg.origin} → {leg.destination}, {day(leg.date)}</h3>
                    {leg.flights.length === 0 ? (
                      <p className="text-sm text-gray-600">No flights on this date.</p>
                    ) : (
                      <ul className="divide-y">{leg.flights.slice(0, 3).map((f) => <FlightCard key={f.id} flight={f} onSave={saveBooking} />)}</ul>
                    )}
                  </div>
                ))}
              </>
            )}
          </section>
        </div>
      )}

      {tab === 'calendar' && (
        <div className="mt-4 grid gap-6 md:grid-cols-[320px_1fr]">
          <form onSubmit={loadCalendar} className="space-y-3 rounded-lg bg-white p-4 shadow">
            <div className="grid grid-cols-2 gap-3">
              <label className="text-sm">From<AirportSelect airports={options.airports} value={calendarForm.origin} onChange={(v) => setCalendarForm({ ...calendarForm, origin: v })} /></label>
              <label className="text-sm">To<AirportSelect airports={options.airports} value={calendarForm.destination} onChange={(v) => setCalendarForm({ ...calendarForm, destination: v })} /></label>
              <label className="text-sm">Month<input className={inputClass} type="month" value={calendarForm.month} onChange={update(setCalendarForm, calendarForm, 'month')} /></label>
              <label className="text-sm">Cabin
                <select className={inputClass} value={calendarForm.cabin_class} onChange={update(setCalendarForm, calendarForm, 'cabin_class')}>
                  {options.cabin_classes.map((c) => <option key={c} value={c}>{label(c)}</option>)}
                </select>
              </label>
            </div>
            <button className="w-full rounded bg-blue-600 px-4 py-2 font-semibold text-white">Show prices</button>
            {calendar?.cheapest && (
              <p className="text-sm text-gray-600">
                Cheapest day: <b>{day(calendar.cheapest.date)}</b> from <b>{money(calendar.cheapest.min_price)}</b>. Green is the cheapest third, red the most expensive.
              </p>
            )}
          </form>

          <section className="rounded-lg bg-white p-4 shadow">
            <h2 className="text-xl font-semibold">Cheapest fare per day</h2>
            {calendar && (
              <div className="mt-3 grid grid-cols-7 gap-1 text-sm">
                {WEEKDAYS.map((d) => <div key={d} className="text-center text-xs text-gray-500">{d}</div>)}
                {Array.from({ length: calendarOffset }, (_, i) => <div key={`blank-${i}`} />)}
                {calendar.days.map((d) => (
                  <button
                    key={d.date}
                    disabled={d.min_price === null}
                    onClick={() => pickCalendarDay(d.date)}
                    className={`rounded border p-1 text-left ${
                      { low: 'bg-green-50', mid: 'bg-amber-50', high: 'bg-red-50' }[d.band] ?? 'opacity-40'
                    }`}
                  >
                    {Number(d.date.slice(8))}
                    <span className="block font-semibold">{d.min_price === null ? '—' : money(d.min_price)}</span>
                  </button>
                ))}
              </div>
            )}
          </section>
        </div>
      )}

      {tab === 'alerts' && (
        <div className="mt-4 grid gap-6 md:grid-cols-[320px_1fr]">
          <form onSubmit={createAlert} className="space-y-3 rounded-lg bg-white p-4 shadow">
            <div className="grid grid-cols-2 gap-3">
              <label className="text-sm">From<AirportSelect airports={options.airports} value={alertForm.origin} onChange={(v) => setAlertForm({ ...alertForm, origin: v })} /></label>
              <label className="text-sm">To<AirportSelect airports={options.airports} value={alertForm.destination} onChange={(v) => setAlertForm({ ...alertForm, destination: v })} /></label>
              <label className="text-sm">Date (optional)<input className={inputClass} type="date" value={alertForm.depart_date} onChange={update(setAlertForm, alertForm, 'depart_date')} /></label>
              <label className="text-sm">Target price<input className={inputClass} type="number" min="1" value={alertForm.target_price} onChange={update(setAlertForm, alertForm, 'target_price')} /></label>
            </div>
            <button className="w-full rounded bg-blue-600 px-4 py-2 font-semibold text-white">Watch this route</button>
            <p className="text-sm text-gray-600">You are told when the cheapest fare drops below the last price seen, or reaches your target.</p>
          </form>

          <section className="rounded-lg bg-white p-4 shadow">
            <h2 className="text-xl font-semibold">Your price alerts</h2>
            {alerts.length === 0 && <p className="py-4 text-gray-600">No price alerts yet.</p>}
            <ul className="divide-y">
              {alerts.map((a) => (
                <li key={a.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                  <div>
                    <p className="font-medium">{a.origin} → {a.destination} · {label(a.cabin_class)}</p>
                    <p className="text-gray-500">
                      {a.depart_date ? day(a.depart_date) : 'Next 90 days'} · last seen {a.last_seen_price ? money(a.last_seen_price) : '—'}
                      {a.target_price ? ` · target ${money(a.target_price)}` : ''}
                    </p>
                    {a.last_notified_price !== null && (
                      <p className="text-green-700">Dropped to {money(a.last_notified_price)}. We emailed you.</p>
                    )}
                  </div>
                  <button className="text-red-700" onClick={() => deleteAlert(a.id)}>Delete</button>
                </li>
              ))}
            </ul>
          </section>
        </div>
      )}
    </div>
  )
}
