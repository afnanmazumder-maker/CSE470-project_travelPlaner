// Formatting helpers for travel times. The API sends local wall-clock times
// such as "2026-12-05T10:30", so they are read as text, not converted.

export function clock(value) {
  return value ? value.slice(11, 16) : ''
}

export function day(value, options = { weekday: 'short', day: 'numeric', month: 'short' }) {
  if (!value) return ''
  return new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-GB', options)
}

export function dayTime(value) {
  return value ? `${day(value)}, ${clock(value)}` : ''
}

export function duration(minutes) {
  return `${Math.floor(minutes / 60)}h ${String(minutes % 60).padStart(2, '0')}m`
}

export function label(value) {
  return value ? value.replace(/_/g, ' ') : ''
}
