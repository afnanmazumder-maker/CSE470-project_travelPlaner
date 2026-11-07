import { useEffect, useState } from 'react'

// Loads Leaflet from the CDN only on pages that show a map, so the app needs
// no extra npm dependency. Returns the Leaflet object once it is ready.
const VERSION = '1.9.4'
let loading = null

function loadLeaflet() {
  if (window.L) return Promise.resolve(window.L)
  if (loading) return loading

  loading = new Promise((resolve, reject) => {
    const css = document.createElement('link')
    css.rel = 'stylesheet'
    css.href = `https://cdnjs.cloudflare.com/ajax/libs/leaflet/${VERSION}/leaflet.min.css`
    document.head.append(css)

    const script = document.createElement('script')
    script.src = `https://cdnjs.cloudflare.com/ajax/libs/leaflet/${VERSION}/leaflet.min.js`
    script.onload = () => resolve(window.L)
    script.onerror = reject
    document.head.append(script)
  })

  return loading
}

export default function useLeaflet() {
  const [leaflet, setLeaflet] = useState(window.L || null)

  useEffect(() => {
    let ignore = false
    loadLeaflet()
      .then((L) => {
        if (!ignore) setLeaflet(L)
      })
      .catch(() => {})
    return () => {
      ignore = true
    }
  }, [])

  return leaflet
}
