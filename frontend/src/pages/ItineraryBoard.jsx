import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import CollaboratorsPanel from '../components/CollaboratorsPanel'
import FloatingNotice from '../components/FloatingNotice'
import { getEcho, watchConnection } from '../echo'
import { money } from '../format'
import { clock, day } from '../travelFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'
const ICONS = { flight: '✈️', stay: '🏨', activity: '🎟️' }
const STATUS_STYLE = { planned: 'bg-amber-50 text-amber-800', confirmed: 'bg-green-50 text-green-800', cancelled: 'bg-red-50 text-red-700' }
const EMPTY_ITEM = { title: '', start_at: '', end_at: '', price: '', notes: '' }
const POLL_MS = 15000

function blankToNull(data) {
  return Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v]))
}

function timeAgo(iso) {
  return new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

// R16: the group trip's shared itinerary. Items are the bookings saved to the trip;
// members vote and comment on them, and every change reaches everyone with the
// board open straight away (Laravel Reverb), or within 15 seconds without it.
export default function ItineraryBoard() {
  const { id } = useParams()
  const [board, setBoard] = useState(null)
  const [activity, setActivity] = useState([])
  const [version, setVersion] = useState(0)
  const [live, setLive] = useState(false)
  const [blocked, setBlocked] = useState(false)
  const [form, setForm] = useState(EMPTY_ITEM)
  const [drafts, setDrafts] = useState({})
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const refresh = useCallback(() => setVersion((v) => v + 1), [])

  useEffect(() => {
    let ignore = false
    Promise.all([api.get(`/budgets/${id}/board`), api.get(`/budgets/${id}/activity`)])
      .then(([boardRes, activityRes]) => {
        if (ignore) return
        setBoard(boardRes.data)
        setActivity(activityRes.data)
      })
      .catch((err) => {
        if (ignore) return
        setBlocked(err.response?.status === 403 || err.response?.status === 404)
        setError(errorMessage(err))
      })
    return () => {
      ignore = true
    }
  }, [id, version])

  useEffect(() => {
    const echo = getEcho()
    if (!echo) return undefined
    echo.private(`trips.${id}`).listen('.board.updated', refresh)
    return () => echo.leave(`trips.${id}`)
  }, [id, refresh])

  useEffect(() => watchConnection(setLive), [])

  useEffect(() => {
    if (live || blocked) return undefined
    const timer = setInterval(refresh, POLL_MS)
    return () => clearInterval(timer)
  }, [live, blocked, refresh])

  async function run(action, message) {
    setError('')
    try {
      await action()
      if (message) setNotice(message)
      refresh()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  function addItem(e) {
    e.preventDefault()
    run(async () => {
      await api.post('/bookings', blankToNull({ ...form, type: 'activity', status: 'planned', budget_id: Number(id) }))
      setForm(EMPTY_ITEM)
    }, `Added ${form.title} to the itinerary.`)
  }

  function vote(item, value) {
    run(() => api.put(`/budgets/${id}/items/${item.id}/vote`, { value: item.votes.mine === value ? 0 : value }))
  }

  function setStatus(item, status) {
    const fields = ['type', 'title', 'provider', 'confirmation_number', 'start_at', 'end_at', 'price', 'party_size', 'notes']
    const data = Object.fromEntries(fields.map((f) => [f, item[f] ?? '']))
    run(() => api.put(`/bookings/${item.id}`, blankToNull({ ...data, status, budget_id: Number(id) })))
  }

  function removeItem(item) {
    if (!window.confirm(`Remove "${item.title}" from the trip?`)) return
    run(() => api.delete(`/bookings/${item.id}`), `Removed ${item.title}.`)
  }

  function addComment(e, item) {
    e.preventDefault()
    run(async () => {
      await api.post(`/budgets/${id}/items/${item.id}/comments`, { body: drafts[item.id] ?? '' })
      setDrafts({ ...drafts, [item.id]: '' })
    })
  }

  function deleteComment(comment) {
    run(() => api.delete(`/comments/${comment.id}`))
  }

  if (!board) {
    return (
      <div className="mx-auto max-w-6xl p-6">
        <p className="text-gray-600">{blocked ? error : 'Loading the board...'}</p>
        {blocked && <Link className="mt-3 inline-block text-blue-700" to="/trips">Back to group trips</Link>}
      </div>
    )
  }

  const days = Object.entries(
    board.items.reduce((groups, item) => {
      const key = item.start_at?.slice(0, 10) ?? 'undated'
      return { ...groups, [key]: [...(groups[key] ?? []), item] }
    }, {}),
  )

  return (
    <div className="mx-auto max-w-6xl p-6">
      <Link className="text-sm text-blue-700" to="/trips">← Group trips</Link>
      <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">{board.trip.title}</h1>
          <p className="text-gray-600">
            {board.trip.destination ? `${board.trip.destination.name}, ${board.trip.destination.country} · ` : ''}
            {board.trip.days} days · {board.members_count} {board.members_count === 1 ? 'person' : 'people'} · you are {board.my_role}
          </p>
        </div>
        <span className={`flex items-center gap-2 rounded-full px-3 py-1 text-sm ${live ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
          <span className={`h-2 w-2 rounded-full ${live ? 'bg-green-600' : 'bg-gray-400'}`} />
          {live ? 'Live' : 'Refreshes every 15 s'}
        </span>
      </div>

      <FloatingNotice notice={notice} error={error} setNotice={setNotice} setError={setError} />

      <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          {board.can_edit && (
            <form onSubmit={addItem} className="grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2">
              <h2 className="text-lg font-semibold sm:col-span-2">Add an activity</h2>
              <label className="text-sm sm:col-span-2">What<input className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="e.g. Snorkelling at Amed" required /></label>
              <label className="text-sm">Starts<input className={inputClass} type="datetime-local" value={form.start_at} onChange={(e) => setForm({ ...form, start_at: e.target.value })} required /></label>
              <label className="text-sm">Ends<input className={inputClass} type="datetime-local" value={form.end_at} onChange={(e) => setForm({ ...form, end_at: e.target.value })} /></label>
              <label className="text-sm">Price (USD)<input className={inputClass} type="number" min="0" step="0.01" value={form.price} onChange={(e) => setForm({ ...form, price: e.target.value })} /></label>
              <label className="text-sm">Notes<input className={inputClass} value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></label>
              <div className="flex flex-wrap items-center justify-between gap-2 sm:col-span-2">
                <p className="text-xs text-gray-500">
                  Flights and stays: use <Link className="text-blue-700" to="/flights">Flights</Link> or <Link className="text-blue-700" to="/stays">Stays</Link> and save the booking to this trip.
                </p>
                <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Add to itinerary</button>
              </div>
            </form>
          )}

          {board.items.length === 0 && (
            <p className="rounded-lg bg-white p-4 text-gray-600 shadow">
              Nothing on the itinerary yet.{board.can_edit ? ' Add an activity, or save a flight or stay to this trip.' : ''}
            </p>
          )}

          {days.map(([date, items]) => (
            <section key={date}>
              <h2 className="mb-2 font-semibold text-gray-700">{date === 'undated' ? 'No date' : day(date, { weekday: 'long', day: 'numeric', month: 'long' })}</h2>
              <ul className="space-y-3">
                {items.map((item) => (
                  <li key={item.id} className="rounded-lg bg-white p-4 shadow">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold">{ICONS[item.type]} {item.title}</p>
                        <p className="text-sm text-gray-600">
                          {clock(item.start_at)}{item.end_at ? ` – ${item.end_at.slice(0, 10) === item.start_at?.slice(0, 10) ? clock(item.end_at) : `${day(item.end_at)} ${clock(item.end_at)}`}` : ''}
                          {item.provider ? ` · ${item.provider}` : ''}
                          {item.price !== null ? ` · ${money(item.price)}` : ''}
                          {item.added_by ? ` · added by ${item.added_by.name}` : ''}
                        </p>
                        {item.notes && <p className="text-sm text-gray-500">{item.notes}</p>}
                      </div>
                      <div className="flex items-center gap-2">
                        {item.can_edit ? (
                          <select className={`rounded px-2 py-1 text-sm ${STATUS_STYLE[item.status]}`} value={item.status} onChange={(e) => setStatus(item, e.target.value)} aria-label={`Status of ${item.title}`}>
                            <option value="planned">planned</option><option value="confirmed">confirmed</option><option value="cancelled">cancelled</option>
                          </select>
                        ) : (
                          <span className={`rounded px-2 py-1 text-sm ${STATUS_STYLE[item.status]}`}>{item.status}</span>
                        )}
                        {item.can_edit && <button className="text-sm text-red-700" onClick={() => removeItem(item)}>Remove</button>}
                      </div>
                    </div>

                    <div className="mt-3 flex items-center gap-2 text-sm">
                      <button
                        className={`rounded border px-2 py-1 ${item.votes.mine === 1 ? 'border-green-600 bg-green-50 text-green-800' : ''}`}
                        disabled={!board.can_edit}
                        onClick={() => vote(item, 1)}
                        aria-label={`Vote for ${item.title}`}
                      >
                        👍 {item.votes.up}
                      </button>
                      <button
                        className={`rounded border px-2 py-1 ${item.votes.mine === -1 ? 'border-red-600 bg-red-50 text-red-700' : ''}`}
                        disabled={!board.can_edit}
                        onClick={() => vote(item, -1)}
                        aria-label={`Vote against ${item.title}`}
                      >
                        👎 {item.votes.down}
                      </button>
                      <span className="text-gray-500">{item.comments.length} {item.comments.length === 1 ? 'comment' : 'comments'}</span>
                    </div>

                    {item.comments.length > 0 && (
                      <ul className="mt-3 space-y-2 border-t pt-3 text-sm">
                        {item.comments.map((c) => (
                          <li key={c.id} className="flex items-start justify-between gap-2">
                            <p><span className="font-medium">{c.user?.name ?? 'Someone'}:</span> {c.body} <span className="text-xs text-gray-400">{timeAgo(c.created_at)}</span></p>
                            {c.can_delete && <button className="text-xs text-red-700" onClick={() => deleteComment(c)}>Delete</button>}
                          </li>
                        ))}
                      </ul>
                    )}

                    {board.can_edit && (
                      <form onSubmit={(e) => addComment(e, item)} className="mt-3 flex gap-2">
                        <input
                          className="flex-1 rounded border px-3 py-1.5 text-sm"
                          placeholder="Add a comment"
                          maxLength={1000}
                          value={drafts[item.id] ?? ''}
                          onChange={(e) => setDrafts({ ...drafts, [item.id]: e.target.value })}
                          aria-label={`Comment on ${item.title}`}
                        />
                        <button className="rounded border px-3 py-1.5 text-sm" disabled={!(drafts[item.id] ?? '').trim()}>Post</button>
                      </form>
                    )}
                  </li>
                ))}
              </ul>
            </section>
          ))}
        </div>

        <aside className="space-y-6">
          <CollaboratorsPanel tripId={id} version={version} onChanged={refresh} setNotice={setNotice} setError={setError} />

          <section className="rounded-lg bg-white p-4 shadow">
            <h2 className="text-lg font-semibold">Activity</h2>
            {activity.length === 0 && <p className="text-sm text-gray-600">No changes yet.</p>}
            <ul className="mt-2 space-y-2 text-sm">
              {activity.map((log) => (
                <li key={log.id}>
                  <span className="font-medium">{log.user?.name ?? 'Someone'}</span> {log.description}
                  <span className="block text-xs text-gray-400">{timeAgo(log.created_at)}</span>
                </li>
              ))}
            </ul>
          </section>
        </aside>
      </div>
    </div>
  )
}
