import { useAuth } from '../AuthContext'

export default function Dashboard() {
  const { user, logout } = useAuth()

  return (
    <div className="mx-auto max-w-3xl p-6">
      <h1 className="text-3xl font-bold">Welcome, {user.name}</h1>
      <p className="mt-2 text-gray-600">Your trips will appear here.</p>
      <button className="mt-6 rounded bg-gray-800 px-4 py-2 text-white" onClick={logout}>
        Log out
      </button>
    </div>
  )
}