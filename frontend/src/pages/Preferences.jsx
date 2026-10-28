import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { BUDGET_LEVELS, PACES, THEMES } from '../constants'

export default function Preferences() {
  const navigate = useNavigate()
  const [interests, setInterests] = useState([])
  const [pace, setPace] = useState('moderate')
  const [budget, setBudget] = useState('moderate')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    let ignore = false

    api
      .get('/preferences')
      .then((res) => {
        if (ignore) return
        const current = Object.entries(res.data.interest_weights)
          .filter(([, weight]) => weight > 0)
          .map(([tag]) => tag)
        setInterests(current)
        setPace(res.data.pace)
        setBudget(res.data.budget_band)
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
  }, [])

  function toggle(tag) {
    setInterests((current) =>
      current.includes(tag) ? current.filter((t) => t !== tag) : [...current, tag],
    )
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      await api.put('/preferences', {
        interests,
        pace,
        budget_band: budget,
      })
      navigate('/recommendations')
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSubmitting(false)
    }
  }

  function renderChoices(name, options, value, onChange) {
    return (
      <div className="flex flex-wrap gap-3">
        {options.map((option) => (
          <label key={option} className="flex items-center gap-2 capitalize">
            <input
              type="radio"
              name={name}
              value={option}
              checked={value === option}
              onChange={() => onChange(option)}
            />
            {option}
          </label>
        ))}
      </div>
    )
  }

  if (loading) return <p className="p-6 text-gray-600">Loading...</p>

  return (
    <div className="mx-auto max-w-2xl p-6">
      <Link className="text-blue-600" to="/">&larr; Back to dashboard</Link>
      <h1 className="mt-4 text-3xl font-bold">Your travel interests</h1>
      <p className="mt-2 text-gray-600">
        Pick what you enjoy. We use this to suggest places you are likely to like.
      </p>

      <form onSubmit={handleSubmit} className="mt-6 space-y-6 rounded-lg bg-white p-6 shadow">
        {error && <p className="rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}

        <div>
          <h2 className="mb-2 font-semibold">Interests</h2>
          <div className="flex flex-wrap gap-2">
            {THEMES.map((tag) => {
              const on = interests.includes(tag)
              return (
                <button
                  key={tag}
                  type="button"
                  aria-pressed={on}
                  onClick={() => toggle(tag)}
                  className={`rounded-full border px-3 py-1 text-sm capitalize ${
                    on ? 'border-blue-600 bg-blue-600 text-white' : 'bg-white text-gray-700'
                  }`}
                >
                  {tag}
                </button>
              )
            })}
          </div>
        </div>

        <div>
          <h2 className="mb-2 font-semibold">Travel pace</h2>
          {renderChoices('pace', PACES, pace, setPace)}
        </div>

        <div>
          <h2 className="mb-2 font-semibold">Budget</h2>
          {renderChoices('budget', BUDGET_LEVELS, budget, setBudget)}
        </div>

        <p className="text-sm text-gray-500">
          Saving starts your interests over and replaces anything we learned from your earlier feedback.
        </p>

        <button
          className="w-full rounded bg-blue-600 p-2 font-semibold text-white disabled:opacity-50"
          disabled={submitting || interests.length === 0}
        >
          {submitting ? 'Saving...' : 'Save and see recommendations'}
        </button>
      </form>
    </div>
  )
}