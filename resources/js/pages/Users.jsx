import React, { useEffect, useState } from 'react'
import { getJSON, deleteJSON } from '../lib/http'
import { Link } from 'react-router-dom'

export default function Users() {
  const [users, setUsers] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  async function load() {
    setLoading(true)
    setError('')
    try {
      const [list, metaRes] = await Promise.all([
        getJSON('/api/users'),
        getJSON('/api/users/meta'),
      ])
      const arr = Array.isArray(list) ? list : (Array.isArray(list?.data) ? list.data : [])
      setUsers(arr)
      setMeta(metaRes)
    } catch (err) {
      setError(err.message || 'Failed to load users.')
      setUsers([])
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { load() }, [])

  async function remove(user) {
    if (!window.confirm(`Delete ${user.email}?`)) return
    setError('')
    try {
      await deleteJSON(`/api/users/${user.id}`)
      await load()
    } catch (err) {
      setError(err.message || 'Failed to delete user.')
    }
  }

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 className="h2 mb-0">Users</h1>
          <small className="text-muted">Manage user accounts and roles.</small>
        </div>
        <Link className="btn btn-primary" to="/admin/users/new">New User</Link>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {loading ? (
        <div>Loading…</div>
      ) : (
        <div className="card">
          <div className="table-responsive">
            <table className="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Company</th>
                  <th>Roles</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {users.length === 0 && (
                  <tr><td colSpan="5" className="text-center py-4 text-muted">No users yet.</td></tr>
                )}
                {users.map(user => (
                  <tr key={user.id}>
                    <td>{user.name} {user.surname}</td>
                    <td>{user.email}</td>
                    <td>{user.company?.name || '—'}</td>
                    <td>{user.roles?.join(', ')}</td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link className="btn btn-outline-primary" to={`/admin/users/${user.id}`}>Edit</Link>
                        <button className="btn btn-outline-danger" onClick={() => remove(user)}>Delete</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </>
  )
}
