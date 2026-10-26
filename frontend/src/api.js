import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export function errorMessage(err) {
  const errors = err.response?.data?.errors
  if (errors) {
    return Object.values(errors)[0][0]
  }
  return err.response?.data?.message || 'Something went wrong. Please try again.'
}

export default api