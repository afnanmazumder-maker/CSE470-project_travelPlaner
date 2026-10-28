import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { MONTHS } from '../constants'

export default function Destination() {
  const { id } = useParams()
  const [destination, setDestination] = useState(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')

    api
      .get(`/destinations/${id}`)
      .then((res) => {
        if (!ignore) setDestination(res.data)
      })
      .catch((err) => {
        if (ignore) return
        setError(err.response?.status === 404 ? 'Destination not found.' : errorMessage(err))
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [id])

  if (loading) return <p className="p-6 text-gray-600">Loading...</p>

  if (error) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        <p className="rounded bg-red-50 p-2 text-red-700">{error}</p>
        <Link className="mt-4 inline-block text-blue-600" to="/explore">Back to explore</Link>
      </div>
    )
  }

  const d = destination

  return (
    <div className="mx-auto max-w-3xl p-6">
      <Link className="text-blue-600" to="/explore">&larr; Back to explore</Link>

      <h1 className="mt-4 text-4xl font-bold">{d.name}</h1>
      <p className="text-gray-500">{d.country} · {d.region}</p>
      <p className="mt-4 text-gray-800">{d.overview}</p>

      <dl className="mt-6 grid gap-4 rounded-lg bg-white p-4 shadow sm:grid-cols-2">
        <div>
          <dt className="text-sm text-gray-500">Budget level</dt>
          <dd className="font-medium">{d.budget_level} (cost index {d.cost_index}/100)</dd>
        </div>
        <div>
          <dt className="text-sm text-gray-500">Climate</dt>
          <dd className="font-medium">{d.climate}</dd>
        </div>
        <div>
          <dt className="text-sm text-gray-500">Safety rating</dt>
          <dd className="font-medium">{d.safety_rating} out of 5</dd>
        </div>
        <div>
          <dt className="text-sm text-gray-500">Best months</dt>
          <dd className="font-medium">{d.best_months.map((m) => MONTHS[m - 1]).join(', ')}</dd>
        </div>
        {d.cuisine && (
          <div className="sm:col-span-2">
            <dt className="text-sm text-gray-500">Cuisine</dt>
            <dd className="font-medium">{d.cuisine}</dd>
          </div>
        )}
      </dl>

      <h2 className="mt-8 text-2xl font-semibold">Attractions</h2>
      <ul className="mt-3 space-y-3">
        {d.attractions.map((a) => (
          <li key={a.id} className="rounded-lg bg-white p-4 shadow">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold">{a.name}</h3>
              <span className="text-sm text-gray-500">popularity {Math.round(a.popularity * 100)}%</span>
            </div>
            <div className="mt-2 flex flex-wrap gap-1">
              {a.tags.map((t) => (
                <span key={t} className="rounded bg-blue-50 px-2 py-0.5 text-xs text-blue-700">{t}</span>
              ))}
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}