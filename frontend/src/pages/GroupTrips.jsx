import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../api'

const ROLE_STYLE = { owner: 'bg-blue-50 text-blue-800', editor: 'bg-green-50 text-green-800', viewer: 'bg-gray-100 text-gray-700' }

// R16: the trips you own (your budgets) and the ones you were invited to.
export default function GroupTrips() {
  const [trips, setTrips] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/trips').then((res) => setTrips(res.data)).catch((err) => setError(errorMessage(err)))
  }, [])

  const owned = trips?.filter((t) => t.role === 'owner') ?? []
  const shared = trips?.filter((t) => t.role !== 'owner') ?? []

  function list(items) {
    return (
      <ul className="grid gap-3 sm:grid-cols-2">
        {items.map((t) => (
          <li key={t.id} className="flex flex-col justify-between gap-3 rounded-lg bg-white p-4 shadow">
            <div>
              <div className="flex items-start justify-between gap-2">
                <p className="font-semibold">{t.title}</p>
                <span className={`rounded px-2 py-0.5 text-xs font-semibold ${ROLE_STYLE[t.role]}`}>{t.role}</span>
              </div>
              <p className="text-sm text-gray-600">
                {t.destination ? `${t.destination.name}, ${t.destination.country} · ` : ''}{t.days} days
              </p>
              <p className="text-sm text-gray-500">
                {t.members_count} {t.members_count === 1 ? 'person' : 'people'}{t.role !== 'owner' ? ` · by ${t.owner}` : ''}
              </p>
            </div>
            <Link className="self-start rounded bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white" to={`/trips/${t.id}`}>Open board</Link>
          </li>
        ))}
      </ul>
    )
  }

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="text-3xl font-bold">Group trips</h1>
      <p className="text-gray-600">Plan one trip together: invite people, vote and comment on the itinerary, and see who changed what.</p>
      {error && <p className="mt-4 rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}
      {trips === null && !error && <p className="mt-6 text-gray-600">Loading...</p>}

      {trips && (
        <>
          <h2 className="mb-3 mt-6 text-xl font-semibold">Your trips</h2>
          {owned.length ? list(owned) : (
            <p className="text-gray-600">
              Each trip is a budget. <Link className="text-blue-700" to="/budgets">Create a budget</Link> first, then open its board to invite people.
            </p>
          )}

          <h2 className="mb-3 mt-8 text-xl font-semibold">Shared with you</h2>
          {shared.length ? list(shared) : <p className="text-gray-600">When someone invites you, the trip shows up here.</p>}
        </>
      )}
    </div>
  )
}
