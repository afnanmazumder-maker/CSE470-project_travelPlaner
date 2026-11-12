import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'

// R16: where an invite link (from an email or a copied link) lands.
export default function JoinTrip() {
  const { token } = useParams()
  const { user, loading } = useAuth()
  const navigate = useNavigate()
  const [invite, setInvite] = useState(null)
  const [error, setError] = useState('')
  const [joining, setJoining] = useState(false)

  useEffect(() => {
    api.get(`/invites/${token}`).then((res) => setInvite(res.data)).catch((err) => setError(errorMessage(err)))
  }, [token])

  async function join() {
    setError('')
    setJoining(true)
    try {
      const res = await api.post(`/invites/${token}/accept`)
      navigate(`/trips/${res.data.trip_id}`)
    } catch (err) {
      setError(errorMessage(err))
      setJoining(false)
    }
  }

  return (
    <div className="mx-auto max-w-lg p-6">
      <div className="rounded-lg bg-white p-6 shadow">
        <h1 className="text-2xl font-bold">Join a group trip</h1>
        {error && <p className="mt-3 rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}
        {!invite && !error && <p className="mt-3 text-gray-600">Loading the invite...</p>}

        {invite && (
          <>
            <p className="mt-3">
              <span className="font-semibold">{invite.invited_by ?? 'Someone'}</span> invited you to <span className="font-semibold">{invite.trip.title}</span>
              {invite.trip.destination ? ` (${invite.trip.destination.name}, ${invite.trip.destination.country}, ${invite.trip.days} days)` : ''} as <span className="font-semibold">{invite.role}</span>.
            </p>
            {invite.email && <p className="mt-1 text-sm text-gray-600">This invite is for {invite.email}.</p>}

            {invite.expired ? (
              <p className="mt-4 text-red-700">This invite has expired. Ask the trip owner for a new one.</p>
            ) : loading ? null : user ? (
              <button className="mt-5 rounded bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-60" onClick={join} disabled={joining}>
                {joining ? 'Joining...' : 'Join the trip'}
              </button>
            ) : (
              <div className="mt-5 flex gap-3">
                <Link className="rounded bg-blue-600 px-4 py-2 font-semibold text-white" to="/login" state={{ from: `/join/${token}` }}>Log in to join</Link>
                <Link className="rounded border px-4 py-2" to="/register">Sign up</Link>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
