import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'

// R6: visa warning on a trip (an R7 budget), using the traveler's saved passport.
export default function VisaNotice({ budgetId }) {
  const [info, setInfo] = useState(null)

  useEffect(() => {
    let ignore = false
    api
      .get(`/budgets/${budgetId}/visa`)
      .then((res) => {
        if (!ignore) setInfo(res.data)
      })
      .catch(() => {
        if (!ignore) setInfo(null)
      })
    return () => {
      ignore = true
    }
  }, [budgetId])

  if (!info) return null

  const destination = info.destination_code ? `?destination=${info.destination_code}` : ''

  if (!info.passport_set) {
    return (
      <p className="mt-4 rounded bg-blue-50 p-2 text-sm text-blue-800">
        Do you need a visa for this trip?{' '}
        <Link className="font-semibold underline" to={`/visa${destination}`}>Add your passport to check</Link>
      </p>
    )
  }

  const result = info.result
  if (!result?.found || result.own_country) return null

  const link = `/visa?passport=${result.passport.code}&destination=${result.destination.code}`

  if (!result.needs_action) {
    return (
      <p className="mt-4 rounded bg-green-50 p-2 text-sm text-green-800">
        {result.summary} <Link className="underline" to={link}>Details</Link>
      </p>
    )
  }

  return (
    <div
      role="alert"
      className={`mt-4 rounded p-3 text-sm ${result.requirement === 'e_visa' || result.requirement === 'eta' ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700'}`}
    >
      <p className="font-semibold">Visa needed: {result.label}</p>
      <p className="mt-1">{result.summary}</p>
      <Link className="mt-1 inline-block font-semibold underline" to={link}>See documents and the official link →</Link>
    </div>
  )
}
