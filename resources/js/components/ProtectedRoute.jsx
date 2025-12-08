// resources/js/components/ProtectedRoute.jsx
import React from 'react'
import { Navigate } from 'react-router-dom'
import { useCurrentUser } from '../lib/auth'

export default function ProtectedRoute({ children }) {
  const { user, loading } = useCurrentUser()
  if (loading) return <div className="p-4">Loading…</div>
  if (!user) return <Navigate to="/login" replace />
  return children
}