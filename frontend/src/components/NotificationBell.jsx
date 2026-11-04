import { useEffect, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import api from '../api'

export default function NotificationBell() {
  const location = useLocation()
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let ignore = false
    api
      .get('/notifications')
      .then((res) => {
        if (!ignore) setUnread(res.data.unread_count)
      })
      .catch(() => {})
    return () => {
      ignore = true
    }
  }, [location.pathname])

  return (
    <NavLink
      to="/notifications"
      aria-label={`Notifications, ${unread} unread`}
      className={({ isActive }) => `relative px-2 py-1 ${isActive ? 'text-blue-700' : 'text-gray-700 hover:text-blue-700'}`}
    >
      🔔
      {unread > 0 && (
        <span className="absolute -right-1 -top-1 rounded-full bg-orange-600 px-1.5 text-xs font-bold text-white">{unread}</span>
      )}
    </NavLink>
  )
}
