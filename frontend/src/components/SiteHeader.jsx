import { Link, NavLink } from 'react-router-dom'
import { useAuth } from '../AuthContext'

const linkClass = ({ isActive }) =>
  `px-2 py-1 ${isActive ? 'font-semibold text-blue-700' : 'text-gray-700 hover:text-blue-700'}`

export default function SiteHeader() {
  const { user, logout } = useAuth()

  return (
    <header className="border-b bg-white">
      <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <Link to="/" className="text-xl font-bold text-blue-700">Voyenta</Link>

        <nav className="flex flex-wrap items-center gap-1 text-sm">
          <NavLink to="/explore" className={linkClass}>Explore</NavLink>

          {user ? (
            <>
              <NavLink to="/recommendations" className={linkClass}>For you</NavLink>
              <NavLink to="/preferences" className={linkClass}>Interests</NavLink>
              <NavLink to="/budgets" className={linkClass}>Budgets</NavLink>
              <span className="px-2 text-gray-500">{user.name}</span>
              <button className="rounded border px-3 py-1" onClick={logout}>Log out</button>
            </>
          ) : (
            <>
              <Link className="px-2 py-1 text-gray-700 hover:text-blue-700" to="/login">Log in</Link>
              <Link className="rounded bg-blue-600 px-3 py-1 font-semibold text-white" to="/register">
                Sign up
              </Link>
            </>
          )}
        </nav>
      </div>
    </header>
  )
}