import { useEffect, useState } from 'react'
import api, { errorMessage } from '../api'
import { clock, day } from '../travelFormat'

const ICONS = { flight: '✈️', stay: '🏨', deadline: '⏰' }

export default function Timeline() {
  const [budgets, setBudgets] = useState([])
  const [tripId, setTripId] = useState('')
  const [days, setDays] = useState(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/budgets').then((res) => setBudgets(res.data)).catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    let ignore = false
    api
      .get(tripId ? `/budgets/${tripId}/timeline` : '/timeline')
      .then((res) => {
        if (!ignore) setDays(res.data.days)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })
    return () => {
      ignore = true
    }
  }, [tripId])

  async function runScan() {
    setError('')
    try {
      const res = await api.post('/reminders/run')
      const sent = res.data.created.length + res.data.price_drops.length
      setNotice(sent ? `Sent ${sent} new reminders. See Notifications.` : 'Nothing new: every reminder that is due was already sent.')
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <div className="mx-auto max-w-3xl p-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Trip timeline</h1>
          <p className="text-gray-600">Flights, stays and booking deadlines in one list. Reminders are sent from these dates.</p>
        </div>
        <div className="flex items-end gap-2 text-sm">
          <label>
            Trip
            <select className="mt-1 block rounded border px-3 py-2" value={tripId} onChange={(e) => setTripId(e.target.value)}>
              <option value="">All my bookings</option>
              {budgets.map((b) => <option key={b.id} value={b.id}>{b.title}</option>)}
            </select>
          </label>
          <button className="rounded bg-blue-600 px-3 py-2 font-semibold text-white" title="The scheduler also runs this every 15 minutes" onClick={runScan}>
            Run reminder scan now
          </button>
        </div>
      </div>

      {notice && <p className="mt-4 rounded bg-green-50 p-2 text-green-800">{notice}</p>}
      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      {days?.length === 0 && <p className="mt-6 text-gray-600">No bookings yet. Save flights and stays from Travel.</p>}
      <ol className="mt-6 space-y-4">
        {days?.map((d) => (
          <li key={d.date} className={`rounded-lg bg-white p-4 shadow ${d.is_today ? 'ring-2 ring-blue-500' : ''}`}>
            <p className="font-semibold">{day(d.date, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}{d.is_today ? ' · today' : ''}</p>
            <ul className="mt-2 space-y-2">
              {d.events.map((e, index) => (
                <li key={`${e.booking_id}-${index}`} className="flex gap-3 text-sm">
                  <span className="w-12 text-gray-500">{clock(e.starts_at)}</span>
                  <span aria-hidden="true">{ICONS[e.kind]}</span>
                  <div>
                    <p className="font-medium">{e.title}</p>
                    {e.subtitle && <p className="text-gray-500">{e.subtitle}</p>}
                    {e.kind === 'flight' && e.ends_at && <p className="text-gray-500">arrives {clock(e.ends_at)}</p>}
                  </div>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ol>
    </div>
  )
}
