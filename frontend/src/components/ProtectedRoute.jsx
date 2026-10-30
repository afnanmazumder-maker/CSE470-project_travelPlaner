import { Navigate } from 'react-router-dom'
import { useAuth } from '../AuthContext'

export default function ProtectedRoute({ children, allowIncomplete = false }) {
  const { user, loading, onboarded } = useAuth()

  if (loading) {
    return <p className="p-6 text-gray-600">Loading...</p>
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  if (!onboarded && !allowIncomplete) {
    return <Navigate to="/welcome" replace />
  }

  return children
}