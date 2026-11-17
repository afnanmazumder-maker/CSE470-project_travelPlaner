import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'
import FloatingNotice from '../components/FloatingNotice'
import { requirementTone, visaDate } from '../visaFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

const EMPTY_RULE = {
  passport_code: 'BD',
  destination_code: '',
  requirement: 'visa_required',
  max_stay_days: '',
  passport_validity_months: '',
  conditions: '',
  documents: '',
  fee_note: '',
  processing_note: '',
  transit_notes: '',
  official_url: '',
  last_verified_at: '',
}

// Turns a table row into form values; documents are edited one per line.
function toForm(rule) {
  return {
    ...EMPTY_RULE,
    ...Object.fromEntries(Object.keys(EMPTY_RULE).map((key) => [key, rule[key] ?? ''])),
    documents: (rule.required_documents || []).join('\n'),
  }
}

// Sends empty fields as null and the document lines as a list.
function toPayload(form) {
  const { documents, ...fields } = form
  const payload = Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, value === '' ? null : value]))
  const lines = documents.split('\n').map((line) => line.trim()).filter(Boolean)
  return { ...payload, required_documents: lines.length ? lines : null }
}

export default function VisaAdmin() {
  const { user } = useAuth()
  const [countries, setCountries] = useState([])
  const [filters, setFilters] = useState({ passport: 'BD', destination: '', source: '', stale: false })
  const [page, setPage] = useState(1)
  const [data, setData] = useState(null)
  const [form, setForm] = useState(EMPTY_RULE)
  const [editingId, setEditingId] = useState(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [reload, setReload] = useState(0)

  useEffect(() => {
    api.get('/visa/countries').then((res) => setCountries(res.data.countries)).catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    if (user?.role !== 'admin') return
    let ignore = false
    const params = {
      passport: filters.passport || undefined,
      destination: filters.destination || undefined,
      source: filters.source || undefined,
      stale: filters.stale ? 1 : undefined,
      page,
    }

    api
      .get('/admin/visa-requirements', { params })
      .then((res) => {
        if (!ignore) setData(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [user, filters, page, reload])

  if (user?.role !== 'admin') {
    return (
      <div className="mx-auto max-w-5xl p-6">
        <p className="rounded bg-red-50 p-2 text-red-700">Only administrators can manage visa data.</p>
        <Link className="mt-4 inline-block text-blue-600" to="/visa">← Visa checker</Link>
      </div>
    )
  }

  function filter(name) {
    return (e) => {
      setPage(1)
      setFilters({ ...filters, [name]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })
    }
  }

  function edit(rule) {
    setEditingId(rule.id)
    setForm(toForm(rule))
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })
  }

  function cancelEdit() {
    setEditingId(null)
    setForm(EMPTY_RULE)
  }

  async function save(e) {
    e.preventDefault()
    setError('')
    try {
      if (editingId) {
        await api.put(`/admin/visa-requirements/${editingId}`, toPayload(form))
        setNotice(`Updated ${form.passport_code} → ${form.destination_code}.`)
      } else {
        await api.post('/admin/visa-requirements', toPayload(form))
        setNotice(`Added ${form.passport_code} → ${form.destination_code}.`)
      }
      cancelEdit()
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function remove(rule) {
    if (!window.confirm(`Delete the rule for ${rule.passport_name} passports visiting ${rule.destination_name}?`)) return
    try {
      await api.delete(`/admin/visa-requirements/${rule.id}`)
      setNotice('Rule deleted.')
      setReload((n) => n + 1)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const countryOptions = countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)
  const field = (name) => (e) => setForm({ ...form, [name]: e.target.value })

  return (
    <div className="mx-auto max-w-6xl p-6">
      <Link className="text-blue-600" to="/visa">← Visa checker</Link>
      <h1 className="mt-2 text-3xl font-bold">Visa data</h1>
      <p className="text-gray-600">
        Rules you edit here are marked as admin-checked and are never overwritten by a dataset import.
      </p>

      <FloatingNotice notice={notice} error={error} setNotice={setNotice} setError={setError} />

      {data && (
        <div className="mt-4 grid gap-3 sm:grid-cols-4">
          {[
            ['All rules', data.counts.total],
            ['Checked on official sites', data.counts.official],
            ['Edited by admins', data.counts.admin],
            ['Stale (needs a re-check)', data.counts.stale],
          ].map(([label, count]) => (
            <div key={label} className="rounded-lg bg-white p-3 shadow">
              <p className="text-2xl font-bold">{count.toLocaleString()}</p>
              <p className="text-sm text-gray-600">{label}</p>
            </div>
          ))}
        </div>
      )}

      <div className="mt-6 flex flex-wrap items-end gap-3 text-sm">
        <label>Passport
          <select className="ml-1 rounded border px-3 py-2" value={filters.passport} onChange={filter('passport')}>
            <option value="">Any</option>
            {countryOptions}
          </select>
        </label>
        <label>Destination
          <select className="ml-1 rounded border px-3 py-2" value={filters.destination} onChange={filter('destination')}>
            <option value="">Any</option>
            {countryOptions}
          </select>
        </label>
        <label>Source
          <select className="ml-1 rounded border px-3 py-2" value={filters.source} onChange={filter('source')}>
            <option value="">Any</option>
            <option value="dataset">Dataset</option>
            <option value="official">Official</option>
            <option value="admin">Admin</option>
          </select>
        </label>
        <label className="flex items-center gap-1">
          <input type="checkbox" checked={filters.stale} onChange={filter('stale')} /> Stale only
        </label>
        {data && <span className="text-gray-600">{data.total} rules · page {data.current_page} of {data.last_page}</span>}
      </div>

      <div className="mt-4 overflow-x-auto rounded-lg bg-white shadow">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th className="p-3">Passport → destination</th><th className="p-3">Requirement</th><th className="p-3">Stay</th><th className="p-3">Source</th><th className="p-3">Last checked</th><th className="p-3" /></tr>
          </thead>
          <tbody className="divide-y">
            {data?.data.map((rule) => (
              <tr key={rule.id}>
                <td className="p-3">{rule.passport_name} → {rule.destination_name}</td>
                <td className="p-3">
                  <span className={`rounded px-2 py-0.5 text-xs font-semibold ring-1 ${requirementTone(rule.requirement)}`}>{rule.label}</span>
                </td>
                <td className="p-3">{rule.max_stay_days ? `${rule.max_stay_days} days` : '—'}</td>
                <td className="p-3 capitalize">{rule.source}</td>
                <td className="p-3">
                  {visaDate(rule.last_verified_at)}
                  {rule.is_stale && <span className="ml-2 rounded bg-amber-50 px-1 text-xs text-amber-800">stale</span>}
                </td>
                <td className="whitespace-nowrap p-3">
                  <button className="rounded bg-blue-600 px-3 py-1 font-semibold text-white" onClick={() => edit(rule)}>Edit</button>
                  <button className="ml-2 text-red-700" onClick={() => remove(rule)}>Delete</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="mt-3 flex gap-2 text-sm">
        <button className="rounded border px-3 py-1" disabled={page <= 1} onClick={() => setPage(page - 1)}>← Previous</button>
        <button className="rounded border px-3 py-1" disabled={!data || page >= data.last_page} onClick={() => setPage(page + 1)}>Next →</button>
      </div>

      <h2 className="mt-8 text-2xl font-semibold">{editingId ? `Edit ${form.passport_code} → ${form.destination_code}` : 'Add a rule'}</h2>
      <form onSubmit={save} className="mt-3 grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-4">
        <label className="text-sm">Passport
          <select className={inputClass} value={form.passport_code} onChange={field('passport_code')} required>{countryOptions}</select>
        </label>
        <label className="text-sm">Destination
          <select className={inputClass} value={form.destination_code} onChange={field('destination_code')} required>
            <option value="">Choose</option>
            {countryOptions}
          </select>
        </label>
        <label className="text-sm">Requirement
          <select className={inputClass} value={form.requirement} onChange={field('requirement')}>
            {Object.entries(data?.requirements || {}).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
          </select>
        </label>
        <label className="text-sm">Last checked on
          <input className={inputClass} type="date" value={form.last_verified_at} onChange={field('last_verified_at')} />
        </label>
        <label className="text-sm">Max stay (days)
          <input className={inputClass} type="number" min="1" value={form.max_stay_days} onChange={field('max_stay_days')} />
        </label>
        <label className="text-sm">Passport valid for (months)
          <input className={inputClass} type="number" min="0" max="24" value={form.passport_validity_months} onChange={field('passport_validity_months')} />
        </label>
        <label className="text-sm">Fee
          <input className={inputClass} value={form.fee_note} onChange={field('fee_note')} placeholder="e.g. USD 35" />
        </label>
        <label className="text-sm">Processing time
          <input className={inputClass} value={form.processing_note} onChange={field('processing_note')} placeholder="e.g. 5 working days" />
        </label>
        <label className="text-sm sm:col-span-2">Conditions
          <textarea className={inputClass} rows="3" value={form.conditions} onChange={field('conditions')} />
        </label>
        <label className="text-sm sm:col-span-2">Documents (one per line)
          <textarea className={inputClass} rows="3" value={form.documents} onChange={field('documents')} />
        </label>
        <label className="text-sm sm:col-span-2">Transit notes
          <textarea className={inputClass} rows="2" value={form.transit_notes} onChange={field('transit_notes')} />
        </label>
        <label className="text-sm sm:col-span-2">Official source link
          <input className={inputClass} type="url" value={form.official_url} onChange={field('official_url')} placeholder="https://" />
        </label>
        <div className="flex items-end gap-3 sm:col-span-2 lg:col-span-4">
          <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white">{editingId ? 'Save changes' : 'Add rule'}</button>
          {editingId && <button type="button" className="rounded border px-4 py-2" onClick={cancelEdit}>Cancel</button>}
        </div>
      </form>
    </div>
  )
}
