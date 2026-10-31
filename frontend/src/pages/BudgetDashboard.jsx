import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { BUDGET_CATEGORIES } from '../constants'
import { money } from '../format'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

function Bar({ percent }) {
  const color = percent > 100 ? 'bg-red-500' : percent >= 90 ? 'bg-amber-500' : 'bg-green-500'

  return (
    <div className="h-2 overflow-hidden rounded bg-gray-200">
      <div className={`h-2 ${color}`} style={{ width: `${Math.min(percent, 100)}%` }} />
    </div>
  )
}

export default function BudgetDashboard() {
  const { id } = useParams()
  const navigate = useNavigate()
  const [budget, setBudget] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [expenseError, setExpenseError] = useState('')
  const [expense, setExpense] = useState({ category: 'food', description: '', amount: '', spent_on: '' })
  const [target, setTarget] = useState('')
  const [suggestions, setSuggestions] = useState(null)
  const [suggestionError, setSuggestionError] = useState('')

  useEffect(() => {
    let ignore = false
    setLoading(true)
    setError('')

    api
      .get(`/budgets/${id}`)
      .then((res) => {
        if (!ignore) setBudget(res.data)
      })
      .catch((err) => {
        if (ignore) return
        const status = err.response?.status
        setError(status === 403 || status === 404 ? 'Budget not found.' : errorMessage(err))
      })
      .finally(() => {
        if (!ignore) setLoading(false)
      })

    return () => {
      ignore = true
    }
  }, [id])

  async function addExpense(e) {
    e.preventDefault()
    setExpenseError('')

    try {
      const res = await api.post(`/budgets/${id}/expenses`, {
        category: expense.category,
        description: expense.description || null,
        amount: Number(expense.amount),
        spent_on: expense.spent_on || null,
      })
      setBudget(res.data.budget)
      setExpense({ ...expense, description: '', amount: '' })
    } catch (err) {
      setExpenseError(errorMessage(err))
    }
  }

  async function removeExpense(expenseId) {
    try {
      const res = await api.delete(`/budgets/${id}/expenses/${expenseId}`)
      setBudget(res.data)
    } catch (err) {
      setExpenseError(errorMessage(err))
    }
  }

  async function loadSuggestions(e) {
    e.preventDefault()
    setSuggestionError('')
    setSuggestions(null)

    try {
      const res = await api.get(`/budgets/${id}/suggestions`, {
        params: target === '' ? {} : { target: Number(target) },
      })
      setSuggestions(res.data)
    } catch (err) {
      setSuggestionError(errorMessage(err))
    }
  }

  async function removeBudget() {
    if (!window.confirm('Delete this budget and all its expenses?')) return

    try {
      await api.delete(`/budgets/${id}`)
      navigate('/budgets')
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  if (loading) return <p className="p-6 text-gray-600">Loading...</p>

  if (error) {
    return (
      <div className="mx-auto max-w-3xl p-6">
        <p className="rounded bg-red-50 p-2 text-red-700">{error}</p>
        <Link className="mt-4 inline-block text-blue-600" to="/budgets">Back to budgets</Link>
      </div>
    )
  }

  const totalPercent = budget.planned_total > 0 ? Math.round((budget.spent_total / budget.planned_total) * 100) : 0

  return (
    <div className="mx-auto max-w-3xl p-6">
      <Link className="text-blue-600" to="/budgets">&larr; All budgets</Link>

      <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">{budget.title}</h1>
          <p className="text-gray-600">
            {budget.destination.name}, {budget.destination.country} · {budget.days} days ·{' '}
            {budget.travelers} travelers · {budget.comfort}
          </p>
        </div>
        <button className="rounded border px-3 py-1 text-sm text-red-700" onClick={removeBudget}>
          Delete budget
        </button>
      </div>

      <div className="mt-6 rounded-lg bg-white p-4 shadow">
        <div className="flex flex-wrap justify-between gap-2">
          <p>
            Spent <span className="text-2xl font-bold">{money(budget.spent_total)}</span> of{' '}
            {money(budget.planned_total)} planned
          </p>
          {budget.limit_amount !== null && (
            <p className="text-gray-600">Limit {money(budget.limit_amount)}</p>
          )}
        </div>
        <div className="mt-3">
          <Bar percent={totalPercent} />
        </div>
      </div>

      {budget.alerts.length > 0 && (
        <ul className="mt-4 space-y-2">
          {budget.alerts.map((alert, index) => (
            <li
              key={index}
              role="alert"
              className={`rounded p-2 text-sm ${
                alert.level === 'danger' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'
              }`}
            >
              {alert.message}
            </li>
          ))}
        </ul>
      )}

      <h2 className="mt-8 text-2xl font-semibold">Planned vs actual</h2>
      <ul className="mt-3 space-y-4 rounded-lg bg-white p-4 shadow">
        {budget.categories.map((c) => (
          <li key={c.category}>
            <div className="flex justify-between text-sm">
              <span className="font-medium">{BUDGET_CATEGORIES[c.category]}</span>
              <span className="text-gray-600">{money(c.spent)} / {money(c.planned)}</span>
            </div>
            <div className="mt-1">
              <Bar percent={c.percent} />
            </div>
          </li>
        ))}
      </ul>

      <h2 className="mt-8 text-2xl font-semibold">Cheaper swaps</h2>
      <form onSubmit={loadSuggestions} className="mt-3 rounded-lg bg-white p-4 shadow">
        <p className="text-sm text-gray-600">
          Find the cheapest changes that keep as much comfort as possible. Leave the target empty to use your limit.
        </p>
        <div className="mt-3 flex flex-wrap items-end gap-3">
          <label className="text-sm">
            Target total
            <input
              className={inputClass}
              type="number"
              min="1"
              step="any"
              value={target}
              onChange={(e) => setTarget(e.target.value)}
            />
          </label>
          <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Suggest swaps</button>
        </div>

        {suggestionError && <p className="mt-3 rounded bg-red-50 p-2 text-red-700">{suggestionError}</p>}

        {suggestions && (
          <div className="mt-4 text-sm">
            {!suggestions.achievable && (
              <p className="rounded bg-amber-50 p-2 text-amber-800">
                Even the cheapest options total {money(suggestions.new_total)}, which is above{' '}
                {money(suggestions.limit)}.
              </p>
            )}
            {suggestions.achievable && suggestions.swaps.length === 0 && (
              <p className="rounded bg-green-50 p-2 text-green-800">
                Your plan already fits within {money(suggestions.limit)}.
              </p>
            )}
            {suggestions.swaps.length > 0 && (
              <>
                <ul className="mt-2 divide-y">
                  {suggestions.swaps.map((s) => (
                    <li key={s.category} className="flex justify-between py-1.5">
                      <span>
                        {BUDGET_CATEGORIES[s.category]}: {s.from} to {s.to}
                      </span>
                      <span className="font-medium text-green-700">saves {money(s.saves)}</span>
                    </li>
                  ))}
                </ul>
                <p className="mt-2 font-semibold">
                  New total {money(suggestions.new_total)} (saves {money(suggestions.savings)})
                </p>
              </>
            )}
          </div>
        )}
      </form>

      <h2 className="mt-8 text-2xl font-semibold">Expenses</h2>
      <form onSubmit={addExpense} className="mt-3 rounded-lg bg-white p-4 shadow">
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            Category
            <select
              className={inputClass}
              value={expense.category}
              onChange={(e) => setExpense({ ...expense, category: e.target.value })}
            >
              {Object.entries(BUDGET_CATEGORIES).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            Amount
            <input
              className={inputClass}
              type="number"
              min="0.01"
              step="0.01"
              value={expense.amount}
              onChange={(e) => setExpense({ ...expense, amount: e.target.value })}
              required
            />
          </label>
          <label className="text-sm">
            Description
            <input
              className={inputClass}
              maxLength={120}
              value={expense.description}
              onChange={(e) => setExpense({ ...expense, description: e.target.value })}
            />
          </label>
          <label className="text-sm">
            Date
            <input
              className={inputClass}
              type="date"
              value={expense.spent_on}
              onChange={(e) => setExpense({ ...expense, spent_on: e.target.value })}
            />
          </label>
        </div>
        {expenseError && <p className="mt-3 rounded bg-red-50 p-2 text-red-700">{expenseError}</p>}
        <button className="mt-3 rounded bg-blue-600 px-4 py-2 font-semibold text-white">Add expense</button>
      </form>

      {budget.expenses.length === 0 ? (
        <p className="mt-3 text-gray-600">No expenses recorded yet.</p>
      ) : (
        <ul className="mt-3 divide-y rounded-lg bg-white shadow">
          {budget.expenses.map((x) => (
            <li key={x.id} className="flex items-center justify-between gap-3 p-3 text-sm">
              <div>
                <p className="font-medium">
                  {BUDGET_CATEGORIES[x.category]}
                  {x.description ? `: ${x.description}` : ''}
                </p>
                <p className="text-gray-500">{x.spent_on}</p>
              </div>
              <div className="flex items-center gap-3">
                <span className="font-semibold">{money(x.amount)}</span>
                <button className="text-red-700" onClick={() => removeExpense(x.id)}>Remove</button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
