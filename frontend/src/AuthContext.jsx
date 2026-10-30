import { createContext, useCallback, useContext, useEffect, useState } from 'react'
import api from './api'

const AuthContext = createContext(null)

async function fetchOnboarded() {
  try {
    const res = await api.get('/preferences')
    return res.data.onboarded
  } catch {
    return true
  }
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [onboarded, setOnboarded] = useState(true)
  const [loading, setLoading] = useState(true)

  const loadSession = useCallback(async () => {
    if (!localStorage.getItem('token')) {
      setUser(null)
      setOnboarded(true)
      setLoading(false)
      return
    }

    try {
      const res = await api.get('/user')
      setOnboarded(await fetchOnboarded())
      setUser(res.data)
    } catch {
      localStorage.removeItem('token')
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadSession()
  }, [loadSession])

  useEffect(() => {
    function clearSession() {
      setUser(null)
      setOnboarded(true)
    }

    function handleStorage(e) {
      if (e.key !== 'token' && e.key !== null) return

      if (localStorage.getItem('token')) {
        loadSession()
      } else {
        clearSession()
      }
    }

    window.addEventListener('storage', handleStorage)
    window.addEventListener('auth:expired', clearSession)

    return () => {
      window.removeEventListener('storage', handleStorage)
      window.removeEventListener('auth:expired', clearSession)
    }
  }, [loadSession])

  async function register(form) {
    const res = await api.post('/register', form)
    localStorage.setItem('token', res.data.token)
    setOnboarded(false)
    setUser(res.data.user)
  }

  async function login(form) {
    const res = await api.post('/login', form)
    localStorage.setItem('token', res.data.token)
    setOnboarded(await fetchOnboarded())
    setUser(res.data.user)
  }

  async function logout() {
    const token = localStorage.getItem('token')

    localStorage.removeItem('token')
    setUser(null)
    setOnboarded(true)

    if (token) {
      try {
        await api.post('/logout', null, {
          headers: { Authorization: `Bearer ${token}` },
        })
      } catch {
        // The token is already gone locally; nothing more to do.
      }
    }
  }

  function completeOnboarding() {
    setOnboarded(true)
  }

  return (
    <AuthContext.Provider
      value={{ user, loading, onboarded, completeOnboarding, register, login, logout }}
    >
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  return useContext(AuthContext)
}