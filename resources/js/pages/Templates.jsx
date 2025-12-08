import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { deleteJSON, getJSON, postJSON, putJSON } from '../lib/http'

const emptyForm = {
  id: null,
  financial_statement_id: '',
  sender_company_id: '',
  receiver_company_id: '',
  sender_account_category_id: '',
  sender_hfm_account_id: '',
  receiver_account_category_id: '',
  receiver_hfm_account_id: '',
  description: '',
  currency: 'ZAR',
  default_amount: '',
  is_active: true,
}

const STATEMENT_CATEGORY_RULES = {
  BALANCE_SHEET: { sender: 'RECEIVABLE', receiver: 'PAYABLE' },
  INCOME_STATEMENT: { sender: 'REVENUE', receiver: 'EXPENSE' },
}

const AUTO_HIGHLIGHT_DURATION = 1200

export default function Templates() {
  const [templates, setTemplates] = useState([])
  const [meta, setMeta] = useState(null)
  const [form, setForm] = useState(emptyForm)
  const [loading, setLoading] = useState(true)
  const [loadingTemplates, setLoadingTemplates] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [status, setStatus] = useState('')
  const [generation, setGeneration] = useState({ period_id: '', financial_statement_id: '' })
  const [pagination, setPagination] = useState(null)
  const [tableParams, setTableParams] = useState({
    page: 1,
    perPage: 10,
    sortBy: 'created_at',
    sortDir: 'desc',
    search: '',
    filterStatement: '',
    filterActive: '',
  })
  const [autoFilledFields, setAutoFilledFields] = useState([])
  const autoFillTimeouts = useRef([])

  const markAutoFilled = useCallback((...fields) => {
    const uniqueFields = Array.from(new Set(fields.filter(Boolean)))
    if (uniqueFields.length === 0) return

    setAutoFilledFields(prev => {
      const merged = new Set([...prev, ...uniqueFields])
      return Array.from(merged)
    })

    const timeoutId = setTimeout(() => {
      setAutoFilledFields(prev => prev.filter(field => !uniqueFields.includes(field)))
    }, AUTO_HIGHLIGHT_DURATION)

    autoFillTimeouts.current.push(timeoutId)
  }, [])

  useEffect(() => {
    return () => {
      autoFillTimeouts.current.forEach(timeoutId => clearTimeout(timeoutId))
      autoFillTimeouts.current = []
    }
  }, [])

  const isAutoFilled = useCallback(field => autoFilledFields.includes(field), [autoFilledFields])

  function handleTableParamsChange(partial, resetPage = false) {
    setTableParams(prev => ({
      ...prev,
      ...partial,
      page: partial.page !== undefined ? partial.page : (resetPage ? 1 : prev.page),
    }))
  }

  function toggleSort(field) {
    setTableParams(prev => ({
      ...prev,
      page: 1,
      sortBy: field,
      sortDir: prev.sortBy === field && prev.sortDir === 'asc' ? 'desc' : 'asc',
    }))
  }

  function renderSortIndicator(field) {
    if (tableParams.sortBy !== field) return null
    return tableParams.sortDir === 'asc' ? '▲' : '▼'
  }

  function changePage(nextPage) {
    handleTableParamsChange({ page: Math.max(1, nextPage) })
  }

  function changePerPage(value) {
    handleTableParamsChange({ perPage: Number(value), page: 1 })
  }

  async function loadMeta() {
    if (meta) return
    setLoading(true)
    try {
      const metaData = await getJSON('/api/templates/meta')
      setMeta(metaData)
      setError('')
    } catch (err) {
      setError(err.message || 'Failed to load templates metadata.')
    } finally {
      setLoading(false)
    }
  }

  async function loadTemplates() {
    setLoadingTemplates(true)
    try {
      const params = new URLSearchParams()
      params.append('page', tableParams.page)
      params.append('per_page', tableParams.perPage)
      params.append('sort_by', tableParams.sortBy)
      params.append('sort_dir', tableParams.sortDir)
      if (tableParams.search) params.append('search', tableParams.search)
      if (tableParams.filterStatement) params.append('financial_statement_id', tableParams.filterStatement)
      if (tableParams.filterActive !== '') params.append('is_active', tableParams.filterActive)

      const templateData = await getJSON(`/api/templates?${params.toString()}`)
      setTemplates(templateData.data ?? templateData)
      setPagination(templateData.meta ?? null)
      setError('')
    } catch (err) {
      setError(err.message || 'Failed to load templates.')
    } finally {
      setLoadingTemplates(false)
    }
  }

  const statements = meta?.financial_statements ?? []
  const companies = meta?.companies ?? []
  const periods = meta?.periods ?? []

  const selectedStatement = useMemo(() => (
    statements.find(s => String(s.id) === String(form.financial_statement_id))
  ), [statements, form.financial_statement_id])

  const statementRule = useMemo(() => (
    selectedStatement ? STATEMENT_CATEGORY_RULES[selectedStatement.name] : null
  ), [selectedStatement])

  const senderCategories = useMemo(() => {
    if (!selectedStatement?.categories) return []
    if (!statementRule) return selectedStatement.categories
    return selectedStatement.categories.filter(cat => cat.name === statementRule.sender)
  }, [selectedStatement, statementRule])

  const receiverCategories = useMemo(() => {
    if (!selectedStatement?.categories) return []
    if (!statementRule) return selectedStatement.categories
    return selectedStatement.categories.filter(cat => cat.name === statementRule.receiver)
  }, [selectedStatement, statementRule])

  const statementPairs = selectedStatement?.account_pairs ?? []

  const availableSenderCompanies = useMemo(() => (
    companies.filter(company => String(company.id) !== String(form.receiver_company_id))
  ), [companies, form.receiver_company_id])

  const availableReceiverCompanies = useMemo(() => (
    companies.filter(company => String(company.id) !== String(form.sender_company_id))
  ), [companies, form.sender_company_id])

  const senderAccounts = useMemo(() => {
    const seen = new Set()
    return statementPairs.reduce((acc, pair) => {
      if (!pair?.sender_account) return acc
      const key = String(pair.sender_account.id)
      if (seen.has(key)) return acc
      seen.add(key)
      acc.push(pair.sender_account)
      return acc
    }, [])
  }, [statementPairs])

  const selectedPair = useMemo(() => statementPairs.find(pair => (
    pair?.sender_account && String(pair.sender_account.id) === String(form.sender_hfm_account_id)
  )), [statementPairs, form.sender_hfm_account_id])

  const receiverAccounts = useMemo(() => {
    if (!selectedPair?.receiver_account) return []
    return [selectedPair.receiver_account]
  }, [selectedPair])

  const availableGenerationPeriods = useMemo(() => (
    periods.filter(period => !period.is_locked && !(period.fiscal_year?.closed_at))
  ), [periods])

  const currentPage = pagination?.current_page ?? tableParams.page
  const lastPage = pagination?.last_page ?? 1
  const totalTemplates = pagination?.total ?? templates.length

  useEffect(() => {
    loadMeta()
  }, [])

  useEffect(() => {
    loadTemplates()
  }, [tableParams])

  useEffect(() => {
    setGeneration(prev => {
      if (!prev.period_id) return prev
      const stillAvailable = availableGenerationPeriods.some(p => String(p.id) === String(prev.period_id))
      if (stillAvailable) return prev
      return { ...prev, period_id: '' }
    })
  }, [availableGenerationPeriods])

  useEffect(() => {
    if (!selectedStatement || !statementRule) return

    const senderCategory = selectedStatement.categories?.find(cat => cat.name === statementRule.sender)
    const receiverCategory = selectedStatement.categories?.find(cat => cat.name === statementRule.receiver)
    const autoFields = []

    setForm(prev => {
      let changed = false
      const next = { ...prev }

      if (senderCategory && String(prev.sender_account_category_id) !== String(senderCategory.id)) {
        next.sender_account_category_id = String(senderCategory.id)
        next.sender_hfm_account_id = ''
        next.receiver_hfm_account_id = ''
        autoFields.push('sender_account_category_id')
        changed = true
      }

      if (receiverCategory && String(prev.receiver_account_category_id) !== String(receiverCategory.id)) {
        next.receiver_account_category_id = String(receiverCategory.id)
        next.receiver_hfm_account_id = ''
        autoFields.push('receiver_account_category_id')
        changed = true
      }

      return changed ? next : prev
    })

    if (autoFields.length) {
      markAutoFilled(...autoFields)
    }
  }, [selectedStatement, statementRule, markAutoFilled])

  useEffect(() => {
    if (!selectedStatement) return

    if (!form.sender_hfm_account_id || !selectedPair?.receiver_account) {
      setForm(prev => {
        if (!prev.receiver_hfm_account_id) return prev
        const next = { ...prev, receiver_hfm_account_id: '' }
        return next
      })
      return
    }

    const receiverId = String(selectedPair.receiver_account.id)
    if (String(form.receiver_hfm_account_id) === receiverId) return

    setForm(prev => ({ ...prev, receiver_hfm_account_id: receiverId }))
    markAutoFilled('receiver_hfm_account_id')
  }, [form.sender_hfm_account_id, form.receiver_hfm_account_id, selectedPair, selectedStatement, markAutoFilled])

  function handleInputChange(event) {
    const { name, value, type, checked } = event.target
    const nextValue = type === 'checkbox' ? checked : value

    setForm(prev => {
      const updated = { ...prev, [name]: nextValue }

      if (name === 'sender_company_id' && value === prev.receiver_company_id) {
        updated.receiver_company_id = ''
      }

      if (name === 'receiver_company_id' && value === prev.sender_company_id) {
        updated.sender_company_id = ''
      }

      if (name === 'financial_statement_id' && value === '') {
        updated.sender_account_category_id = ''
        updated.receiver_account_category_id = ''
        updated.sender_hfm_account_id = ''
        updated.receiver_hfm_account_id = ''
      }

      if (name === 'sender_hfm_account_id' && value === '') {
        updated.receiver_hfm_account_id = ''
      }

      return updated
    })
  }

  function resetForm() {
    setForm(emptyForm)
    setAutoFilledFields([])
    autoFillTimeouts.current.forEach(timeoutId => clearTimeout(timeoutId))
    autoFillTimeouts.current = []
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSaving(true)
    setStatus('')
    setError('')
    try {
      const payload = {
        financial_statement_id: Number(form.financial_statement_id),
        sender_company_id: Number(form.sender_company_id),
        receiver_company_id: Number(form.receiver_company_id),
        sender_account_category_id: Number(form.sender_account_category_id),
        sender_hfm_account_id: Number(form.sender_hfm_account_id),
        receiver_account_category_id: Number(form.receiver_account_category_id),
        receiver_hfm_account_id: Number(form.receiver_hfm_account_id),
        description: form.description || null,
        currency: form.currency,
        default_amount: form.default_amount === '' ? null : Number(form.default_amount),
        is_active: Boolean(form.is_active),
      }

      if (form.id) {
        await putJSON(`/api/templates/${form.id}`, payload)
        setStatus('Template updated.')
      } else {
        await postJSON('/api/templates', payload)
        setStatus('Template created.')
      }

      resetForm()
      setTableParams(prev => ({ ...prev, page: 1 }))
      await loadTemplates()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to save template.')
    } finally {
      setSaving(false)
    }
  }

  function startEdit(template) {
    setForm({
      id: template.id,
      financial_statement_id: template.financial_statement?.id ?? '',
      sender_company_id: template.sender_company?.id ?? '',
      receiver_company_id: template.receiver_company?.id ?? '',
      sender_account_category_id: template.sender_category?.id ?? '',
      sender_hfm_account_id: template.sender_account?.id ?? '',
      receiver_account_category_id: template.receiver_category?.id ?? '',
      receiver_hfm_account_id: template.receiver_account?.id ?? '',
      description: template.description ?? '',
      currency: template.currency || 'ZAR',
      default_amount: template.default_amount ?? '',
      is_active: Boolean(template.is_active),
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  async function removeTemplate(template) {
    if (!window.confirm(`Delete template #${template.id}?`)) return
    setError('')
    try {
      await deleteJSON(`/api/templates/${template.id}`)
      setStatus('Template deleted.')
      await loadTemplates()
    } catch (err) {
      setError(err.message || 'Unable to delete template.')
    }
  }

  async function handleGeneration(event) {
    event.preventDefault()
    setError('')
    if (!generation.period_id || !generation.financial_statement_id) {
      setError('Select a period and statement for generation.')
      return
    }
    setStatus('')
    try {
      const res = await postJSON(`/api/periods/${generation.period_id}/generate-transactions`, {
        financial_statement_id: Number(generation.financial_statement_id),
      })
      setStatus(`Generated ${res.created} new transactions for the selected period.`)
    } catch (err) {
      setError(err.message || 'Unable to generate transactions.')
    }
  }

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h1 className="h2 mb-0">Transaction Templates</h1>
          <small className="text-muted">Define recurring intercompany pairs.</small>
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {status && <div className="alert alert-success">{status}</div>}

      <div className="row g-4">
        <div className="col-12 col-lg-5">
          <div className="card">
            <div className="card-header telkom-accent">
              <strong>{form.id ? 'Edit Template' : 'New Template'}</strong>
            </div>
            <div className="card-body">
              {loading && !meta ? (
                <div>Loading metadata…</div>
              ) : (
                <form onSubmit={handleSubmit} className="vstack gap-3">
                  <div>
                    <label className="form-label">Financial Statement</label>
                    <select
                      className="form-select"
                      name="financial_statement_id"
                      value={form.financial_statement_id}
                      onChange={handleInputChange}
                      required
                    >
                      <option value="">Choose…</option>
                      {statements.map(stmt => (
                        <option key={stmt.id} value={stmt.id}>{stmt.label}</option>
                      ))}
                    </select>
                  </div>

                  <div className="row g-3">
                    <div className="col">
                      <label className="form-label">Sender Company</label>
                      <select
                        className="form-select"
                        name="sender_company_id"
                        value={form.sender_company_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {availableSenderCompanies.map(company => (
                          <option key={company.id} value={company.id}>{company.name}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col">
                      <label className="form-label">Receiver Company</label>
                      <select
                        className="form-select"
                        name="receiver_company_id"
                        value={form.receiver_company_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {availableReceiverCompanies.map(company => (
                          <option key={company.id} value={company.id}>{company.name}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="row g-3">
                    <div className="col">
                      <label className="form-label">Sender Category</label>
                      <select
                        className={`form-select ${isAutoFilled('sender_account_category_id') ? 'auto-filled' : ''}`}
                        name="sender_account_category_id"
                        value={form.sender_account_category_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {senderCategories.map(cat => (
                          <option key={cat.id} value={cat.id}>{cat.label}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col">
                      <label className="form-label">Receiver Category</label>
                      <select
                        className={`form-select ${isAutoFilled('receiver_account_category_id') ? 'auto-filled' : ''}`}
                        name="receiver_account_category_id"
                        value={form.receiver_account_category_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {receiverCategories.map(cat => (
                          <option key={cat.id} value={cat.id}>{cat.label}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div className="row g-3">
                    <div className="col">
                      <label className="form-label">Sender HFM Account</label>
                      <select
                        className="form-select"
                        name="sender_hfm_account_id"
                        value={form.sender_hfm_account_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {senderAccounts.map(acc => (
                          <option key={acc.id} value={acc.id}>{acc.name}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col">
                      <label className="form-label">Receiver HFM Account</label>
                      <select
                        className={`form-select ${isAutoFilled('receiver_hfm_account_id') ? 'auto-filled' : ''}`}
                        name="receiver_hfm_account_id"
                        value={form.receiver_hfm_account_id}
                        onChange={handleInputChange}
                        required
                      >
                        <option value="">Choose…</option>
                        {receiverAccounts.map(acc => (
                          <option key={acc.id} value={acc.id}>{acc.name}</option>
                        ))}
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="form-label">Description</label>
                    <textarea
                      className="form-control"
                      name="description"
                      value={form.description}
                      onChange={handleInputChange}
                      maxLength={255}
                      placeholder="e.g. Monthly receivable vs payable for services"
                    />
                    <small className="text-muted">Optional, max 255 characters.</small>
                  </div>

                  <div className="row g-3">
                    <div className="col-4">
                      <label className="form-label">Currency</label>
                      <input
                        type="text"
                        className="form-control text-uppercase"
                        name="currency"
                        value={form.currency}
                        onChange={handleInputChange}
                        maxLength={3}
                        required
                      />
                    </div>
                    <div className="col">
                      <label className="form-label">Default Amount</label>
                      <input
                        type="number"
                        step="0.01"
                        className="form-control"
                        name="default_amount"
                        value={form.default_amount}
                        onChange={handleInputChange}
                      />
                    </div>
                  </div>

                  <div className="form-check">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      name="is_active"
                      id="template-active"
                      checked={form.is_active}
                      onChange={handleInputChange}
                    />
                    <label className="form-check-label" htmlFor="template-active">
                      Active
                    </label>
                  </div>

                  <div className="d-flex gap-2">
                    <button type="submit" className="btn btn-primary" disabled={saving}>
                      {saving ? 'Saving…' : (form.id ? 'Update Template' : 'Create Template')}
                    </button>
                    {form.id && (
                      <button type="button" className="btn btn-outline-secondary" onClick={resetForm}>
                        Cancel
                      </button>
                    )}
                  </div>
                </form>
              )}
            </div>
          </div>

          <div className="card mt-4">
            <div className="card-header">
              <strong>Generate Period Transactions</strong>
            </div>
            <div className="card-body">
              <form className="vstack gap-3" onSubmit={handleGeneration}>
                <div>
                    <label className="form-label">Period</label>
                    <select
                      className="form-select"
                      value={generation.period_id}
                      onChange={e => setGeneration(g => ({ ...g, period_id: e.target.value }))}
                      disabled={availableGenerationPeriods.length === 0}
                    >
                      <option value="">Choose…</option>
                      {availableGenerationPeriods.map(period => (
                        <option key={period.id} value={period.id}>#{period.period_number ?? ''} - {period.label}</option>
                      ))}
                    </select>
                    {availableGenerationPeriods.length === 0 && (
                      <small className="text-danger">All periods are locked or closed. Unlock a period first.</small>
                    )}
                </div>
                <div>
                  <label className="form-label">Financial Statement</label>
                  <select
                    className="form-select"
                    value={generation.financial_statement_id}
                    onChange={e => setGeneration(g => ({ ...g, financial_statement_id: e.target.value }))}
                  >
                    <option value="">Choose…</option>
                    {statements.map(stmt => (
                      <option key={stmt.id} value={stmt.id}>{stmt.label}</option>
                    ))}
                  </select>
                </div>
                <button className="btn btn-outline-primary" type="submit" disabled={availableGenerationPeriods.length === 0}>
                  Generate
                </button>
              </form>
            </div>
          </div>
        </div>

        <div className="col-12 col-lg-7">
          <div className="card">
            <div className="card-header">
              <div className="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div className="d-flex align-items-center gap-2">
                  <strong>Templates</strong>
                  <small className="text-muted">{totalTemplates} total</small>
                </div>

              </div>
            </div>
              <div className="card-body">
                  <div className="d-flex flex-row gap-2 align-items-center justify-content-between mb-3">

                      <select
                          className="form-select form-select-sm"
                          value={tableParams.filterStatement}
                          onChange={e => handleTableParamsChange({ filterStatement: e.target.value }, true)}
                      >
                          <option value="">All statements</option>
                          {statements.map(stmt => (
                              <option key={stmt.id} value={stmt.id}>{stmt.label}</option>
                          ))}
                      </select>
                      <select
                          className="form-select form-select-sm"
                          value={tableParams.filterActive}
                          onChange={e => handleTableParamsChange({ filterActive: e.target.value }, true)}
                      >
                          <option value="">All</option>
                          <option value="1">Active</option>
                          <option value="0">Inactive</option>
                      </select>
                  </div>

                  <div className="d-flex flex-row gap-2 align-items-center justify-content-end mb-3">
                      <div>
                          <input
                              type="search"
                              className="form-control form-control-sm"
                              placeholder="Search..."
                              style={{ minWidth: '220px' }}
                              value={tableParams.search}
                              onChange={e => handleTableParamsChange({ search: e.target.value }, true)}
                          />
                      </div>
                  </div>
                  <div className="table-responsive">
                      <table className="table table-hover table-striped mb-0">
                          <thead className='thead'>
                          <tr>
                              <th role="button" onClick={() => toggleSort('created_at')}>ID {renderSortIndicator('created_at')}</th>
                              <th role="button" onClick={() => toggleSort('financial_statement')}>Statement {renderSortIndicator('financial_statement')}</th>
                              <th role="button" onClick={() => toggleSort('description')}>Description {renderSortIndicator('description')}</th>
                              <th role="button" onClick={() => toggleSort('sender_company')}>Sender → Receiver {renderSortIndicator('sender_company')}</th>
                              <th role="button" onClick={() => toggleSort('currency')}>Currency {renderSortIndicator('currency')}</th>
                              <th role="button" onClick={() => toggleSort('is_active')}>Active {renderSortIndicator('is_active')}</th>
                              <th></th>
                          </tr>
                          </thead>
                          <tbody>
                          {loadingTemplates && templates.length === 0 && (
                              <tr>
                                  <td colSpan="7" className="text-center py-4 text-muted">Loading templates…</td>
                              </tr>
                          )}
                          {!loadingTemplates && templates.length === 0 && (
                              <tr>
                                  <td colSpan="7" className="text-center py-4 text-muted">No templates yet.</td>
                              </tr>
                          )}
                          {templates.map(template => (
                              <tr key={template.id}>
                                  <td>#{template.id}</td>
                                  <td>{template.financial_statement?.label}</td>
                                  <td className="w-25">
                                      {template.description || <span className="text-muted">—</span>}
                                  </td>
                                  <td>
                                      <div className="fw-semibold">{template.sender_company?.name} → {template.receiver_company?.name}</div>
                                      <small className="text-muted">{template.sender_account?.name} / {template.receiver_account?.name}</small>
                                  </td>
                                  <td>
                                      {template.currency}{' '}
                                      {template.default_amount !== null && (
                                          <span className="text-muted">({template.default_amount.toLocaleString(undefined, { minimumFractionDigits: 2 })})</span>
                                      )}
                                  </td>
                                  <td>
                                      {template.is_active ? <span className="badge text-bg-success">Active</span> : <span className="badge text-bg-secondary">Inactive</span>}
                                  </td>
                                  <td className="text-end">
                                      <div className="btn-group btn-group-sm">
                                          <button className="btn btn-outline-primary me-1" onClick={() => startEdit(template)}>Edit</button>
                                          <button className="btn btn-outline-danger" onClick={() => removeTemplate(template)}>Delete</button>
                                      </div>
                                  </td>
                              </tr>
                          ))}
                          </tbody>
                      </table>
                  </div>
              </div>

            <div className="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div className="text-muted small">
                Page {currentPage} of {lastPage} • {totalTemplates} records
              </div>
              <div className="d-flex align-items-center gap-2">
                <select
                  className="form-select form-select-sm"
                  value={tableParams.perPage}
                  onChange={e => changePerPage(e.target.value)}
                >
                  {[10, 20, 50, 100].map(size => (
                    <option key={size} value={size}>{size} / page</option>
                  ))}
                </select>
                <div className="btn-group btn-group-sm">
                  <button
                    className="btn btn-outline-secondary"
                    disabled={currentPage <= 1 || loadingTemplates}
                    onClick={() => changePage(currentPage - 1)}
                  >
                    Previous
                  </button>
                  <button
                    className="btn btn-outline-secondary"
                    disabled={currentPage >= lastPage || loadingTemplates}
                    onClick={() => changePage(currentPage + 1)}
                  >
                    Next
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  )
}
