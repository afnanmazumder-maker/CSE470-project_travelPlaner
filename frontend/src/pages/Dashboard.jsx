import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api from '../api'
import { useAuth } from '../AuthContext'

export default function Dashboard() {
  const { user, logout } = useAuth()
  const [onboarded, setOnboarded] = useState(true)

  useEffect(() => {
    api
      .get('/preferences')
      .then((res) => setOnboarded(res.data.onboarded))
      .catch(() => {})
  }, [])

  return (
    <div className="mx-auto max-w-3xl p-6">
      <h1 className="text-3xl font-bold">Welcome, {user.name}</h1>
      <p className="mt-2 text-gray-600">Your trips will appear here.</p>

      {!onboarded && (
        <div className="mt-6 rounded-lg bg-blue-50 p-4">
          <p className="font-medium text-blue-900">Tell us what you enjoy</p>
          <p className="text-sm text-blue-800">
            A short quiz lets us suggest places that fit you.
          </p>
          <Link className="mt-2 inline-block text-blue-700 underline" to="/preferences">
            Take the quiz
          </Link>
        </div>
      )}

      <div className="mt-6 flex flex-wrap gap-4 text-blue-600">
        <Link to="/explore">Explore destinations</Link>
        <Link to="/recommendations">Recommended for you</Link>
        <Link to="/preferences">Edit interests</Link>
      </div>

      <button className="mt-8 rounded bg-gray-800 px-4 py-2 text-white" onClick={logout}>
        Log out
      </button>
    </div>
  )
}