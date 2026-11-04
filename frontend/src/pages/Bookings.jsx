import { useEffect, useState } from 'react'
import api, { errorMessage } from '../api'
import TravelTabs from '../components/TravelTabs'
import { money } from '../format'
import { dayTime } from '../travelFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'
const STATUS_STYLE = { planned: 'bg-amber-50 text-amber-800', confirmed: 'bg-green-50 text-green-800', cancelled: 'bg-red-50 text-red-700' }
const EMPTY_FORM = {
  type: 'stay', budget_id: '', title: '', provider: '', start_at: '', end_at: '', confirmation_number: '',
  status: 'planned', cancellation_deadline: '', payment_due_at: '', price: '',
}

const BOOKING_FIELDS = [
  'type', 'budget_id', 'flight_id', 'accommodation_id', 'title', 'provider', 'confirmation_number', 'start_at',
  'end_at', 'status', 'price', 'party_size', 'cancellation_deadline', 'payment_due_at', 'notes',
]

function blankToNull(data) {
  return Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v]))
}

export default function Bookings() {
  const [bookings, setBookings] = useState([])
  const [budgets, setBudgets] = useState([])
  const [filters, setFilters] = useState({ budget_id: '', type: '', status: '' })
  const [edits, setEdits] = useState({})
  const [form, setForm] = useState(EMPTY_FORM)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    api.get('/budgets').then((res) => setBudgets(res.data)).catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    let ignore = false
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))

    api
      .get('/bookings', { params })
      .then((res) => {
        if (!ignore) setBookings(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [filters, reload])

  function edit(booking, field, value) {
    setEdits({ ...edits, [booking.id]: { ...(edits[booking.id] ?? {}), [field]: value } })
  }

  async function save(booking) {
    setError('')
    const fields = Object.fromEntries(BOOKING_FIELDS.map((name) => [name, booking[name]]))
    try {
      await api.put(`/bookings/${booking.id}`, { ...fields, ...(edits[booking.id] ?? {}) })
      setEdits({ ...edits, [booking.id]: undefined })
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function remove(id) {
    if (!window.confirm('Delete this booking?')) return
    try {
      await api.delete(`/bookings/${id}`)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function add(e) {
    e.preventDefault()
    setError('')
    try {
      await api.post('/bookings', blankToNull({ ...form, budget_id: form.budget_id === '' ? '' : Number(form.budget_id) }))
      setForm(EMPTY_FORM)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const field = (name) => (e) => setForm({ ...form, [name]: e.target.value })

  return (
    <div className="mx-auto max-w-5xl p-6">
      <TravelTabs />
      <h1 className="text-3xl font-bold">My bookings</h1>
      <p className="text-gray-600">Records you keep for organisation. Cancellation and payment dates drive your reminders.</p>

      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 flex flex-wrap gap-3 text-sm">
        <select className="rounded border px-3 py-2" value={filters.budget_id} onChange={(e) => setFilters({ ...filters, budget_id: e.target.value })}>
          <option value="">All trips</option>
          {budgets.map((b) => <option key={b.id} value={b.id}>{b.title}</option>)}
        </select>
        <select className="rounded border px-3 py-2" value={filters.type} onChange={(e) => setFilters({ ...filters, type: e.target.value })}>
          <option value="">Flights and stays</option><option value="flight">Flights</option><option value="stay">Stays</option>
        </select>
        <select className="rounded border px-3 py-2" value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
          <option value="">Any status</option><option>planned</option><option>confirmed</option><option>cancelled</option>
        </select>
      </div>

      {bookings.length === 0 ? (
        <p className="mt-4 text-gray-600">No bookings yet. Save one from Flights or Stays, or add it below.</p>
      ) : (
        <ul className="mt-4 divide-y rounded-lg bg-white shadow">
          {bookings.map((b) => {
            const pending = edits[b.id] ?? {}
            return (
              <li key={b.id} className="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
                <div>
                  <p className="font-semibold">
                    {b.type === 'flight' ? '✈️' : '🏨'} {b.title}
                    <span className={`ml-2 rounded px-2 py-0.5 text-xs ${STATUS_STYLE[b.status]}`}>{b.status}</span>
                  </p>
                  <p className="text-sm text-gray-600">
                    {dayTime(b.start_at)}{b.end_at ? ` → ${dayTime(b.end_at)}` : ''}
                    {b.provider ? ` · ${b.provider}` : ''}
                    {b.budget ? ` · ${b.budget.title}` : ''}
                  </p>
                  <p className="text-sm text-gray-500">
                    {b.cancellation_deadline ? `Free cancellation until ${dayTime(b.cancellation_deadline)}. ` : ''}
                    {b.payment_due_at ? `Pay by ${dayTime(b.payment_due_at)}.` : ''}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  <input
                    className="w-32 rounded border px-2 py-1"
                    placeholder="Confirmation"
                    aria-label="Confirmation number"
                    value={pending.confirmation_number ?? b.confirmation_number ?? ''}
                    onChange={(e) => edit(b, 'confirmation_number', e.target.value)}
                  />
                  <select
                    className="rounded border px-2 py-1"
                    aria-label="Status"
                    value={pending.status ?? b.status}
                    onChange={(e) => edit(b, 'status', e.target.value)}
                  >
                    <option>planned</option><option>confirmed</option><option>cancelled</option>
                  </select>
                  <span className="w-20 text-right font-semibold">{b.price !== null ? money(b.price) : '—'}</span>
                  <button className="rounded bg-blue-600 px-3 py-1 font-semibold text-white" onClick={() => save(b)}>Save</button>
                  <button className="text-red-700" onClick={() => remove(b.id)}>Delete</button>
                </div>
              </li>
            )
          })}
        </ul>
      )}

      <h2 className="mt-8 text-2xl font-semibold">Add a booking by hand</h2>
      <form onSubmit={add} className="mt-3 grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-4">
        <label className="text-sm">Type
          <select className={inputClass} value={form.type} onChange={field('type')}><option value="stay">Stay</option><option value="flight">Flight</option></select>
        </label>
        <label className="text-sm">Trip
          <select className={inputClass} value={form.budget_id} onChange={field('budget_id')}>
            <option value="">No trip</option>
            {budgets.map((b) => <option key={b.id} value={b.id}>{b.title}</option>)}
          </select>
        </label>
        <label className="text-sm">Title<input className={inputClass} value={form.title} onChange={field('title')} required /></label>
        <label className="text-sm">Provider<input className={inputClass} value={form.provider} onChange={field('provider')} /></label>
        <label className="text-sm">Start<input className={inputClass} type="datetime-local" value={form.start_at} onChange={field('start_at')} required /></label>
        <label className="text-sm">End<input className={inputClass} type="datetime-local" value={form.end_at} onChange={field('end_at')} /></label>
        <label className="text-sm">Confirmation no.<input className={inputClass} value={form.confirmation_number} onChange={field('confirmation_number')} /></label>
        <label className="text-sm">Status
          <select className={inputClass} value={form.status} onChange={field('status')}><option>planned</option><option>confirmed</option></select>
        </label>
        <label className="text-sm">Free cancellation until<input className={inputClass} type="datetime-local" value={form.cancellation_deadline} onChange={field('cancellation_deadline')} /></label>
        <label className="text-sm">Payment due<input className={inputClass} type="datetime-local" value={form.payment_due_at} onChange={field('payment_due_at')} /></label>
        <label className="text-sm">Price (USD)<input className={inputClass} type="number" min="0" step="0.01" value={form.price} onChange={field('price')} /></label>
        <div className="flex items-end">
          <button className="w-full rounded bg-blue-600 px-4 py-2 font-semibold text-white">Save booking</button>
        </div>
      </form>
    </div>
  )
}
