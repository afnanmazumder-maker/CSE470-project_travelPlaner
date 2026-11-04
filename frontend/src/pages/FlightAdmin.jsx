import { useEffect, useState } from 'react'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'
import TravelTabs from '../components/TravelTabs'
import { dayTime, duration, label } from '../travelFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'
const FLIGHT_FIELDS = [
  'airline', 'airline_code', 'flight_number', 'origin', 'destination', 'depart_at', 'arrive_at',
  'duration_minutes', 'stops', 'stopover', 'cabin_class', 'price', 'seats_available',
]
const NEW_FLIGHT = {
  airline: 'Biman Bangladesh Airlines', airline_code: 'BG', flight_number: 'BG088', origin: 'DAC', destination: 'BKK',
  depart_at: '', arrive_at: '', duration_minutes: '150', stops: '0', stopover: '', cabin_class: 'economy', price: '199', seats_available: '9',
}

export default function FlightAdmin() {
  const { user } = useAuth()
  const [options, setOptions] = useState(null)
  const [filters, setFilters] = useState({ origin: 'DAC', destination: 'CDG', date: '2027-04-13' })
  const [page, setPage] = useState(1)
  const [data, setData] = useState(null)
  const [edits, setEdits] = useState({})
  const [form, setForm] = useState(NEW_FLIGHT)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    api.get('/flights/options').then((res) => setOptions(res.data)).catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    if (user?.role !== 'admin') return
    let ignore = false

    api
      .get('/admin/flights', { params: { ...filters, page } })
      .then((res) => {
        if (!ignore) setData(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [user, filters, page, reload])

  if (user?.role !== 'admin') {
    return (
      <div className="mx-auto max-w-5xl p-6">
        <TravelTabs />
        <p className="rounded bg-red-50 p-2 text-red-700">Only administrators can manage flight data.</p>
      </div>
    )
  }

  async function save(flight) {
    setError('')
    const fields = Object.fromEntries(FLIGHT_FIELDS.map((name) => [name, flight[name]]))
    try {
      const res = await api.put(`/admin/flights/${flight.id}`, { ...fields, ...(edits[flight.id] ?? {}) })
      setNotice(`Saved ${flight.flight_number}. Price alerts announced: ${res.data.price_alerts_announced.length}.`)
      setEdits({ ...edits, [flight.id]: undefined })
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function remove(flight) {
    if (!window.confirm(`Delete ${flight.flight_number} on ${dayTime(flight.depart_at)}?`)) return
    try {
      await api.delete(`/admin/flights/${flight.id}`)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function add(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.post('/admin/flights', { ...form, stopover: form.stopover || null })
      setNotice(`Added ${res.data.flight.flight_number}. Price alerts announced: ${res.data.price_alerts_announced.length}.`)
      setForm(NEW_FLIGHT)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const airportOptions = options?.airports.map((a) => <option key={a.code} value={a.code}>{a.code} · {a.city}</option>)
  const field = (name) => (e) => setForm({ ...form, [name]: e.target.value })

  return (
    <div className="mx-auto max-w-6xl p-6">
      <TravelTabs />
      <h1 className="text-3xl font-bold">Flight data</h1>
      <p className="text-gray-600">Fares here are what travelers search. Lowering a fare re-checks price alerts on that route.</p>

      {notice && <p className="mt-4 rounded bg-green-50 p-2 text-green-800">{notice}</p>}
      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 flex flex-wrap items-end gap-3 text-sm">
        <select className="rounded border px-3 py-2" value={filters.origin} onChange={(e) => { setPage(1); setFilters({ ...filters, origin: e.target.value }) }}>{airportOptions}</select>
        <select className="rounded border px-3 py-2" value={filters.destination} onChange={(e) => { setPage(1); setFilters({ ...filters, destination: e.target.value }) }}>{airportOptions}</select>
        <input className="rounded border px-3 py-2" type="date" value={filters.date} onChange={(e) => { setPage(1); setFilters({ ...filters, date: e.target.value }) }} />
        {data && <span className="text-gray-600">{data.total} fares · page {data.current_page} of {data.last_page}</span>}
      </div>

      <div className="mt-4 overflow-x-auto rounded-lg bg-white shadow">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th className="p-3">Flight</th><th className="p-3">Route</th><th className="p-3">Departs</th><th className="p-3">Cabin</th><th className="p-3">Price</th><th className="p-3">Seats</th><th className="p-3" /></tr>
          </thead>
          <tbody className="divide-y">
            {data?.data.map((f) => (
              <tr key={f.id}>
                <td className="p-3"><p className="font-medium">{f.flight_number}</p><p className="text-gray-500">{f.airline}</p></td>
                <td className="p-3">{f.origin} → {f.destination}<p className="text-gray-500">{f.stops ? `via ${f.stopover}` : 'direct'}</p></td>
                <td className="p-3">{dayTime(f.depart_at)}<p className="text-gray-500">{duration(f.duration_minutes)}</p></td>
                <td className="p-3">{label(f.cabin_class)}</td>
                <td className="p-3">
                  <input className="w-24 rounded border px-2 py-1" type="number" min="1" aria-label="Price" value={edits[f.id]?.price ?? f.price}
                    onChange={(e) => setEdits({ ...edits, [f.id]: { ...(edits[f.id] ?? {}), price: e.target.value } })} />
                </td>
                <td className="p-3">
                  <input className="w-20 rounded border px-2 py-1" type="number" min="0" aria-label="Seats" value={edits[f.id]?.seats_available ?? f.seats_available}
                    onChange={(e) => setEdits({ ...edits, [f.id]: { ...(edits[f.id] ?? {}), seats_available: e.target.value } })} />
                </td>
                <td className="whitespace-nowrap p-3">
                  <button className="rounded bg-blue-600 px-3 py-1 font-semibold text-white" onClick={() => save(f)}>Save</button>
                  <button className="ml-2 text-red-700" onClick={() => remove(f)}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex gap-2 text-sm">
        <button className="rounded border px-3 py-1" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Previous</button>
        <button className="rounded border px-3 py-1" disabled={!data || page >= data.last_page} onClick={() => setPage(page + 1)}>Next →</button>
      </div>

      <h2 className="mt-8 text-2xl font-semibold">Add a fare</h2>
      <form onSubmit={add} className="mt-3 grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-3 lg:grid-cols-6">
        <label className="text-sm sm:col-span-2">Airline<input className={inputClass} value={form.airline} onChange={field('airline')} required /></label>
        <label className="text-sm">Code<input className={inputClass} value={form.airline_code} onChange={field('airline_code')} required maxLength={3} /></label>
        <label className="text-sm">Flight no.<input className={inputClass} value={form.flight_number} onChange={field('flight_number')} required /></label>
        <label className="text-sm">From<select className={inputClass} value={form.origin} onChange={field('origin')}>{airportOptions}</select></label>
        <label className="text-sm">To<select className={inputClass} value={form.destination} onChange={field('destination')}>{airportOptions}</select></label>
        <label className="text-sm sm:col-span-2">Departs (local)<input className={inputClass} type="datetime-local" value={form.depart_at} onChange={field('depart_at')} required /></label>
        <label className="text-sm sm:col-span-2">Arrives (local)<input className={inputClass} type="datetime-local" value={form.arrive_at} onChange={field('arrive_at')} required /></label>
        <label className="text-sm">Minutes<input className={inputClass} type="number" min="20" value={form.duration_minutes} onChange={field('duration_minutes')} required /></label>
        <label className="text-sm">Stops<input className={inputClass} type="number" min="0" max="3" value={form.stops} onChange={field('stops')} /></label>
        <label className="text-sm">Via<input className={inputClass} value={form.stopover} onChange={field('stopover')} /></label>
        <label className="text-sm">Cabin
          <select className={inputClass} value={form.cabin_class} onChange={field('cabin_class')}>
            {options?.cabin_classes.map((c) => <option key={c} value={c}>{label(c)}</option>)}
          </select>
        </label>
        <label className="text-sm">Price<input className={inputClass} type="number" min="1" value={form.price} onChange={field('price')} required /></label>
        <label className="text-sm">Seats<input className={inputClass} type="number" min="0" value={form.seats_available} onChange={field('seats_available')} required /></label>
        <div className="flex items-end sm:col-span-3 lg:col-span-6">
          <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Add fare</button>
        </div>
      </form>
    </div>
  )
}
