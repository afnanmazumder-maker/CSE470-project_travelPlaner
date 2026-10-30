import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import DestinationCard from '../components/DestinationCard'
import { BUDGET_LEVELS, MONTHS, REGIONS, THEMES } from '../constants'

const EMPTY_FILTERS = {
  q: '',
  region: '',
  budget_level: '',
  theme: '',
  month: '',
  min_safety: '',
}

export default function Explore() {
  const [searchParams] = useSearchParams()
  const [filters, setFilters] = useState({ ...EMPTY_FILTERS, q: searchParams.get('q') || '' })
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    let ignore = false

    const timer = setTimeout(async () => {
      const params = { page }
      for (const [key, value] of Object.entries(filters)) {
        if (value !== '') params[key] = value
      }

      setLoading(true)
      setError('')
      try {
        const res = await api.get('/destinations', { params })
        if (!ignore) setResult(res.data)
      } catch (err) {
        if (!ignore) setError(errorMessage(err))
      } finally {
        if (!ignore) setLoading(false)
      }
    }, 300)

    return () => {
      ignore = true
      clearTimeout(timer)
    }
  }, [filters, page])

  function handleChange(e) {
    setFilters({ ...filters, [e.target.name]: e.target.value })
    setPage(1)
  }

  function clearFilters() {
    setFilters(EMPTY_FILTERS)
    setPage(1)
  }

  const selectClass = 'rounded border bg-white p-2'

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="mb-4 text-3xl font-bold">Explore destinations</h1>

      <div className="mb-6 grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-3">
        <input
          className="rounded border p-2 sm:col-span-3"
          name="q"
          placeholder="Search by city, country or region"
          value={filters.q}
          onChange={handleChange}
        />
        <select className={selectClass} name="region" value={filters.region} onChange={handleChange}>
          <option value="">Any region</option>
          {REGIONS.map((r) => <option key={r} value={r}>{r}</option>)}
        </select>
        <select className={selectClass} name="budget_level" value={filters.budget_level} onChange={handleChange}>
          <option value="">Any budget</option>
          {BUDGET_LEVELS.map((b) => <option key={b} value={b}>{b}</option>)}
        </select>
        <select className={selectClass} name="theme" value={filters.theme} onChange={handleChange}>
          <option value="">Any theme</option>
          {THEMES.map((t) => <option key={t} value={t}>{t}</option>)}
        </select>
        <select className={selectClass} name="month" value={filters.month} onChange={handleChange}>
          <option value="">Any month</option>
          {MONTHS.map((m, i) => <option key={m} value={i + 1}>{m}</option>)}
        </select>
        <select className={selectClass} name="min_safety" value={filters.min_safety} onChange={handleChange}>
          <option value="">Any safety rating</option>
          {[2, 3, 4, 5].map((n) => <option key={n} value={n}>{n}+ out of 5</option>)}
        </select>
        <button className="rounded border p-2 text-gray-700" onClick={clearFilters}>
          Clear filters
        </button>
      </div>

      {error && <p className="mb-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}
      {loading && !result && <p className="text-gray-600">Loading...</p>}

      {result && (
        <>
          {result.data.length === 0 ? (
            <p className="text-gray-600">No destinations match those filters.</p>
          ) : (
            <div className={`grid gap-4 sm:grid-cols-2 lg:grid-cols-3 ${loading ? 'opacity-60' : ''}`}>
              {result.data.map((d) => (
                <DestinationCard key={d.id} destination={d} />
              ))}
            </div>
          )}

          {result.last_page > 1 && (
            <div className="mt-6 flex items-center justify-center gap-4">
              <button
                className="rounded border px-3 py-1 disabled:opacity-40"
                disabled={page <= 1}
                onClick={() => setPage(page - 1)}
              >
                Previous
              </button>
              <span className="text-sm">Page {result.current_page} of {result.last_page}</span>
              <button
                className="rounded border px-3 py-1 disabled:opacity-40"
                disabled={page >= result.last_page}
                onClick={() => setPage(page + 1)}
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}