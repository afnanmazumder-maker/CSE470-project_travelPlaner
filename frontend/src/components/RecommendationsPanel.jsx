import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../api'

export default function RecommendationsPanel({ destinationId = null, limit = 10 }) {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [message, setMessage] = useState(null)
  const [saved, setSaved] = useState([])
  const [pending, setPending] = useState([])
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')

    const params = { limit }
    if (destinationId) params.destination_id = destinationId

    api
      .get('/recommendations', { params })
      .then((res) => {
        if (ignore) return
        setData(res.data)
        setSaved([])
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [destinationId, limit, reloadKey])

  async function sendFeedback(item, action) {
    setMessage(null)
    setPending((current) => [...current, item.attraction_id])

    try {
      await api.post(`/attractions/${item.attraction_id}/feedback`, { action })

      if (action === 'save') {
        setSaved((current) => [...current, item.attraction_id])
        setMessage({ kind: 'info', text: "Noted. We'll suggest more places like this." })
      } else {
        setData((current) => ({
          ...current,
          recommendations: current.recommendations.filter(
            (r) => r.attraction_id !== item.attraction_id,
          ),
        }))
        setMessage({ kind: 'info', text: "Got it. We'll suggest fewer places like this." })
      }
    } catch (err) {
      const unavailable = err.response?.status === 503
      setMessage({
        kind: 'error',
        text: unavailable
          ? 'The recommendation service is unavailable right now. Please try again later.'
          : errorMessage(err),
      })
    } finally {
      setPending((current) => current.filter((id) => id !== item.attraction_id))
    }
  }

  if (!data && loading) {
    return <p className="text-gray-600">Loading recommendations...</p>
  }

  return (
    <div>
      {error && <p className="mb-3 rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}

      {data && (
        <>
          {data.ai_service_available === false && (
            <p className="mb-3 rounded bg-amber-50 p-2 text-sm text-amber-800">
              Personalised suggestions are unavailable right now, so we are showing popular places instead.
            </p>
          )}

          {data.ai_service_available && data.fallback_used && (
            <p className="mb-3 rounded bg-blue-50 p-2 text-sm text-blue-800">
              Showing popular places. <Link className="underline" to="/preferences">Choose your interests</Link> to
              get suggestions that fit you.
            </p>
          )}

          {message && (
            <p
              className={`mb-3 rounded p-2 text-sm ${
                message.kind === 'error' ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-800'
              }`}
            >
              {message.text}
            </p>
          )}

          <div className="mb-3 flex justify-end">
            <button
              className="rounded border px-3 py-1 text-sm disabled:opacity-50"
              disabled={loading}
              onClick={() => setReloadKey((key) => key + 1)}
            >
              {loading ? 'Refreshing...' : 'Refresh suggestions'}
            </button>
          </div>

          {data.recommendations.length === 0 ? (
            <p className="text-gray-600">No suggestions to show right now.</p>
          ) : (
            <ul className={`space-y-3 ${loading ? 'opacity-60' : ''}`}>
              {data.recommendations.map((r) => {
                const isSaved = saved.includes(r.attraction_id)
                const busy = pending.includes(r.attraction_id)

                return (
                  <li key={r.attraction_id} className="rounded-lg bg-white p-4 shadow">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <h3 className="font-semibold">{r.name}</h3>
                        {!destinationId && r.destination && (
                          <p className="text-sm text-gray-500">
                            {r.destination.name}, {r.destination.country}
                          </p>
                        )}
                      </div>
                      {!data.fallback_used && (
                        <span className="rounded bg-green-50 px-2 py-0.5 text-sm text-green-700">
                          {Math.round(r.score * 100)}% match
                        </span>
                      )}
                    </div>

                    <p className="mt-2 text-sm text-gray-700">{r.reason}</p>

                    <div className="mt-2 flex flex-wrap gap-1">
                      {r.tags.map((tag) => (
                        <span key={tag} className="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">
                          {tag}
                        </span>
                      ))}
                    </div>

                    <div className="mt-3 flex gap-2">
                      <button
                        className="rounded bg-blue-600 px-3 py-1 text-sm text-white disabled:opacity-50"
                        disabled={busy || isSaved}
                        onClick={() => sendFeedback(r, 'save')}
                      >
                        {isSaved ? 'Saved' : 'Save'}
                      </button>
                      <button
                        className="rounded border px-3 py-1 text-sm disabled:opacity-50"
                        disabled={busy}
                        onClick={() => sendFeedback(r, 'reject')}
                      >
                        Not interested
                      </button>
                    </div>
                  </li>
                )
              })}
            </ul>
          )}
        </>
      )}
    </div>
  )
}