import { useEffect, useRef, useState } from 'react'
import api, { errorMessage } from '../api'
import FloatingNotice from '../components/FloatingNotice'
import TravelTabs from '../components/TravelTabs'
import { money } from '../format'
import { label } from '../travelFormat'
import useLeaflet from '../useLeaflet'

const inputClass = 'mt-1 w-full rounded border px-3 py-2'

export default function StaySearch() {
  const L = useLeaflet()
  const mapElement = useRef(null)
  const map = useRef(null)
  const pins = useRef(null)
  const [destinations, setDestinations] = useState([])
  const [budgets, setBudgets] = useState([])
  const [options, setOptions] = useState(null)
  const [form, setForm] = useState({
    destination_id: '', check_in: '2026-12-01', check_out: '2026-12-05', min_price: '', max_price: '',
    min_rating: '', max_distance_km: '', sort: 'price', amenities: [], property_types: [],
  })
  const [bounds, setBounds] = useState(null)
  const [result, setResult] = useState(null)
  const [tripId, setTripId] = useState('')
  const [hovered, setHovered] = useState(null)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    Promise.all([api.get('/destinations', { params: { per_page: 50 } }), api.get('/stays/options'), api.get('/budgets')])
      .then(([destinationRes, optionRes, budgetRes]) => {
        setDestinations(destinationRes.data.data)
        setOptions(optionRes.data)
        setBudgets(budgetRes.data)
        const bangkok = destinationRes.data.data.find((d) => d.name === 'Bangkok') ?? destinationRes.data.data[0]
        if (bangkok) setForm((f) => ({ ...f, destination_id: String(bangkok.id) }))
      })
      .catch((err) => setError(errorMessage(err)))
  }, [])

  useEffect(() => {
    if (!form.destination_id && !bounds) return
    let ignore = false
    const params = Object.fromEntries(
      Object.entries({ ...form, ...(bounds ?? {}), destination_id: bounds ? '' : form.destination_id })
        .filter(([, v]) => v !== '' && !(Array.isArray(v) && !v.length)),
    )

    api
      .get('/stays/search', { params })
      .then((res) => {
        if (!ignore) setResult(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })

    return () => {
      ignore = true
    }
  }, [form, bounds])

  useEffect(() => {
    if (!L || !mapElement.current || map.current) return
    // The mouse wheel scrolls the page, not the map, until the traveler clicks into
    // the map; clicking elsewhere hands the wheel back to the page.
    const m = L.map(mapElement.current, { scrollWheelZoom: false }).setView([13.7563, 100.5018], 12)
    m.on('focus', () => m.scrollWheelZoom.enable())
    m.on('blur', () => m.scrollWheelZoom.disable())
    map.current = m
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map.current)
    pins.current = L.layerGroup().addTo(map.current)
  }, [L])

  useEffect(() => {
    if (!L || !map.current || !result) return
    pins.current.clearLayers()

    if (result.center) {
      L.circleMarker([result.center.lat, result.center.lng], { radius: 6, color: '#c2410c' })
        .addTo(pins.current)
        .bindTooltip(result.center.label)
    }

    result.results.forEach((stay) => {
      const active = stay.id === hovered ? 'bg-blue-600 text-white' : 'bg-white text-gray-900'
      const icon = L.divIcon({
        className: '',
        html: `<span class="whitespace-nowrap rounded-full border-2 border-blue-600 px-2 py-0.5 text-xs font-bold shadow ${active}">$${Math.round(stay.price_per_night)}</span>`,
      })
      L.marker([stay.latitude, stay.longitude], { icon }).addTo(pins.current).bindPopup(`${stay.name}<br>$${stay.price_per_night} per night`)
    })

    if (!bounds && result.results.length) {
      map.current.fitBounds(L.latLngBounds(result.results.map((s) => [s.latitude, s.longitude])).pad(0.2))
    }
  }, [L, result, hovered, bounds])

  function toggle(field, value) {
    return (e) => setForm({
      ...form,
      [field]: e.target.checked ? [...form[field], value] : form[field].filter((v) => v !== value),
    })
  }

  function searchMapArea() {
    const b = map.current.getBounds()
    setBounds({ north: b.getNorth(), south: b.getSouth(), east: b.getEast(), west: b.getWest() })
  }

  async function saveBooking(stay) {
    setError('')
    try {
      await api.post('/bookings', {
        type: 'stay',
        accommodation_id: stay.id,
        budget_id: tripId === '' ? null : Number(tripId),
        title: stay.name,
        provider: stay.address,
        start_at: `${form.check_in} 15:00`,
        end_at: `${form.check_out} 11:00`,
        status: 'planned',
        price: stay.total_price,
      })
      setNotice(`Saved ${stay.name} to My bookings.`)
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  return (
    <div className="mx-auto max-w-6xl p-6">
      <TravelTabs />
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-3xl font-bold">Stay search</h1>
          <p className="text-gray-600">Filter by price, rating, distance, amenities and type, or search the area shown on the map.</p>
        </div>
        <label className="text-sm">
          Save bookings to trip
          <select className={inputClass} value={tripId} onChange={(e) => setTripId(e.target.value)}>
            <option value="">No trip</option>
            {budgets.map((b) => <option key={b.id} value={b.id}>{b.title}</option>)}
          </select>
        </label>
      </div>

      <FloatingNotice notice={notice} error={error} setNotice={setNotice} setError={setError} />

      <div className="mt-6 grid gap-6 lg:grid-cols-[300px_1fr]">
        <form className="space-y-3 rounded-lg bg-white p-4 shadow" onSubmit={(e) => e.preventDefault()}>
          <label className="block text-sm">
            Destination
            <select
              className={inputClass}
              value={form.destination_id}
              onChange={(e) => {
                setBounds(null)
                setForm({ ...form, destination_id: e.target.value })
              }}
            >
              {destinations.map((d) => <option key={d.id} value={d.id}>{d.name}, {d.country}</option>)}
            </select>
          </label>
          <div className="grid grid-cols-2 gap-3">
            <label className="text-sm">Check-in<input className={inputClass} type="date" value={form.check_in} onChange={(e) => setForm({ ...form, check_in: e.target.value })} /></label>
            <label className="text-sm">Check-out<input className={inputClass} type="date" value={form.check_out} onChange={(e) => setForm({ ...form, check_out: e.target.value })} /></label>
            <label className="text-sm">Min $/night<input className={inputClass} type="number" min="0" value={form.min_price} onChange={(e) => setForm({ ...form, min_price: e.target.value })} /></label>
            <label className="text-sm">Max $/night<input className={inputClass} type="number" min="0" value={form.max_price} onChange={(e) => setForm({ ...form, max_price: e.target.value })} /></label>
            <label className="text-sm">Min rating
              <select className={inputClass} value={form.min_rating} onChange={(e) => setForm({ ...form, min_rating: e.target.value })}>
                <option value="">Any</option><option>3.5</option><option>4</option><option>4.5</option>
              </select>
            </label>
            <label className="text-sm">Max km from centre<input className={inputClass} type="number" min="0.1" step="0.1" value={form.max_distance_km} onChange={(e) => setForm({ ...form, max_distance_km: e.target.value })} /></label>
          </div>
          {options && (
            <>
              <fieldset className="text-sm">
                <legend>Property type</legend>
                <div className="mt-1 flex flex-wrap gap-2">
                  {options.property_types.map((t) => (
                    <label key={t} className="flex items-center gap-1 rounded border px-2 py-1">
                      <input type="checkbox" checked={form.property_types.includes(t)} onChange={toggle('property_types', t)} /> {t}
                    </label>
                  ))}
                </div>
              </fieldset>
              <fieldset className="text-sm">
                <legend>Amenities (all must match)</legend>
                <div className="mt-1 flex flex-wrap gap-2">
                  {options.amenities.map((a) => (
                    <label key={a} className="flex items-center gap-1 rounded border px-2 py-1">
                      <input type="checkbox" checked={form.amenities.includes(a)} onChange={toggle('amenities', a)} /> {label(a)}
                    </label>
                  ))}
                </div>
              </fieldset>
            </>
          )}
          <label className="block text-sm">Sort by
            <select className={inputClass} value={form.sort} onChange={(e) => setForm({ ...form, sort: e.target.value })}>
              <option value="price">Price</option><option value="rating">Rating</option><option value="distance">Distance</option><option value="reviews">Most reviewed</option>
            </select>
          </label>
        </form>

        <div className="space-y-4">
          <div className="rounded-lg bg-white p-3 shadow">
            <div className="mb-2 flex items-center justify-between text-sm">
              <span className="text-gray-600">
                {result ? `${result.count} stays${result.center ? ` · distance from ${result.center.label}` : ''}` : 'Searching...'}
              </span>
              <button className="rounded border px-3 py-1" disabled={!L} onClick={searchMapArea}>Search this map area</button>
            </div>
            <div ref={mapElement} className="h-96 rounded" />
            <p className="mt-1 text-xs text-gray-500">Click the map to zoom with the mouse wheel, or use + and −.</p>
          </div>

          <ul className="divide-y rounded-lg bg-white shadow">
            {result?.count === 0 && <li className="p-4 text-gray-600">No stays match these filters.</li>}
            {result?.results.map((stay) => (
              <li
                key={stay.id}
                className="flex flex-wrap items-center justify-between gap-3 p-4"
                onMouseEnter={() => setHovered(stay.id)}
                onMouseLeave={() => setHovered(null)}
              >
                <div>
                  <p className="font-semibold">
                    {stay.name} <span className="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{stay.property_type}</span>
                  </p>
                  <p className="text-sm text-gray-600">
                    ★ {stay.rating} ({stay.review_count}) · {stay.distance_km ?? '?'} km · {stay.amenities.map(label).join(', ')}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <div className="text-right">
                    <p className="font-bold">{money(stay.price_per_night)} <span className="text-xs font-normal text-gray-500">/ night</span></p>
                    {stay.total_price && <p className="text-xs text-gray-500">{money(stay.total_price)} for {stay.nights} nights</p>}
                  </div>
                  <button className="rounded border px-3 py-1.5 text-sm" onClick={() => saveBooking(stay)}>Save booking</button>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  )
}
