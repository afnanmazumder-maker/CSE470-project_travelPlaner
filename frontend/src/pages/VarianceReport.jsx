import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { money } from '../format'

const STATUS_STYLE = {
  within: 'text-green-700',
  over: 'text-amber-700',
  alert: 'text-red-700',
  unplanned: 'text-purple-700',
}

// Hides the site header and the buttons when the report is saved as PDF.
const PRINT_CSS = '@media print { header, .no-print { display: none !important; } body { background: white; } }'

function percent(value) {
  return value === null ? '—' : `${value > 0 ? '+' : ''}${value}%`
}

function CumulativeChart({ points }) {
  if (points.length < 2) return <p className="text-sm text-gray-600">Set the trip start date to see spending over time.</p>

  const width = 600
  const height = 200
  const max = Math.max(1, ...points.flatMap((p) => [p.planned, p.actual]))
  const line = (key) => points.map((p, i) => `${(i / (points.length - 1)) * width},${height - (p[key] / max) * height}`).join(' ')

  return (
    <svg viewBox={`0 0 ${width} ${height + 24}`} className="w-full" role="img" aria-label="Cumulative planned and actual spending">
      <polyline points={line('planned')} fill="none" stroke="#9ca3af" strokeWidth="3" strokeDasharray="8 6" />
      <polyline points={line('actual')} fill="none" stroke="#2563eb" strokeWidth="3" />
      {points.map((p, i) => (
        <text key={p.label} x={(i / (points.length - 1)) * width} y={height + 18} fontSize="11" textAnchor="middle" fill="#6b7280">
          {i === 0 || i === points.length - 1 || points.length < 10 ? p.label : ''}
        </text>
      ))}
    </svg>
  )
}

export default function VarianceReport() {
  const { id } = useParams()
  const [report, setReport] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get(`/budgets/${id}/variance-report`)
      .then((res) => setReport(res.data))
      .catch((err) => {
        const status = err.response?.status
        setError(status === 403 || status === 404 ? 'Budget not found.' : errorMessage(err))
      })
  }, [id])

  async function downloadCsv() {
    try {
      const res = await api.get(`/budgets/${id}/variance-report.csv`, { responseType: 'blob' })
      const link = document.createElement('a')
      link.href = URL.createObjectURL(res.data)
      link.download = `variance-report-${report.budget.title.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.csv`
      link.click()
      URL.revokeObjectURL(link.href)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  if (!report) return <p className="p-6 text-gray-600">{error || 'Loading...'}</p>

  const rows = [...report.categories.filter((c) => c.planned > 0 || c.actual > 0), ...(report.unplanned.count ? [report.unplanned] : [])]
  const max = Math.max(1, ...rows.flatMap((r) => [r.planned, r.actual]))

  return (
    <div className="mx-auto max-w-4xl p-6">
      <style>{PRINT_CSS}</style>
      <div className="no-print flex flex-wrap items-center justify-between gap-3">
        <Link className="text-blue-600" to={`/budgets/${id}/spending`}>&larr; Spending</Link>
        <div className="flex gap-2 text-sm">
          <button className="rounded bg-blue-600 px-3 py-1.5 font-semibold text-white" onClick={() => window.print()}>Download PDF</button>
          <button className="rounded border px-3 py-1.5" onClick={downloadCsv}>Download CSV</button>
        </div>
      </div>

      <h1 className="mt-4 text-3xl font-bold">Variance report: {report.budget.title}</h1>
      <p className="text-gray-600">
        {report.budget.destination?.name} · {report.budget.days} days
        {report.tracking.start_date ? ` · ${report.tracking.start_date} to ${report.tracking.end_date}` : ''} · alert threshold {report.tracking.alert_threshold_pct}% · USD
      </p>

      {error && <p className="no-print mt-4 rounded bg-red-50 p-2 text-red-700">{error}</p>}

      <div className="mt-6 grid gap-3 sm:grid-cols-4">
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Planned</p><p className="text-xl font-bold">{money(report.totals.planned)}</p></div>
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Actual</p><p className="text-xl font-bold">{money(report.totals.actual)}</p></div>
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Deviation</p><p className="text-xl font-bold">{money(report.totals.deviation)}</p></div>
        <div className="rounded-lg bg-white p-4 shadow"><p className="text-sm text-gray-500">Deviation %</p><p className="text-xl font-bold">{percent(report.totals.deviation_pct)}</p></div>
      </div>

      <section className="mt-6 rounded-lg bg-white p-4 shadow">
        <h2 className="text-xl font-semibold">Summary</h2>
        <ul className="mt-2 list-disc pl-5 text-sm">
          {report.insights.map((line) => <li key={line}>{line}</li>)}
        </ul>
      </section>

      <section className="mt-6 rounded-lg bg-white p-4 shadow">
        <h2 className="text-xl font-semibold">By category</h2>
        <ul className="mt-3 space-y-3">
          {rows.map((r) => (
            <li key={r.category} className="text-sm">
              <div className="flex justify-between">
                <span className="font-medium">{r.name}</span>
                <span>{money(r.actual)} / {money(r.planned)} <span className={`font-semibold ${STATUS_STYLE[r.status]}`}>{percent(r.deviation_pct)} {r.status}</span></span>
              </div>
              <div className="mt-1 h-2 rounded bg-gray-100"><div className="h-2 rounded bg-gray-400" style={{ width: `${(r.planned / max) * 100}%` }} /></div>
              <div className="mt-0.5 h-2 rounded bg-gray-100"><div className="h-2 rounded bg-blue-600" style={{ width: `${Math.min(100, (r.actual / max) * 100)}%` }} /></div>
            </li>
          ))}
        </ul>
      </section>

      <section className="mt-6 rounded-lg bg-white p-4 shadow">
        <h2 className="text-xl font-semibold">Cumulative spend</h2>
        <p className="text-xs text-gray-500">Dashed grey: planned. Blue: actual.</p>
        <CumulativeChart points={report.cumulative} />
      </section>

      <div className="mt-6 grid gap-6 sm:grid-cols-2">
        <section className="rounded-lg bg-white p-4 shadow">
          <h2 className="text-xl font-semibold">Currencies used</h2>
          <table className="mt-2 w-full text-sm">
            <thead className="text-left text-xs uppercase text-gray-500"><tr><th>Currency</th><th className="text-right">Paid</th><th className="text-right">USD</th></tr></thead>
            <tbody className="divide-y">
              {report.by_currency.map((c) => (
                <tr key={c.currency}><td className="py-1">{c.currency} ({c.count})</td><td className="text-right">{c.original_total.toLocaleString('en-US')}</td><td className="text-right">{money(c.converted_total)}</td></tr>
              ))}
            </tbody>
          </table>
        </section>
        <section className="rounded-lg bg-white p-4 shadow">
          <h2 className="text-xl font-semibold">Top expenses</h2>
          <ul className="mt-2 divide-y text-sm">
            {report.top_expenses.map((e) => (
              <li key={e.id} className="flex justify-between py-1"><span>{e.description || e.category_name} <span className="text-gray-500">{e.spent_on}</span></span><span className="font-semibold">{money(e.amount)}</span></li>
            ))}
          </ul>
        </section>
      </div>

      <p className="mt-6 text-xs text-gray-500">Converted with daily ECB reference rates from Frankfurter; each expense keeps the rate it used. Generated {new Date(report.generated_at).toLocaleString('en-GB')}.</p>
    </div>
  )
}
