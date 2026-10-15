import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export function connectEcho() {
    const key = import.meta.env.VITE_PUSHER_APP_KEY
    if (!key) return null

    return new Echo({
        broadcaster: 'pusher',
        Pusher,
        key,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
        wsHost: import.meta.env.VITE_PUSHER_HOST,
        wsPort: import.meta.env.VITE_PUSHER_PORT,
        wssPort: import.meta.env.VITE_PUSHER_PORT,
        enabledTransports: ['ws', 'wss'],
    })
}
