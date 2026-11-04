import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { BUDGET_CATEGORIES } from '../constants'
import { money } from '../format'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'
const STATUS_STYLE = {
  within: 'bg-green-50 text-green-800',
  over: 'bg-amber-50 text-amber-800',
  alert: 'bg-red-50 text-red-700',
  unplanned: 'bg-purple-50 text-purple-800',
}
const BAR_COLOR = { within: 'bg-green-500', over: 'bg-amber-500', alert: 'bg-red-500', unplanned: 'bg-purple-500' }

function percent(value) {
  return value === null ? '—' : `${value > 0 ? '+' : ''}${value}%`
}

function Status({ status }) {
  return <span className={`rounded px-2 py-0.5 text-xs font-semibold ${STATUS_STYLE[status]}`}>{status}</span>
}

function PlanBars({ row, max }) {
  const width = (value) => `${Math.min(100, (value / max) * 100)}%`
  return (
    <div className="space-y-1">
      <div className="h-2 rounded bg-gray-100" title="Planned"><div className="h-2 rounded bg-gray-400" style={{ width: width(row.planned) }} /></div>
      <div className="h-2 rounded bg-gray-100" title="Actual"><div className={`h-2 rounded ${BAR_COLOR[row.status]}`} style={{ width: width(row.actual) }} /></div>
    </div>
  )
}

export default function SpendTracker() {
  const { id } = useParams()
  const [summary, setSummary] = useState(null)
  const [currencies, setCurrencies] = useState(['USD'])
  const [expenses, setExpenses] = useState([])
  const [tracking, setTracking] = useState({ start_date: '', alert_threshold_pct: '10' })
  const [expense, setExpense] = useState({ category: 'food', description: '', amount: '', currency: 'USD', spent_on: '' })
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    api.get('/currencies').then((res) => setCurrencies(res.data.currencies)).catch(() => {})
  }, [])

  useEffect(() => {
    let ignore = false

    Promise.all([api.get(`/budgets/${id}/deviation`), api.get(`/budgets/${id}/variance-report`)])
      .then(([deviationRes, reportRes]) => {
        if (ignore) return
        setSummary(deviationRes.data)
        setExpenses(reportRes.data.expenses)
        setTracking({
          start_date: deviationRes.data.tracking.start_date ?? '',
          alert_threshold_pct: String(deviationRes.data.tracking.alert_threshold_pct),
        })
      })
      .catch((err) => {
        if (ignore) return
        const status = err.response?.status
        setError(status === 403 || status === 404 ? 'Budget not found.' : errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [id, reload])

  async function saveTracking(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.put(`/budgets/${id}/tracking`, {
        start_date: tracking.start_date || null,
        alert_threshold_pct: Number(tracking.alert_threshold_pct),
      })
      setNotice(res.data.alerts.length ? `Saved. ${res.data.alerts.join('. ')}.` : 'Trip settings saved.')
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function addExpense(e) {
    e.preventDefault()
    setError('')
    try {
      const res = await api.post(`/budgets/${id}/expenses`, {
        category: expense.category,
        description: expense.description || null,
        amount: Number(expense.amount),
        currency: expense.currency,
        spent_on: expense.spent_on || null,
      })
      const saved = res.data.expense
      const converted = saved.currency === 'USD' ? '' : ` = ${money(saved.amount)} at ${saved.rate_used}`
      const alerts = res.data.deviation_alerts.length ? ` ⚠ ${res.data.deviation_alerts.join('. ')}` : ''
      setNotice(`Added ${saved.original_amount} ${saved.currency}${converted}.${alerts}`)
      setExpense({ ...expense, description: '', amount: '' })
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function removeExpense(expenseId) {
    try {
      await api.delete(`/budgets/${id}/expenses/${expenseId}`)
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  if (!summary) return <p className="p-6 text-gray-600">{error || 'Loading...'}</p>

  const totals = summary.totals
  const rows = [...summary.categories.filter((c) => c.planned > 0 || c.actual > 0), ...(summary.unplanned.count ? [summary.unplanned] : [])]
  const max = Math.max(1, ...rows.flatMap((r) => [r.planned, r.actual]))

  return (
    <div className="mx-auto max-w-4xl p-6">
      <Link className="text-blue-600" to={`/budgets/${id}`}>&larr; Budget</Link>
      <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Spending: {summary.budget.title}</h1>
          <p className="text-gray-600">Log what you pay in any currency; it is converted to USD with that day's ECB rate.</p>
        </div>
        <Link className="rounded border px-3 py-1 text-sm" to={`/budgets/${id}/report`}>Variance report</Link>
      </div>

      {notice && <p className="mt-4 rounded bg-green-50 p-2 text-green-800">{notice}</p>}
      {error && <p className="mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 grid gap-3 sm:grid-cols-4">
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Planned</p><p className="text-2xl font-bold">{money(totals.planned)}</p></div>
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Actual</p><p className="text-2xl font-bold">{money(totals.actual)}</p><p className="text-xs text-gray-500">{totals.expense_count} expenses</p></div>
        <div className="rounded-lg bg-white p-4 shadow">
          <p className="text-sm text-gray-500">Deviation</p>
          <p className={`text-2xl font-bold ${totals.deviation > 0 ? 'text-red-700' : 'text-green-700'}`}>{money(totals.deviation)}</p>
          <p className="text-xs">{percent(totals.deviation_pct)} <Status status={totals.status} /></p>
        </div>
        <div className="rounded-lg bg-white p-4 shadow">
          <p className="text-sm text-gray-500">Planned to date</p>
          <p className="text-2xl font-bold">{totals.planned_to_date === null ? '—' : money(totals.planned_to_date)}</p>
          <p className="text-xs text-gray-500">{summary.tracking.today_day_number === null ? 'set a start date' : `trip day ${summary.tracking.today_day_number}`}</p>
        </div>
      </div>

      <form onSubmit={saveTracking} className="mt-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow">
        <label className="text-sm">Trip start date
          <input className={inputClass} type="date" value={tracking.start_date} onChange={(e) => setTracking({ ...tracking, start_date: e.target.value })} />
        </label>
        <label className="text-sm">Alert when a category is over plan by (%)
          <input className={inputClass} type="number" min="1" max="200" value={tracking.alert_threshold_pct} onChange={(e) => setTracking({ ...tracking, alert_threshold_pct: e.target.value })} />
        </label>
        <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Save</button>
        {summary.tracking.start_date && <p className="text-sm text-gray-500">Trip: {summary.tracking.start_date} to {summary.tracking.end_date}</p>}
      </form>

      <h2 className="mt-8 text-2xl font-semibold">Add an expense</h2>
      <form onSubmit={addExpense} className="mt-3 grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-5">
        <label className="text-sm sm:col-span-2">Description
          <input className={inputClass} maxLength={120} value={expense.description} onChange={(e) => setExpense({ ...expense, description: e.target.value })} />
        </label>
        <label className="text-sm">Amount paid
          <input className={inputClass} type="number" min="0.01" step="0.01" required value={expense.amount} onChange={(e) => setExpense({ ...expense, amount: e.target.value })} />
        </label>
        <label className="text-sm">Currency
          <select className={inputClass} value={expense.currency} onChange={(e) => setExpense({ ...expense, currency: e.target.value })}>
            {currencies.map((c) => <option key={c}>{c}</option>)}
          </select>
        </label>
        <label className="text-sm">Date
          <input className={inputClass} type="date" value={expense.spent_on} onChange={(e) => setExpense({ ...expense, spent_on: e.target.value })} />
        </label>
        <label className="text-sm sm:col-span-2">Category
          <select className={inputClass} value={expense.category} onChange={(e) => setExpense({ ...expense, category: e.target.value })}>
            {Object.entries(BUDGET_CATEGORIES).map(([value, text]) => <option key={value} value={value}>{text}</option>)}
            <option value="other">Miscellaneous / Unplanned</option>
          </select>
        </label>
        <div className="flex items-end sm:col-span-3">
          <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">Add expense</button>
        </div>
      </form>

      <h2 className="mt-8 text-2xl font-semibold">Planned vs actual by category</h2>
      <div className="mt-3 overflow-x-auto rounded-lg bg-white shadow">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th className="p-3">Category</th><th className="w-40 p-3">Grey plan, colour actual</th><th className="p-3 text-right">Planned</th><th className="p-3 text-right">Actual</th><th className="p-3 text-right">Deviation</th><th className="p-3">Status</th></tr>
          </thead>
          <tbody className="divide-y">
            {rows.map((r) => (
              <tr key={r.category}>
                <td className="p-3 font-medium">{r.name}<p className="text-xs font-normal text-gray-500">{r.count} expenses</p></td>
                <td className="p-3"><PlanBars row={r} max={max} /></td>
                <td className="p-3 text-right">{money(r.planned)}</td>
                <td className="p-3 text-right">{money(r.actual)}</td>
                <td className={`p-3 text-right ${r.deviation > 0 ? 'text-red-700' : 'text-green-700'}`}>{money(r.deviation)} <span className="text-xs text-gray-500">{percent(r.deviation_pct)}</span></td>
                <td className="p-3"><Status status={r.status} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <h2 className="mt-8 text-2xl font-semibold">By trip day</h2>
      {summary.days.length === 0 ? (
        <p className="mt-3 text-gray-600">Set the trip start date above to match expenses to trip days.</p>
      ) : (
        <ul className="mt-3 divide-y rounded-lg bg-white shadow">
          {summary.days.map((d) => (
            <li key={d.day} className={`grid grid-cols-[110px_1fr_auto] items-center gap-3 p-3 text-sm ${d.day === summary.tracking.today_day_number ? 'bg-blue-50' : ''}`}>
              <span className="font-medium">{d.label}<span className="block text-xs text-gray-500">{d.date}</span></span>
              <PlanBars row={d} max={Math.max(1, ...summary.days.flatMap((x) => [x.planned, x.actual]))} />
              <span className="text-right">{money(d.actual)} / {money(d.planned)}<span className="block text-xs text-gray-500">{percent(d.deviation_pct)}</span></span>
            </li>
          ))}
        </ul>
      )}

      <h2 className="mt-8 text-2xl font-semibold">Expenses</h2>
      {expenses.length === 0 ? (
        <p className="mt-3 text-gray-600">No expenses recorded yet.</p>
      ) : (
        <ul className="mt-3 divide-y rounded-lg bg-white shadow">
          {expenses.map((x) => (
            <li key={x.id} className="flex items-center justify-between gap-3 p-3 text-sm">
              <div>
                <p className="font-medium">{x.category_name}{x.description ? `: ${x.description}` : ''}</p>
                <p className="text-gray-500">{x.spent_on}{x.day_label ? ` · ${x.day_label}` : ''}{x.currency !== 'USD' ? ` · ${x.original_amount} ${x.currency} at ${x.rate_used} (${x.rate_date})` : ''}</p>
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
