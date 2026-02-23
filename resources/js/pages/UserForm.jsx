import React, { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { getJSON, postJSON, putJSON } from '../lib/http'

export default function UserForm() {
  const { id } = useParams()
  const isNew = id === 'new'
  const navigate = useNavigate()
  const [meta, setMeta] = useState(null)
  const [form, setForm] = useState({
    name: '',
    surname: '',
    email: '',
    company_id: '',
    password: '',
    password_confirmation: '',
    roles: [],
  })
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  const [loading, setLoading] = useState(true)

  async function loadMeta() {
    const res = await getJSON('/api/users/meta')
    setMeta(res)
  }

  async function loadUser() {
    if (isNew) return
    const res = await getJSON(`/api/users/${id}`)
    setForm({
      name: res.name || '',
      surname: res.surname || '',
      email: res.email || '',
      company_id: res.company?.id || '',
      password: '',
      password_confirmation: '',
      roles: res.roles || [],
    })
  }

  useEffect(() => {
    async function init() {
      setLoading(true)
      setError('')
      try {
        await loadMeta()
        await loadUser()
      } catch (err) {
        setError(err.message || 'Failed to load user.')
      } finally {
        setLoading(false)
      }
    }
    init()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id])

  function toggleRole(role) {
    setForm(prev => {
      const has = prev.roles.includes(role)
      return {
        ...prev,
        roles: has ? prev.roles.filter(r => r !== role) : [...prev.roles, role],
      }
    })
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setSaving(true)
    setError('')
    try {
      const payload = { ...form }
      if (!isNew && !payload.password) {
        delete payload.password
        delete payload.password_confirmation
      }
      if (isNew) {
        await postJSON('/api/users', payload)
      } else {
        await putJSON(`/api/users/${id}`, payload)
      }
      navigate('/admin/users')
    } catch (err) {
      setError(err.data?.message || err.message || 'Failed to save user.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="card">
      <div className="card-header">
        <strong>{isNew ? 'Create User' : 'Edit User'}</strong>
      </div>
      <div className="card-body">
        {error && <div className="alert alert-danger">{error}</div>}
        {loading ? (
          <div>Loading…</div>
        ) : (
          <form className="vstack gap-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-12 col-md-6">
                <label className="form-label">Name</label>
                <input
                  className="form-control"
                  value={form.name}
                  onChange={e => setForm({ ...form, name: e.target.value })}
                  required
                />
              </div>
              <div className="col-12 col-md-6">
                <label className="form-label">Surname</label>
                <input
                  className="form-control"
                  value={form.surname}
                  onChange={e => setForm({ ...form, surname: e.target.value })}
                  required
                />
              </div>
            </div>

            <div className="row g-3">
              <div className="col-12 col-md-6">
                <label className="form-label">Email</label>
                <input
                  type="email"
                  className="form-control"
                  value={form.email}
                  onChange={e => setForm({ ...form, email: e.target.value })}
                  required
                />
              </div>
              <div className="col-12 col-md-6">
                <label className="form-label">Company</label>
                <select
                  className="form-select"
                  value={form.company_id}
                  onChange={e => setForm({ ...form, company_id: e.target.value })}
                  required
                >
                  <option value="">Choose…</option>
                  {meta?.companies?.map(c => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="row g-3">
              <div className="col-12 col-md-6">
                <label className="form-label">Password {isNew ? '(required)' : '(leave blank to keep current)'}</label>
                <input
                  type="password"
                  className="form-control"
                  value={form.password}
                  onChange={e => setForm({ ...form, password: e.target.value })}
                  {...(isNew ? { required: true } : {})}
                  minLength={6}
                />
              </div>
              <div className="col-12 col-md-6">
                <label className="form-label">Confirm Password</label>
                <input
                  type="password"
                  className="form-control"
                  value={form.password_confirmation}
                  onChange={e => setForm({ ...form, password_confirmation: e.target.value })}
                  {...(isNew ? { required: true } : {})}
                  minLength={6}
                />
              </div>
            </div>

            <div className="card">
              <div className="card-header">
                <strong>Roles</strong>
              </div>
              <div className="card-body">
                <div className="d-flex flex-wrap gap-3">
                  {(meta?.roles ?? []).map(role => (
                    <label key={role.id} className="form-check">
                      <input
                        type="checkbox"
                        className="form-check-input"
                        checked={form.roles.includes(role.name)}
                        onChange={() => toggleRole(role.name)}
                      />
                      <span className="ms-2">{role.name}</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <div className="d-flex gap-2">
              <button className="btn btn-primary" type="submit" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
              </button>
              <button className="btn btn-secondary" type="button" onClick={() => navigate('/admin/users')} disabled={saving}>Cancel</button>
            </div>
          </form>
        )}
      </div>
    </div>
  )
}
