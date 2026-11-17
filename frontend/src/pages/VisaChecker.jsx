import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'
import FloatingNotice from '../components/FloatingNotice'
import { requirementTone, sourceText, visaDate } from '../visaFormat'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

// The six destinations travelers can plan in Voyenta, for one-click checks.
const APP_DESTINATIONS = ['FR', 'IT', 'JP', 'TR', 'TH', 'ID']

function Detail({ label, children }) {
  if (children === null || children === undefined || children === '') return null
  return (
    <div>
      <dt className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</dt>
      <dd className="mt-1">{children}</dd>
    </div>
  )
}

function VisaResult({ result }) {
  if (!result.found || result.own_country) {
    return (
      <div className="rounded-lg bg-white p-4 shadow">
        <p>{result.summary}</p>
      </div>
    )
  }

  return (
    <article className="rounded-lg bg-white p-4 shadow">
      <p className={`inline-block rounded px-3 py-1 text-sm font-semibold ring-1 ${requirementTone(result.requirement)}`}>
        {result.label}
      </p>
      <p className="mt-3 text-lg">{result.summary}</p>

      <dl className="mt-4 grid gap-4 sm:grid-cols-2">
        <Detail label="Maximum stay">{result.max_stay_days ? `${result.max_stay_days} days` : null}</Detail>
        <Detail label="Passport validity">
          {result.passport_validity_months ? `At least ${result.passport_validity_months} months` : null}
        </Detail>
        <Detail label="Fee">{result.fee_note}</Detail>
        <Detail label="Processing time">{result.processing_note}</Detail>
      </dl>

      {result.conditions && (
        <div className="mt-4">
          <h3 className="font-semibold">Conditions</h3>
          <p className="mt-1 text-gray-700">{result.conditions}</p>
        </div>
      )}

      {result.required_documents.length > 0 && (
        <div className="mt-4">
          <h3 className="font-semibold">Documents to prepare</h3>
          <ul className="mt-1 list-disc space-y-1 pl-5 text-gray-700">
            {result.required_documents.map((doc) => <li key={doc}>{doc}</li>)}
          </ul>
        </div>
      )}

      {result.transit_notes && (
        <div className="mt-4">
          <h3 className="font-semibold">Transit</h3>
          <p className="mt-1 text-gray-700">{result.transit_notes}</p>
        </div>
      )}

      {result.official_url && (
        <a
          className="mt-4 inline-block rounded bg-blue-600 px-4 py-2 font-semibold text-white"
          href={result.official_url}
          target="_blank"
          rel="noopener noreferrer"
        >
          Official source ↗
        </a>
      )}

      <p className="mt-4 text-sm text-gray-600">{sourceText(result)}</p>
      {result.is_stale && (
        <p className="mt-2 rounded bg-amber-50 p-2 text-sm text-amber-800">
          This information may be out of date. Check the official source before you apply.
        </p>
      )}
    </article>
  )
}

export default function VisaChecker() {
  const { user } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [countries, setCountries] = useState([])
  const [datasetDate, setDatasetDate] = useState(null)
  const [savedPassport, setSavedPassport] = useState(null)
  const [passport, setPassport] = useState(searchParams.get('passport') || '')
  const [destination, setDestination] = useState(searchParams.get('destination') || '')
  const [transit, setTransit] = useState('')
  const [result, setResult] = useState(null)
  const [checking, setChecking] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api
      .get('/visa/countries')
      .then((res) => {
        setCountries(res.data.countries)
        setDatasetDate(res.data.dataset_updated_on)
        setPassport((current) => current || res.data.default_passport)
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    if (!user) return
    api
      .get('/visa/passport')
      .then((res) => {
        setSavedPassport(res.data.passport_country)
        if (res.data.passport_country && !searchParams.get('passport')) setPassport(res.data.passport_country)
      })
      .catch(() => {})
    // Runs on login change only; a passport in the link (from a trip warning) wins over the saved one.
  }, [user])

  useEffect(() => {
    if (!passport || !destination) {
      setResult(null)
      return
    }
    let ignore = false
    setChecking(true)
    setError('')

    api
      .get('/visa/check', { params: { passport, destination, transit: transit || undefined } })
      .then((res) => {
        if (!ignore) setResult(res.data)
      })
      .catch((err) => {
        if (!ignore) {
          setResult(null)
          setError(errorMessage(err))
        }
      })
      .finally(() => {
        if (!ignore) setChecking(false)
      })

    return () => {
      ignore = true
    }
  }, [passport, destination, transit])

  function choose(setter, key) {
    return (e) => {
      setter(e.target.value)
      if (key) {
        const next = new URLSearchParams(searchParams)
        next.set(key, e.target.value)
        setSearchParams(next, { replace: true })
      }
    }
  }

  async function savePassport() {
    try {
      const res = await api.put('/visa/passport', { passport_country: passport })
      setSavedPassport(res.data.passport_country)
      setNotice('Saved. Your trips will now show visa warnings for this passport.')
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  const countryOptions = countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)
  const nameOf = (code) => countries.find((c) => c.code === code)?.name || code

  return (
    <div className="mx-auto max-w-5xl p-6">
      <h1 className="text-3xl font-bold">Visa and entry requirements</h1>
      <p className="text-gray-600">Check what your passport needs before you book, with the documents to prepare and the official link.</p>

      <FloatingNotice notice={notice} error={error} setNotice={setNotice} setError={setError} />

      <div className="mt-6 grid gap-6 md:grid-cols-3">
        <section className="rounded-lg bg-white p-4 shadow md:col-span-1">
          <label className="block text-sm">
            My passport
            <select className={inputClass} value={passport} onChange={choose(setPassport, 'passport')}>
              {countryOptions}
            </select>
          </label>
          {user && passport && passport !== savedPassport && (
            <button className="mt-2 text-sm text-blue-600" onClick={savePassport}>
              Save {nameOf(passport)} as my passport
            </button>
          )}
          {user && passport && passport === savedPassport && (
            <p className="mt-2 text-sm text-gray-500">This is your saved passport.</p>
          )}

          <label className="mt-4 block text-sm">
            Destination
            <select className={inputClass} value={destination} onChange={choose(setDestination, 'destination')}>
              <option value="">Choose a country</option>
              {countryOptions}
            </select>
          </label>

          <div className="mt-2 flex flex-wrap gap-1">
            {APP_DESTINATIONS.map((code) => (
              <button
                key={code}
                className={`rounded-full border px-2 py-0.5 text-xs ${destination === code ? 'border-blue-600 text-blue-700' : 'text-gray-600'}`}
                onClick={() => choose(setDestination, 'destination')({ target: { value: code } })}
              >
                {nameOf(code)}
              </button>
            ))}
          </div>

          <label className="mt-4 block text-sm">
            Stopover country (optional)
            <select className={inputClass} value={transit} onChange={(e) => setTransit(e.target.value)}>
              <option value="">No stopover</option>
              {countryOptions}
            </select>
          </label>

          {!user && (
            <p className="mt-4 text-sm text-gray-600">
              <Link className="text-blue-600" to="/login">Log in</Link> to save your passport and get visa warnings on your trips.
            </p>
          )}
          {user?.role === 'admin' && (
            <Link className="mt-4 block text-sm text-blue-600" to="/admin/visa">Manage visa data →</Link>
          )}
        </section>

        <section className="space-y-4 md:col-span-2">
          {!destination && (
            <p className="rounded-lg bg-white p-4 text-gray-600 shadow">Choose a destination to see the entry requirements.</p>
          )}
          {checking && <p className="text-gray-600">Checking...</p>}
          {result && !checking && <VisaResult result={result} />}

          {result?.transit && !checking && (
            <div className={`rounded-lg p-4 ring-1 ${result.transit.may_need_visa ? 'bg-amber-50 text-amber-800 ring-amber-200' : 'bg-white ring-gray-200'}`}>
              <h3 className="font-semibold">Stopover in {result.transit.country.name}</h3>
              <p className="mt-1">{result.transit.message}</p>
              {result.transit.transit_notes && <p className="mt-1 text-sm">{result.transit.transit_notes}</p>}
            </div>
          )}

          {result && (
            <p className="text-sm text-gray-500">
              {result.disclaimer} Basic rules for all countries come from the Passport Index dataset
              {datasetDate ? ` (updated ${visaDate(datasetDate)})` : ''}.
            </p>
          )}
        </section>
      </div>
    </div>
  )
}
