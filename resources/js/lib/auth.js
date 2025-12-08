// resources/js/lib/auth.js
import { useEffect, useState } from 'react'
import { getJSON } from './http'

export function useCurrentUser() {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let cancelled = false
    getJSON('/user')
      .then(u => { if (!cancelled) { setUser(u); setLoading(false) } })
      .catch(() => { if (!cancelled) { setUser(null); setLoading(false) } })
    return () => { cancelled = true }
  }, [])

  return { user, loading, setUser }
}