import { NavLink } from 'react-router-dom'
import { useAuth } from '../AuthContext'

const tabClass = ({ isActive }) =>
  `px-3 py-2 ${isActive ? 'border-b-2 border-blue-700 font-semibold text-blue-700' : 'text-gray-600 hover:text-blue-700'}`

export default function TravelTabs() {
  const { user } = useAuth()

  return (
    <nav className="mb-6 flex flex-wrap gap-1 border-b text-sm">
      <NavLink to="/flights" className={tabClass}>Flights</NavLink>
      <NavLink to="/stays" className={tabClass}>Stays</NavLink>
      <NavLink to="/bookings" className={tabClass}>My bookings</NavLink>
      {user?.role === 'admin' && <NavLink to="/admin/flights" className={tabClass}>Flight data</NavLink>}
    </nav>
  )
}
