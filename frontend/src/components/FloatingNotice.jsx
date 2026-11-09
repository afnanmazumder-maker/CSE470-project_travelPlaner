import { useEffect } from 'react'

// R9: shows the result of an action pinned to the bottom of the screen, so it is
// seen even when the button that caused it is far down the page (search results,
// the stay map, the fare table). Success messages hide after 5 seconds; errors
// stay until they are closed or the next action clears them.
export default function FloatingNotice({ notice = '', error = '', setNotice, setError }) {
  useEffect(() => {
    if (!notice || !setNotice) return
    const timer = setTimeout(() => setNotice(''), 5000)
    return () => clearTimeout(timer)
  }, [notice, setNotice])

  if (!notice && !error) return null

  return (
    <div className="fixed inset-x-4 bottom-4 z-[1100] mx-auto flex max-w-md flex-col gap-2">
      {error && (
        <div role="alert" className="flex items-start justify-between gap-3 rounded-lg bg-red-50 p-3 text-red-700 shadow-lg ring-1 ring-red-200">
          <p>{error}</p>
          {setError && (
            <button type="button" className="font-bold" aria-label="Close message" onClick={() => setError('')}>×</button>
          )}
        </div>
      )}
      {notice && (
        <div role="status" className="flex items-start justify-between gap-3 rounded-lg bg-green-50 p-3 text-green-800 shadow-lg ring-1 ring-green-200">
          <p>{notice}</p>
          <button type="button" className="font-bold" aria-label="Close message" onClick={() => setNotice('')}>×</button>
        </div>
      )}
    </div>
  )
}
