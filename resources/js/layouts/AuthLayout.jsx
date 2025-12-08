import React from 'react'
import { Outlet } from 'react-router-dom'

export default function AuthLayout() {
  // Use Bootstrap utilities to vertically center content (no inline styles)
  return (
    <main className="d-flex align-items-center justify-content-center min-vh-100 bg-light">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-11 col-sm-8 col-md-6 col-lg-4">
            <Outlet />
          </div>
        </div>
      </div>
    </main>
  )
}