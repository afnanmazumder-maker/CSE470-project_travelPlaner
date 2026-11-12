import { Navigate, Route, Routes } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import Bookings from './pages/Bookings'
import BudgetDashboard from './pages/BudgetDashboard'
import Budgets from './pages/Budgets'
import Destination from './pages/Destination'
import Explore from './pages/Explore'
import FlightAdmin from './pages/FlightAdmin'
import FlightSearch from './pages/FlightSearch'
import Landing from './pages/Landing'
import LanguageHelper from './pages/LanguageHelper'
import Login from './pages/Login'
import Onboarding from './pages/Onboarding'
import Preferences from './pages/Preferences'
import Recommendations from './pages/Recommendations'
import Register from './pages/Register'
import SpendTracker from './pages/SpendTracker'
import StaySearch from './pages/StaySearch'
import VarianceReport from './pages/VarianceReport'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route
        path="/welcome"
        element={
          <ProtectedRoute allowIncomplete>
            <Onboarding />
          </ProtectedRoute>
        }
      />

      <Route element={<Layout />}>
        <Route path="/" element={<Landing />} />
        <Route path="/explore" element={<Explore />} />
        <Route path="/destinations/:id" element={<Destination />} />
        <Route path="/phrasebook" element={<LanguageHelper />} />
        <Route
          path="/recommendations"
          element={
            <ProtectedRoute>
              <Recommendations />
            </ProtectedRoute>
          }
        />
        <Route
          path="/preferences"
          element={
            <ProtectedRoute>
              <Preferences />
            </ProtectedRoute>
          }
        />
        <Route
          path="/budgets"
          element={
            <ProtectedRoute>
              <Budgets />
            </ProtectedRoute>
          }
        />
        <Route
          path="/budgets/:id"
          element={
            <ProtectedRoute>
              <BudgetDashboard />
            </ProtectedRoute>
          }
        />
        <Route
          path="/flights"
          element={
            <ProtectedRoute>
              <FlightSearch />
            </ProtectedRoute>
          }
        />
        <Route
          path="/stays"
          element={
            <ProtectedRoute>
              <StaySearch />
            </ProtectedRoute>
          }
        />
        <Route
          path="/bookings"
          element={
            <ProtectedRoute>
              <Bookings />
            </ProtectedRoute>
          }
        />
        <Route
          path="/admin/flights"
          element={
            <ProtectedRoute>
              <FlightAdmin />
            </ProtectedRoute>
          }
        />
        <Route
          path="/budgets/:id/spending"
          element={
            <ProtectedRoute>
              <SpendTracker />
            </ProtectedRoute>
          }
        />
        <Route
          path="/budgets/:id/report"
          element={
            <ProtectedRoute>
              <VarianceReport />
            </ProtectedRoute>
          }
        />
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}