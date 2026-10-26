import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../AuthContext'
import { errorMessage } from '../api'

export default function Register() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function handleChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      await register(form)
      navigate('/')
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 p-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded-lg bg-white p-6 shadow">
        <h1 className="text-2xl font-bold">Create your account</h1>
        {error && <p className="rounded bg-red-50 p-2 text-sm text-red-700">{error}</p>}
        <input
          className="w-full rounded border p-2"
          name="name"
          placeholder="Full name"
          value={form.name}
          onChange={handleChange}
          required
        />
        <input
          className="w-full rounded border p-2"
          type="email"
          name="email"
          placeholder="Email"
          value={form.email}
          onChange={handleChange}
          required
        />
        <input
          className="w-full rounded border p-2"
          type="password"
          name="password"
          placeholder="Password (at least 8 characters)"
          value={form.password}
          onChange={handleChange}
          required
        />
        <input
          className="w-full rounded border p-2"
          type="password"
          name="password_confirmation"
          placeholder="Confirm password"
          value={form.password_confirmation}
          onChange={handleChange}
          required
        />
        <button
          className="w-full rounded bg-blue-600 p-2 font-semibold text-white disabled:opacity-50"
          disabled={submitting}
        >
          {submitting ? 'Creating account...' : 'Register'}
        </button>
        <p className="text-sm">
          Already registered? <Link className="text-blue-600" to="/login">Log in</Link>
        </p>
      </form>
    </div>
  )
}