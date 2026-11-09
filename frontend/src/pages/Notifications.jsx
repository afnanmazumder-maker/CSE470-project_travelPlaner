import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../api'

const ICONS = {
  checkin_open: '🛫', flight_24h: '✈️', payment_due: '💳', cancellation_deadline: '⏰',
  daily_digest: '🗓️', price_drop: '📉', budget_alert: '📊',
}

export default function Notifications() {
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    let ignore = false
    api
      .get('/notifications')
      .then((res) => {
        if (!ignore) setData(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })
    return () => {
      ignore = true
    }
  }, [reload])

  async function markRead(id) {
    try {
      await api.post(`/notifications/${id}/read`)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function markAllRead() {
    try {
      await api.post('/notifications/read-all')
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <div className="mx-auto max-w-3xl p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Notifications</h1>
          <p className="text-gray-600">{data ? `${data.unread_count} unread` : 'Loading...'}</p>
        </div>
        <div className="flex gap-2 text-sm">
          <Link className="rounded border px-3 py-1" to="/timeline">Timeline</Link>
          <Link className="rounded border px-3 py-1" to="/notification-settings">Settings</Link>
          <button className="rounded border px-3 py-1" onClick={markAllRead}>Mark all read</button>
        </div>
      </div>

      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      {data?.notifications.length === 0 && <p className="mt-6 text-gray-600">No notifications yet.</p>}
      <ul className="mt-6 divide-y rounded-lg bg-white shadow">
        {data?.notifications.map((n) => (
          <li key={n.id} className={`flex items-start justify-between gap-3 p-4 ${n.read_at ? 'opacity-60' : ''}`}>
            <div className="flex gap-3">
              <span className="text-2xl" aria-hidden="true">{ICONS[n.type] ?? '🔔'}</span>
              <div>
                <p className="font-semibold">{n.read_at ? '' : '● '}{n.title}</p>
                <p className="whitespace-pre-line text-sm text-gray-700">{n.body}</p>
                <p className="mt-1 text-xs text-gray-500">
                  {new Date(n.created_at).toLocaleString('en-GB')}
                  {n.budget ? ` · ${n.budget.title}` : ''}
                  {n.emailed_at ? ' · emailed' : ''}
                </p>
              </div>
            </div>
            {!n.read_at && <button className="text-sm text-blue-600" onClick={() => markRead(n.id)}>Mark read</button>}
          </li>
        ))}
      </ul>
    </div>
  )
}
