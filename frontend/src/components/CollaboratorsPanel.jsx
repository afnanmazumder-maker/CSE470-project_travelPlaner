import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { errorMessage } from '../api'
import { useAuth } from '../AuthContext'

const ROLES = ['owner', 'editor', 'viewer']
const ROLE_HELP = {
  owner: 'Owners invite people, change roles and edit the itinerary.',
  editor: 'Editors add and change items, vote and comment.',
  viewer: 'Viewers see the itinerary, votes, comments and history.',
}

// R16: who is on the group trip. Owners invite by email or link as owner, editor
// or viewer, change roles and remove people; anyone else can leave the trip.
// `version` changes when the board hears about a change, so the list reloads.
export default function CollaboratorsPanel({ tripId, version, onChanged, setNotice, setError }) {
  const { user } = useAuth()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [invite, setInvite] = useState({ email: '', role: 'editor' })
  const [newLink, setNewLink] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    let ignore = false
    api
      .get(`/budgets/${tripId}/members`)
      .then((res) => {
        if (!ignore) setData(res.data)
      })
      .catch((err) => {
        if (!ignore) setError(errorMessage(err))
      })
    return () => {
      ignore = true
    }
  }, [tripId, version, setError])

  const isOwner = data?.my_role === 'owner'

  async function sendInvite(e) {
    e.preventDefault()
    setError('')
    setSending(true)
    try {
      const res = await api.post(`/budgets/${tripId}/invites`, { email: invite.email || null, role: invite.role })
      setData(res.data.members)
      if (invite.email) {
        setNotice(`Invite sent to ${res.data.invite.email}.`)
        setNewLink('')
      } else {
        setNewLink(res.data.invite.link)
        setNotice('Invite link created. Copy it and send it to one person.')
      }
      setInvite({ ...invite, email: '' })
      onChanged()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSending(false)
    }
  }

  async function changeRole(member, role) {
    setError('')
    try {
      const res = await api.patch(`/budgets/${tripId}/members/${member.id}`, { role })
      setData(res.data)
      onChanged()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function remove(member, message) {
    if (!window.confirm(message)) return
    setError('')
    try {
      await api.delete(`/budgets/${tripId}/members/${member.id}`)
      if (member.user_id === user?.id) {
        navigate('/trips')
        return
      }
      setData({
        ...data,
        members: data.members.filter((m) => m.id !== member.id),
        invites: data.invites.filter((i) => i.id !== member.id),
      })
      onChanged()
    } catch (err) {
      setError(errorMessage(err))
    }
  }

  async function copy(link) {
    try {
      await navigator.clipboard.writeText(link)
      setNotice('Link copied.')
    } catch {
      setNotice(link)
    }
  }

  if (!data) return <section className="rounded-lg bg-white p-4 shadow text-sm text-gray-600">Loading people...</section>

  return (
    <section className="space-y-4 rounded-lg bg-white p-4 shadow">
      <div>
        <h2 className="text-lg font-semibold">Collaborators</h2>
        <p className="text-sm text-gray-600">{ROLE_HELP[data.my_role]}</p>
      </div>

      <ul className="divide-y text-sm">
        <li className="flex items-center justify-between gap-2 py-2">
          <div>
            <p className="font-medium">{data.owner.name}{data.owner.user_id === user?.id ? ' (you)' : ''}</p>
            <p className="text-gray-500">Trip creator</p>
          </div>
          <span className="rounded bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-800">owner</span>
        </li>
        {data.members.map((m) => (
          <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
            <div>
              <p className="font-medium">{m.name}{m.user_id === user?.id ? ' (you)' : ''}</p>
              <p className="text-gray-500">{m.email}</p>
            </div>
            <div className="flex items-center gap-2">
              {isOwner ? (
                <select className="rounded border px-2 py-1" value={m.role} onChange={(e) => changeRole(m, e.target.value)} aria-label={`Role of ${m.name}`}>
                  {ROLES.map((r) => <option key={r}>{r}</option>)}
                </select>
              ) : (
                <span className="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{m.role}</span>
              )}
              {m.user_id === user?.id ? (
                <button className="text-red-700" onClick={() => remove(m, 'Leave this trip? You will need a new invite to come back.')}>Leave</button>
              ) : (
                isOwner && <button className="text-red-700" onClick={() => remove(m, `Remove ${m.name} from the trip?`)}>Remove</button>
              )}
            </div>
          </li>
        ))}
      </ul>

      {isOwner && (
        <>
          <form onSubmit={sendInvite} className="space-y-2 border-t pt-4 text-sm">
            <h3 className="font-semibold">Invite someone</h3>
            <input
              className="w-full rounded border px-3 py-2"
              type="email"
              placeholder="Email (leave empty for a link)"
              value={invite.email}
              onChange={(e) => setInvite({ ...invite, email: e.target.value })}
            />
            <div className="flex gap-2">
              <select className="flex-1 rounded border px-3 py-2" value={invite.role} onChange={(e) => setInvite({ ...invite, role: e.target.value })} aria-label="Role for the invite">
                {ROLES.map((r) => <option key={r}>{r}</option>)}
              </select>
              <button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white disabled:opacity-60" disabled={sending}>
                {invite.email ? 'Send invite' : 'Create link'}
              </button>
            </div>
            {newLink && (
              <div className="flex items-center gap-2 rounded bg-blue-50 p-2">
                <input className="flex-1 rounded border bg-white px-2 py-1 text-xs" readOnly value={newLink} aria-label="New invite link" />
                <button type="button" className="rounded border bg-white px-2 py-1" onClick={() => copy(newLink)}>Copy</button>
              </div>
            )}
          </form>

          {data.invites.length > 0 && (
            <div className="border-t pt-4 text-sm">
              <h3 className="font-semibold">Waiting to join</h3>
              <ul className="divide-y">
                {data.invites.map((i) => (
                  <li key={i.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div>
                      <p className="font-medium">{i.email ?? 'Invite link'} · {i.role}</p>
                      <p className={i.expired ? 'text-red-700' : 'text-gray-500'}>
                        {i.expired ? 'Expired' : `Until ${new Date(i.expires_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`}
                      </p>
                    </div>
                    <div className="flex gap-2">
                      <button className="rounded border px-2 py-1" onClick={() => copy(i.link)}>Copy link</button>
                      <button className="text-red-700" onClick={() => remove(i, 'Cancel this invite?')}>Cancel</button>
                    </div>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </>
      )}
    </section>
  )
}
