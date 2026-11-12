import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import api from './api'

// R16: live updates through Laravel Reverb. Without VITE_REVERB_APP_KEY (or when the
// Reverb server is not running) the app still works; the board refreshes itself instead.
let echo = null

export function getEcho() {
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) return null
  if (echo) return echo

  const port = Number(import.meta.env.VITE_REVERB_PORT || 8080)
  window.Pusher = Pusher
  echo = new Echo({
    broadcaster: 'reverb',
    Pusher,
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Private channels are authorised with the same API token as every other request.
    authorizer: (channel) => ({
      authorize: (socketId, callback) => {
        api
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((res) => callback(null, res.data))
          .catch((err) => callback(err, null))
      },
    }),
  })

  return echo
}

// Calls onChange(true/false) whenever the live connection goes up or down.
export function watchConnection(onChange) {
  const connection = getEcho()?.connector?.pusher?.connection
  if (!connection) {
    onChange(false)
    return () => {}
  }

  const handler = ({ current }) => onChange(current === 'connected')
  onChange(connection.state === 'connected')
  connection.bind('state_change', handler)
  return () => connection.unbind('state_change', handler)
}
