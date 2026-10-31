import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { BUDGET_CATEGORIES, BUDGET_LEVELS } from '../constants'
import { money } from '../format'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

export default function Budgets() {
  const navigate = useNavigate()
  const [budgets, setBudgets] = useState([])
  const [destinations, setDestinations] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [estimate, setEstimate] = useState(null)
  const [form, setForm] = useState({
    title: '',
    destination_id: '',
    days: '5',
    travelers: '2',
    comfort: 'moderate',
    limit_amount: '',
  })

  useEffect(() => {
    Promise.all([api.get('/budgets'), api.get('/destinations', { params: { per_page: 50 } })])
      .then(([budgetRes, destinationRes]) => {
        setBudgets(budgetRes.data)
        setDestinations(destinationRes.data.data)
      })
      .catch((err) => setError(errorMessage(err)))
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    if (!form.destination_id) {
      setEstimate(null)
      return
    }

    let ignore = false

    api
      .post('/budgets/estimate', {
        destination_id: Number(form.destination_id),
        days: Number(form.days),
        travelers: Number(form.travelers),
        comfort: form.comfort,
      })
      .then((res) => {
        if (!ignore) setEstimate(res.data)
      })
      .catch(() => {
        if (!ignore) setEstimate(null)
      })

    return () => {
      ignore = true
    }
  }, [form.destination_id, form.days, form.travelers, form.comfort])

  function update(field) {
    return (e) => setForm({ ...form, [field]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSaving(true)

    try {
      const res = await api.post('/budgets', {
        title: form.title,
        destination_id: Number(form.destination_id),
        days: Number(form.days),
        travelers: Number(form.travelers),
        comfort: form.comfort,
        limit_amount: form.limit_amount === '' ? null : Number(form.limit_amount),
      })
      navigate(`/budgets/${res.data.id}`)
    } catch (err) {
      setError(errorMessage(err))
      setSaving(false)
    }
  }

  if (loading) return <p className="p-6 text-gray-600">Loading...</p>

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="text-3xl font-bold">Trip budgets</h1>
      <p className="mt-1 text-gray-600">
        Estimate what a trip will cost, then track what you actually spend. Amounts are in US dollars.
      </p>

      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 grid gap-6 md:grid-cols-2">
        <form onSubmit={handleSubmit} className="space-y-3 rounded-lg bg-white p-4 shadow">
          <h2 className="text-xl font-semibold">New budget</h2>

          <label className="block text-sm">
            Title
            <input className={inputClass} value={form.title} onChange={update('title')} maxLength={100} required />
          </label>

          <label className="block text-sm">
            Destination
            <select className={inputClass} value={form.destination_id} onChange={update('destination_id')} required>
              <option value="">Choose a destination</option>
              {destinations.map((d) => (
                <option key={d.id} value={d.id}>{d.name}, {d.country}</option>
              ))}
            </select>
          </label>

          <div className="grid grid-cols-2 gap-3">
            <label className="block text-sm">
              Days
              <input className={inputClass} type="number" min="1" max="90" value={form.days} onChange={update('days')} required />
            </label>
            <label className="block text-sm">
              Travelers
              <input className={inputClass} type="number" min="1" max="20" value={form.travelers} onChange={update('travelers')} required />
            </label>
          </div>

          <label className="block text-sm">
            Comfort level
            <select className={inputClass} value={form.comfort} onChange={update('comfort')}>
              {BUDGET_LEVELS.map((level) => (
                <option key={level} value={level}>{level}</option>
              ))}
            </select>
          </label>

          <label className="block text-sm">
            Spending limit (optional)
            <input className={inputClass} type="number" min="1" step="any" value={form.limit_amount} onChange={update('limit_amount')} />
          </label>

          <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-60" disabled={saving}>
            {saving ? 'Saving...' : 'Create budget'}
          </button>
        </form>

        <div className="rounded-lg bg-white p-4 shadow">
          <h2 className="text-xl font-semibold">Cost estimate</h2>
          {estimate ? (
            <>
              <ul className="mt-3 divide-y text-sm">
                {Object.entries(estimate.planned).map(([category, amount]) => (
                  <li key={category} className="flex justify-between py-1.5">
                    <span>{BUDGET_CATEGORIES[category]}</span>
                    <span className="font-medium">{money(amount)}</span>
                  </li>
                ))}
              </ul>
              <p className="mt-3 flex justify-between border-t pt-3 text-lg font-bold">
                <span>Total</span>
                <span>{money(estimate.total)}</span>
              </p>
            </>
          ) : (
            <p className="mt-3 text-gray-600">Choose a destination to see an estimate.</p>
          )}
        </div>
      </div>

      <h2 className="mt-10 text-2xl font-semibold">Your budgets</h2>
      {budgets.length === 0 ? (
        <p className="mt-3 rounded-lg bg-white p-4 text-gray-700 shadow">You have no budgets yet.</p>
      ) : (
        <ul className="mt-3 grid gap-3 sm:grid-cols-2">
          {budgets.map((b) => (
            <li key={b.id}>
              <Link to={`/budgets/${b.id}`} className="block rounded-lg bg-white p-4 shadow hover:shadow-md">
                <h3 className="font-semibold">{b.title}</h3>
                <p className="text-sm text-gray-500">
                  {b.destination.name} · {b.days} days · {b.travelers} travelers · {b.comfort}
                </p>
                <p className="mt-2 text-sm">
                  Spent {money(b.spent_total)} of {money(b.planned_total)} planned
                </p>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
