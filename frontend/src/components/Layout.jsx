import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../AuthContext'
import SiteHeader from './SiteHeader'

export default function Layout() {
  const { user, loading, onboarded } = useAuth()

  if (loading) {
    return <p className="p-6 text-gray-600">Loading...</p>
  }

  if (user && !onboarded) {
    return <Navigate to="/welcome" replace />
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <SiteHeader />
      <main>
        <Outlet />
      </main>
    </div>
  )
}