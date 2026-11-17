// R6: shared display helpers for the visa checker, the trip warning and the admin page.

const TONES = {
  visa_free: 'bg-green-50 text-green-800 ring-green-200',
  visa_on_arrival: 'bg-green-50 text-green-800 ring-green-200',
  eta: 'bg-amber-50 text-amber-800 ring-amber-200',
  e_visa: 'bg-amber-50 text-amber-800 ring-amber-200',
  visa_required: 'bg-red-50 text-red-700 ring-red-200',
  no_admission: 'bg-red-50 text-red-700 ring-red-200',
}

export function requirementTone(requirement) {
  return TONES[requirement] || 'bg-gray-50 text-gray-700 ring-gray-200'
}

export function visaDate(isoDate) {
  if (!isoDate) return 'unknown date'
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function sourceText(result) {
  if (result.source === 'official') return `Checked on the official site on ${visaDate(result.last_verified_at)}`
  if (result.source === 'admin') return `Updated by a Voyenta admin on ${visaDate(result.last_verified_at)}`
  return `From the Passport Index dataset, updated ${visaDate(result.last_verified_at)}. Details have not been checked by hand yet.`
}
