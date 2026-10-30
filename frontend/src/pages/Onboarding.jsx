import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'
import { INTEREST_INFO, THEMES } from '../constants'

const MIN_PICKS = 3

export default function Onboarding() {
  const { user, onboarded, completeOnboarding } = useAuth()
  const [picked, setPicked] = useState([])
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  if (onboarded) {
    return <Navigate to="/" replace />
  }
  function toggle(tag) {
    setPicked((current) =>
      current.includes(tag) ? current.filter((t) => t !== tag) : [...current, tag],
    )
  }

  async function handleContinue() {
    setError('')
    setSubmitting(true)
    try {
      await api.put('/preferences', {
        interests: picked,
        pace: 'moderate',
        budget_band: 'moderate',
      })
      completeOnboarding()
    } catch (err) {
      setError(errorMessage(err))
      setSubmitting(false)
    }
  }
  async function handleSkip() {
    setError('')
    setSubmitting(true)
    try {
      await api.post('/preferences/skip')
      completeOnboarding()
    } catch (err) {
      setError(errorMessage(err))
      setSubmitting(false)
    }
  }
  const remaining = MIN_PICKS - picked.length
  const firstName = user.name.split(' ')[0]

  return (
    <div className="mx-auto max-w-4xl px-4 pt-8">
      <h1 className="text-3xl font-bold">Welcome, {firstName}!</h1>
      <p className="mt-2 text-lg text-gray-700">What kind of places do you love?</p>
      <p className="text-gray-500">
        Pick at least {MIN_PICKS}. We will use them to suggest places for you, and you can change them any time.
      </p>

      {error && <p className="mt-4 rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}

      <div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {THEMES.map((tag) => {
          const info = INTEREST_INFO[tag]
          const on = picked.includes(tag)

          return (
            <button
              key={tag}
              type="button"
              aria-pressed={on}
              onClick={() => toggle(tag)}
              className={`relative rounded-xl border-2 p-4 text-left transition ${
                on
                  ? 'border-blue-600 bg-blue-50'
                  : 'border-transparent bg-white shadow hover:shadow-md'
              }`}
            >
              {on && (
                <span className="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-sm text-white">
                  ✓
                </span>
              )}
              <div className="text-4xl">{info.emoji}</div>
              <div className="mt-3 font-semibold">{info.label}</div>
              <div className="text-sm text-gray-500">{info.blurb}</div>
            </button>
          )
        })}
      </div>

      <div className="sticky bottom-0 mt-8 flex items-center justify-between gap-4 border-t bg-white/95 py-4">
        <p className="text-sm text-gray-600">
          {remaining > 0
            ? `Pick ${remaining} more to continue`
            : `${picked.length} selected`}
        </p>
        <div className="flex items-center gap-4">
          <button
            className="text-sm text-gray-600 underline disabled:opacity-40"
            disabled={submitting}
            onClick={handleSkip}
          >
            Skip for now
          </button>
          <button
            className="rounded-full bg-blue-600 px-6 py-2 font-semibold text-white disabled:opacity-40"
            disabled={picked.length < MIN_PICKS || submitting}
            onClick={handleContinue}
          >
            {submitting ? 'Saving...' : 'Continue'}
          </button>
        </div>
      </div>
    </div>
  )
}