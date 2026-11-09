import { useEffect, useState } from 'react'
import api, { errorMessage } from '../api'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

export default function NotificationSettings() {
  const [types, setTypes] = useState([])
  const [form, setForm] = useState(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/notification-settings')
      .then((res) => {
        setTypes(res.data.types)
        setForm(res.data.preferences)
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  function setChannel(type, channel, value) {
    setForm({ ...form, channels: { ...form.channels, [type]: { ...form.channels[type], [channel]: value } } })
  }

  async function save(e) {
    e.preventDefault()
    setError('')
    setNotice('')
    try {
      await api.put('/notification-settings', {
        channels: form.channels,
        daily_digest: form.daily_digest,
        digest_time: form.digest_time,
        checkin_lead_hours: Number(form.checkin_lead_hours),
        deadline_lead_days: Number(form.deadline_lead_days),
      })
      setNotice('Notification settings saved.')
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  if (!form) return <p className="p-6 text-gray-600">{error || 'Loading...'}</p>

  return (
    <div className="mx-auto max-w-3xl p-6">
      <h1 className="text-3xl font-bold">Notification settings</h1>
      <p className="text-gray-600">Choose how each reminder reaches you.</p>

      {notice && <p className="mt-4 rounded bg-green-50 p-2 text-green-800">{notice}</p>}
      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <form onSubmit={save} className="mt-6 space-y-6">
        <table className="w-full rounded-lg bg-white text-sm shadow">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th className="p-3">Reminder</th><th className="p-3 text-center">In-app</th><th className="p-3 text-center">Email</th></tr>
          </thead>
          <tbody className="divide-y">
            {types.map((t) => (
              <tr key={t.key}>
                <td className="p-3">{t.label}</td>
                {['in_app', 'email'].map((channel) => (
                  <td key={channel} className="p-3 text-center">
                    <input
                      type="checkbox"
                      aria-label={`${t.label} ${channel === 'in_app' ? 'in-app' : 'email'}`}
                      checked={Boolean(form.channels[t.key]?.[channel])}
                      onChange={(e) => setChannel(t.key, channel, e.target.checked)}
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>

        <div className="grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2">
          <label className="flex items-center gap-2 text-sm sm:col-span-2">
            <input type="checkbox" checked={form.daily_digest} onChange={(e) => setForm({ ...form, daily_digest: e.target.checked })} />
            Send a daily digest on travel days
          </label>
          <label className="text-sm">Digest time<input className={inputClass} type="time" value={form.digest_time} onChange={(e) => setForm({ ...form, digest_time: e.target.value })} /></label>
          <label className="text-sm">Check-in reminder (hours before departure)
            <input className={inputClass} type="number" min="1" max="168" value={form.checkin_lead_hours} onChange={(e) => setForm({ ...form, checkin_lead_hours: e.target.value })} />
          </label>
          <label className="text-sm">Payment and cancellation reminders (days ahead)
            <input className={inputClass} type="number" min="1" max="30" value={form.deadline_lead_days} onChange={(e) => setForm({ ...form, deadline_lead_days: e.target.value })} />
          </label>
        </div>

        <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Save settings</button>
      </form>
    </div>
  )
}
