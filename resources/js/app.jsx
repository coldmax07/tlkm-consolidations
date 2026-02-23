import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'

// Bootstrap CSS/JS (no inline styles in JSX — utilities/classes only)
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import '../css/theme-copy.css'
import '../css/theme-table.css'

import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Templates from './pages/Templates'
import FiscalCalendar from './pages/FiscalCalendar'
import StatementView from './pages/StatementView'
import ReportView from './pages/ReportView'
import AdminLayout from './layouts/AdminLayout'
import AuthLayout from './layouts/AuthLayout'
import ProtectedRoute from './components/ProtectedRoute'
import Users from './pages/Users'
import UserForm from './pages/UserForm'

function AppRouter() {
  return (
    <Routes>
      {/* Auth routes */}
      <Route element={<AuthLayout />}>
        <Route path="/login" element={<Login />} />
      </Route>

      {/* Admin routes */}
      <Route element={<AdminLayout />}>
        <Route path="/admin" element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        } />
        <Route path="/admin/statements/balance-sheet" element={
          <ProtectedRoute>
            <StatementView statementSlug="balance-sheet" />
          </ProtectedRoute>
        } />
        <Route path="/admin/statements/income-statement" element={
          <ProtectedRoute>
            <StatementView statementSlug="income-statement" />
          </ProtectedRoute>
        } />
        <Route path="/admin/reports/balance-sheet" element={
          <ProtectedRoute>
            <ReportView statementSlug="balance-sheet" />
          </ProtectedRoute>
        } />
        <Route path="/admin/reports/income-statement" element={
          <ProtectedRoute>
            <ReportView statementSlug="income-statement" />
          </ProtectedRoute>
        } />
        <Route path="/admin/templates" element={
          <ProtectedRoute>
            <Templates />
          </ProtectedRoute>
        } />
        <Route path="/admin/fiscal-calendar" element={
          <ProtectedRoute>
            <FiscalCalendar />
          </ProtectedRoute>
        } />
        <Route path="/admin/users" element={
          <ProtectedRoute>
            <Users />
          </ProtectedRoute>
        } />
        <Route path="/admin/users/:id" element={
          <ProtectedRoute>
            <UserForm />
          </ProtectedRoute>
        } />
      </Route>

      {/* Default redirect */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <AppRouter />
    </BrowserRouter>
  </React.StrictMode>
)
