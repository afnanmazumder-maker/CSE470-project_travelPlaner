import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api from '../api'
import { useAuth } from '../AuthContext'
import DestinationCard from '../components/DestinationCard'

// To add a feature to the landing page, add an entry here.
// needsLogin: true shows a lock to logged-out visitors. soon: true shows "Coming soon".
const FEATURES = [
  { title: 'Explore destinations', blurb: 'Search cities and filter by budget, climate, themes and season.', to: '/explore', needsLogin: false },
  { title: 'Recommended for you', blurb: 'Places matched to your interests, with a reason for each.', to: '/recommendations', needsLogin: true },
  { title: 'Your interests', blurb: 'Tell us what you love so suggestions fit you.', to: '/preferences', needsLogin: true },
  { title: 'Trip itineraries', blurb: 'Day-by-day plans built from nearby attractions.', soon: true },
  { title: 'Reviews and journal', blurb: 'Record your trips and rate the places you visited.', soon: true },
]

function FeatureCard({ feature, loggedIn }) {
  const base = 'block rounded-lg bg-white p-4 shadow'

  if (feature.soon) {
    return (
      <div className={`${base} opacity-70`}>
        <h3 className="font-semibold">{feature.title}</h3>
        <p className="mt-1 text-sm text-gray-600">{feature.blurb}</p>
        <p className="mt-2 text-xs font-medium text-gray-500">Coming soon</p>
      </div>
    )
  }

  const locked = feature.needsLogin && !loggedIn

  return (
    <Link to={locked ? '/login' : feature.to} className={`${base} hover:shadow-md`}>
      <h3 className="font-semibold">{feature.title}</h3>
      <p className="mt-1 text-sm text-gray-600">{feature.blurb}</p>
      <p className={`mt-2 text-xs font-medium ${locked ? 'text-amber-700' : 'text-blue-700'}`}>
        {locked ? '🔒 Log in to use' : 'Open'}
      </p>
    </Link>
  )
}

export default function Landing() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [featured, setFeatured] = useState([])
  const [needsInterests, setNeedsInterests] = useState(false)

  useEffect(() => {
    let ignore = false

    api
      .get('/destinations', { params: { per_page: 6 } })
      .then((res) => {
        if (!ignore) setFeatured(res.data.data)
      })
      .catch(() => {})

    return () => {
      ignore = true
    }
  }, [])

  useEffect(() => {
    if (!user) {
      setNeedsInterests(false)
      return
    }

    let ignore = false

    api
      .get('/preferences')
      .then((res) => {
        if (!ignore) setNeedsInterests(Object.keys(res.data.interest_weights).length === 0)
      })
      .catch(() => {})

    return () => {
      ignore = true
    }
  }, [user])

  function handleSearch(e) {
    e.preventDefault()
    const term = query.trim()
    navigate(term ? `/explore?q=${encodeURIComponent(term)}` : '/explore')
  }

  return (
    <div>
      <section className="bg-gradient-to-br from-blue-600 to-indigo-700 px-4 py-16 text-white">
        <div className="mx-auto max-w-3xl text-center">
          <h1 className="text-4xl font-bold sm:text-5xl">Your Journey, Your Way.</h1>
          <p className="mt-3 text-lg text-blue-100">
            Discover destinations and plan your trip in one place.
          </p>

          <form onSubmit={handleSearch} className="mx-auto mt-8 flex max-w-xl gap-2">
            <input
              className="min-w-0 flex-1 rounded-lg p-3 text-gray-900"
              placeholder="Search a city, country or region"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
            <button className="rounded-lg bg-white px-5 py-3 font-semibold text-blue-700">
              Search
            </button>
          </form>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-4 py-8">
        {user && needsInterests && (
          <div className="mb-8 rounded-lg bg-blue-50 p-4">
            <p className="font-medium text-blue-900">Get suggestions that fit you</p>
            <p className="text-sm text-blue-800">Choose a few interests and we will pick places for you.</p>
            <Link className="mt-2 inline-block text-blue-700 underline" to="/preferences">
              Choose your interests
            </Link>
          </div>
        )}

        <h2 className="text-2xl font-semibold">Popular destinations</h2>
        {featured.length === 0 ? (
          <p className="mt-3 text-gray-600">Destinations will appear here.</p>
        ) : (
          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {featured.map((d) => (
              <DestinationCard key={d.id} destination={d} />
            ))}
          </div>
        )}
        <Link className="mt-4 inline-block text-blue-600" to="/explore">See all destinations &rarr;</Link>

        <h2 className="mt-12 text-2xl font-semibold">Everything in one place</h2>
        {!user && (
          <p className="mt-1 text-gray-600">
            Searching is open to everyone.{' '}
            <Link className="text-blue-600 underline" to="/register">Create a free account</Link> to unlock the rest.
          </p>
        )}
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((f) => (
            <FeatureCard key={f.title} feature={f} loggedIn={Boolean(user)} />
          ))}
        </div>
      </div>
    </div>
  )
}