import React, { useEffect, useMemo, useState } from 'react'
import { getJSON, postJSON } from '../lib/http'

export default function FiscalCalendar() {
  const [fiscalYears, setFiscalYears] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const [actionKey, setActionKey] = useState('')

  useEffect(() => {
    loadFiscalYears()
  }, [])

  const openPeriods = useMemo(() => {
    const map = new Map()
    fiscalYears.forEach(year => {
      const open = year.periods?.find(period => !period.is_locked)
      if (open) {
        map.set(year.id, open.id)
      }
    })
    return map
  }, [fiscalYears])

  async function loadFiscalYears() {
    setLoading(true)
    setError('')
    try {
      const data = await getJSON('/api/fiscal-years')
      setFiscalYears(data?.data ?? data)
    } catch (err) {
      setError(err.message || 'Unable to load fiscal years.')
    } finally {
      setLoading(false)
    }
  }

  async function handleCreateFiscalYear() {
    if (!window.confirm('Creating a new fiscal year will close the current fiscal year and lock all of its periods. Continue?')) {
      return
    }

    setActionKey('create')
    setError('')
    setStatus('')
    try {
      await postJSON('/api/fiscal-years', {})
      setStatus('New fiscal year created and previous year closed.')
      await loadFiscalYears()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to create a new fiscal year.')
    } finally {
      setActionKey('')
    }
  }

  async function handleCloseFiscalYear(year) {
    if (year.is_closed) return
    if (!window.confirm(`Closing ${year.label} will lock every month. This cannot be undone. Continue?`)) {
      return
    }

    setActionKey(`close-${year.id}`)
    setError('')
    setStatus('')
    try {
      await postJSON(`/api/fiscal-years/${year.id}/close`, {})
      setStatus(`${year.label} closed.`)
      await loadFiscalYears()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to close fiscal year.')
    } finally {
      setActionKey('')
    }
  }

  async function handleLockPeriod(period) {
    setActionKey(`lock-${period.id}`)
    setError('')
    setStatus('')
    try {
      await postJSON(`/api/periods/${period.id}/lock`, {})
      setStatus(`#${period.period_number ?? ''} - ${period.label} locked.`)
      await loadFiscalYears()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to lock period.')
    } finally {
      setActionKey('')
    }
  }

  async function handleUnlockPeriod(period, year) {
    if (year.is_closed) return
    if (!window.confirm(`Unlocking ${period.label} will lock every other month in ${year.label}. Continue?`)) {
      return
    }

    setActionKey(`unlock-${period.id}`)
    setError('')
    setStatus('')
    try {
      await postJSON(`/api/periods/${period.id}/unlock`, {})
      setStatus(`#${period.period_number ?? ''} - ${period.label} unlocked.`)
      await loadFiscalYears()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to unlock period.')
    } finally {
      setActionKey('')
    }
  }

  return (
    <div className="vstack gap-3">
      <div className="d-flex justify-content-between align-items-center">
        <div>
          <h1 className="h2 mb-0">Fiscal Calendar</h1>
          <small className="text-muted">Lock months and roll the fiscal year forward.</small>
        </div>
        <button
          className="btn btn-primary"
          onClick={handleCreateFiscalYear}
          disabled={actionKey === 'create'}
        >
          {actionKey === 'create' ? 'Creating…' : 'Create New Fiscal Year'}
        </button>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {status && <div className="alert alert-success">{status}</div>}

      {loading ? (
        <div>Loading fiscal years…</div>
      ) : fiscalYears.length === 0 ? (
        <div className="alert alert-info">No fiscal years configured yet.</div>
      ) : (
        fiscalYears.map(year => {
          const closing = actionKey === `close-${year.id}`
          return (
            <div className="card" key={year.id}>
              <div className="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                  <div className="d-flex align-items-center gap-2">
                    <strong>{year.label}</strong>
                    {year.is_closed ? (
                      <span className="badge text-bg-secondary">Closed</span>
                    ) : (
                      <span className="badge text-bg-success">Open</span>
                    )}
                  </div>
                  <small className="text-muted">
                    {year.starts_on} → {year.ends_on}
                  </small>
                </div>
                <button
                  className="btn btn-outline-danger btn-sm"
                  disabled={year.is_closed || closing}
                  onClick={() => handleCloseFiscalYear(year)}
                >
                  {closing ? 'Closing…' : 'Close Fiscal Year'}
                </button>
              </div>
              <div className='card-body'>
                  <div className="table-responsive">
                      <table className="table table-sm mb-0">
                          <thead>
                          <tr>
                              <th>Period</th>
                              <th>Dates</th>
                              <th>Status</th>
                              <th></th>
                          </tr>
                          </thead>
                          <tbody>
                          {year.periods?.map(period => {
                              const locking = actionKey === `lock-${period.id}`
                              const unlocking = actionKey === `unlock-${period.id}`
                              const isOpen = !period.is_locked
                              const disableActions = year.is_closed || locking || unlocking
                              const currentOpenId = openPeriods.get(year.id)
                              const isCurrentOpen = currentOpenId === period.id

                              return (
                                  <tr key={period.id}>
                                      <td>
                                          <div className="fw-semibold">#{period.period_number ?? ''} - {period.label}</div>
                                          {isCurrentOpen && <small className="text-success">Current open period</small>}
                                      </td>
                                      <td>
                                          <small>{period.starts_on} → {period.ends_on}</small>
                                      </td>
                                      <td>
                                          {isOpen
                                              ? <span className="badge text-bg-success">Open</span>
                                              : <span className="badge text-bg-secondary">Locked</span>}
                                      </td>
                                      <td className="text-end">
                                          {isOpen ? (
                                              <button
                                                  className="btn btn-outline-secondary btn-sm"
                                                  onClick={() => handleLockPeriod(period)}
                                                  disabled={disableActions}
                                              >
                                                  {locking ? 'Locking…' : 'Lock'}
                                              </button>
                                          ) : (
                                              <button
                                                  className="btn btn-outline-primary btn-sm"
                                                  onClick={() => handleUnlockPeriod(period, year)}
                                                  disabled={disableActions}
                                              >
                                                  {unlocking ? 'Unlocking…' : 'Unlock'}
                                              </button>
                                          )}
                                      </td>
                                  </tr>
                              )
                          })}
                          </tbody>
                      </table>
                  </div>
              </div>
            </div>
          )
        })
      )}
    </div>
  )
}
