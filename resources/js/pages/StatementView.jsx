import React, { useEffect, useMemo, useState } from 'react'
import { getJSON, patchJSON, postJSON } from '../lib/http'
import ThreadDrawer from '../components/ThreadDrawer'
import { notifyError, notifySuccess, promptReason } from '../lib/notify'

function formatAmount(amount, currency, { accounting = false } = {}) {
  if (amount === null || amount === undefined || amount === '') return '—'
  const numeric = Number(amount)
  const formatter = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'ZAR',
    minimumFractionDigits: 2,
  })
  if (accounting && numeric !== 0) {
    return `(${formatter.format(Math.abs(numeric))})`
  }
  return formatter.format(numeric)
}

function formatDescription(value, limit = 50) {
  if (!value) return '—'
  return value.length > limit ? `${value.slice(0, limit)}…` : value
}

function toNumberOrNull(value) {
  if (value === '' || value === null || value === undefined) return null
  const parsed = Number(value)
  return Number.isFinite(parsed) ? parsed : null
}

function calculateFinalAmount(amount, adjustmentAmount) {
  const baseAmount = toNumberOrNull(amount)
  if (baseAmount === null) return null
  const adjustment = toNumberOrNull(adjustmentAmount) ?? 0
  return baseAmount - adjustment
}

function LegCell({ leg, currency, children }) {
  if (!leg) return <span className="text-muted">n/a</span>
  const isPayable = leg.account?.category?.name === 'PAYABLE'
  const amountLabel = formatAmount(leg.amount ?? 0, currency, { accounting: isPayable })
  return (
    <div>
      <div className="fw-semibold">{leg.company?.name}</div>
      <div className="small text-muted">{leg.account?.name}</div>
      <div className="small fw-semibold text-brand-primary">{amountLabel}</div>
      <div className="small text-muted">{leg.status?.label}</div>
      {leg.agreement_status?.label && (
        <div className="small text-muted">Agreement: {leg.agreement_status.label}</div>
      )}
      {children}
    </div>
  )
}

export default function StatementView({ statementSlug }) {
  const [meta, setMeta] = useState(null)
  const [filters, setFilters] = useState({
    financial_statement_id: '',
    period_id: '',
    company_id: '',
    counterparty_company_id: '',
    status_id: '',
    agreement_status_id: '',
    account_category_id: '',
    hfm_account_id: '',
  })
  const [tableParams, setTableParams] = useState({
    page: 1,
    per_page: 10,
    sort_by: 'sender_company',
    sort_dir: 'asc',
  })
  const [data, setData] = useState(null)
  const [loadingMeta, setLoadingMeta] = useState(true)
  const [loadingData, setLoadingData] = useState(false)
  const [error, setError] = useState('')
  const [legAmounts, setLegAmounts] = useState({})
  const [legAdjustments, setLegAdjustments] = useState({})
  const [legAgreements, setLegAgreements] = useState({})
  const [legReasons, setLegReasons] = useState({})
  const [actionLoading, setActionLoading] = useState({})
  const [reloadVersion, setReloadVersion] = useState(0)
  const [threadTransactionId, setThreadTransactionId] = useState(null)
  const [threadOpen, setThreadOpen] = useState(false)

  useEffect(() => {
    async function fetchMeta() {
      try {
        const metaResponse = await getJSON('/api/statements/meta')
        setMeta(metaResponse)

        const statement = metaResponse.financial_statements.find(fs => fs.slug === statementSlug)
        const defaultStatementId = statement?.id ?? metaResponse.financial_statements?.[0]?.id ?? ''

        const activePeriod = metaResponse.periods?.find(period => (
          period && !period.is_locked && !(period.fiscal_year?.closed_at)
        ))
        const defaultPeriod = activePeriod?.id ?? metaResponse.periods?.[0]?.id ?? ''

        setFilters(prev => ({
          ...prev,
          financial_statement_id: defaultStatementId,
          period_id: defaultPeriod,
        }))
      } catch (err) {
        setError(err.message || 'Failed to load reference data.')
        await notifyError(err, 'Failed to load filters')
      } finally {
        setLoadingMeta(false)
      }
    }

    fetchMeta()
  }, [statementSlug])

  useEffect(() => {
    if (!filters.financial_statement_id || !filters.period_id) return
    async function fetchData() {
      setLoadingData(true)
      setError('')
      try {
        const params = new URLSearchParams()
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== '' && value !== null && value !== undefined) {
            params.append(key, value)
          }
        })
        params.append('page', tableParams.page)
        params.append('per_page', tableParams.per_page)
        params.append('sort_by', tableParams.sort_by)
        params.append('sort_dir', tableParams.sort_dir)
        const response = await getJSON(`/api/statements?${params.toString()}`)
        setData(response)
      } catch (err) {
        setError(err?.data?.message || err.message || 'Unable to load statement data.')
        await notifyError(err, 'Unable to load statement data')
      } finally {
        setLoadingData(false)
      }
    }

    fetchData()
  }, [filters, reloadVersion, tableParams])

  useEffect(() => {
    if (!data?.transactions) {
      setLegAmounts({})
      setLegAdjustments({})
      setLegAgreements({})
      setLegReasons({})
      return
    }
    const next = {}
    const nextAdjustments = {}
    const nextAgreement = {}
    const nextReasons = {}
    data.transactions.forEach(tx => {
      if (tx.legs?.sender?.id) {
        next[tx.legs.sender.id] = tx.legs.sender.amount ?? ''
        nextAdjustments[tx.legs.sender.id] = tx.legs.sender.adjustment_amount ?? 0
      }
      if (tx.legs?.receiver?.id) {
        next[tx.legs.receiver.id] = tx.legs.receiver.amount ?? ''
        nextAgreement[tx.legs.receiver.id] = tx.legs.receiver.agreement_status?.id ?? ''
        nextReasons[tx.legs.receiver.id] = tx.legs.receiver.disagree_reason ?? ''
      }
    })
    setLegAmounts(next)
    setLegAdjustments(nextAdjustments)
    setLegAgreements(nextAgreement)
    setLegReasons(nextReasons)
  }, [data])

  const statementMeta = useMemo(() => {
    if (!meta) return null
    return meta.financial_statements.find(fs => fs.id === Number(filters.financial_statement_id)) || null
  }, [meta, filters.financial_statement_id])

  const selectedCategory = useMemo(() => {
    if (!meta || !filters.account_category_id) return null
    return meta.account_categories.find(cat => String(cat.id) === String(filters.account_category_id)) || null
  }, [meta, filters.account_category_id])

  const transactionPage = data?.pagination?.page ?? tableParams.page
  const transactionLastPage = data?.pagination?.last_page ?? 1
  const transactionTotal = data?.pagination?.total ?? (data?.transactions?.length ?? 0)
  const agreementMap = useMemo(() => {
    if (!meta?.agreement_statuses) return {}
    return meta.agreement_statuses.reduce((acc, status) => {
      acc[String(status.id)] = status
      return acc
    }, {})
  }, [meta?.agreement_statuses])

  function handleFilterChange(event) {
    const { name, value } = event.target
    setFilters(prev => ({
      ...prev,
      [name]: value,
      ...(name === 'account_category_id' ? { hfm_account_id: '' } : {}),
    }))
    setTableParams(prev => ({ ...prev, page: 1 }))
  }

  function toggleTransactionSort(field) {
    setTableParams(prev => ({
      ...prev,
      page: 1,
      sort_by: field,
      sort_dir: prev.sort_by === field && prev.sort_dir === 'asc' ? 'desc' : 'asc',
    }))
  }

  function sortIndicator(field) {
    if (tableParams.sort_by !== field) return null
    return tableParams.sort_dir === 'asc' ? '▲' : '▼'
  }

  function changeTransactionPage(nextPage) {
    setTableParams(prev => ({ ...prev, page: Math.max(1, nextPage) }))
  }

  function changeTransactionPerPage(value) {
    setTableParams(prev => ({ ...prev, per_page: Number(value), page: 1 }))
  }

  function receiverDecisionReady(legId) {
    const statusId = legAgreements[legId]
    const status = agreementMap[statusId]
    const amount = legAmounts[legId]
    if (amount === undefined || amount === null || amount === '') return false
    if (!status || status.name === 'UNKNOWN') return false
    const reason = (legReasons[legId] ?? '').trim()
    if (status.name === 'AGREE' && reason) return false
    if (status.name === 'DISAGREE' && !reason) return false
    return true
  }

  function setLegLoading(legId, value) {
    setActionLoading(prev => ({ ...prev, [legId]: value }))
  }

  function handleAmountInputChange(legId, value) {
    setLegAmounts(prev => ({ ...prev, [legId]: value }))
  }

  function handleAdjustmentInputChange(legId, value) {
    setLegAdjustments(prev => ({ ...prev, [legId]: value }))
  }

  function handleAgreementChange(legId, value) {
    setLegAgreements(prev => ({ ...prev, [legId]: value }))
    const status = agreementMap[value]
    if (status?.name === 'AGREE') {
      setLegReasons(prev => ({ ...prev, [legId]: '' }))
    }
  }

  function handleReasonChange(legId, value) {
    setLegReasons(prev => ({ ...prev, [legId]: value }))
  }

  async function saveSenderAmount(leg) {
    if (!leg) return
    const newAmount = Number(legAmounts[leg.id])
    const adjustmentAmount = toNumberOrNull(legAdjustments[leg.id])
    setLegLoading(leg.id, true)
    setError('')
    try {
      await patchJSON(`/api/legs/${leg.id}`, {
        amount: newAmount,
        adjustment_amount: adjustmentAmount,
      })
      await notifySuccess('Sender amount updated.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to save amount.')
      await notifyError(err, 'Unable to save sender amount')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function submitLeg(leg) {
    if (!leg) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/submit`, {})
      await notifySuccess('Sender leg submitted for review.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to submit leg.')
      await notifyError(err, 'Unable to submit sender leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function approveLeg(leg) {
    if (!leg) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/approve`, {})
      await notifySuccess('Sender leg approved.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to approve leg.')
      await notifyError(err, 'Unable to approve sender leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function rejectLeg(leg) {
    if (!leg) return
    const reason = await promptReason({ title: 'Reject Sender Leg', text: 'Provide a rejection reason.' })
    if (!reason) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/reject`, { reason })
      await notifySuccess('Sender leg rejected.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to reject leg.')
      await notifyError(err, 'Unable to reject sender leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function saveReceiverLeg(leg) {
    if (!leg) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await patchJSON(`/api/legs/${leg.id}/receiver`, {
        amount: Number(legAmounts[leg.id]),
        agreement_status_id: legAgreements[leg.id],
        disagree_reason: legReasons[leg.id] || null,
      })
      await notifySuccess('Receiver leg updated.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to save receiver leg.')
      await notifyError(err, 'Unable to save receiver leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function submitReceiverLeg(leg) {
    if (!leg) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/receiver/submit`, {})
      await notifySuccess('Receiver leg submitted for review.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to submit receiver leg.')
      await notifyError(err, 'Unable to submit receiver leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function approveReceiverLeg(leg) {
    if (!leg) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/receiver/approve`, {})
      await notifySuccess('Receiver leg approved.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to approve receiver leg.')
      await notifyError(err, 'Unable to approve receiver leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  async function rejectReceiverLeg(leg) {
    if (!leg) return
    const reason = await promptReason({ title: 'Reject Receiver Leg', text: 'Provide a rejection reason.' })
    if (!reason) return
    setLegLoading(leg.id, true)
    setError('')
    try {
      await postJSON(`/api/legs/${leg.id}/receiver/reject`, { reason })
      await notifySuccess('Receiver leg rejected.')
      setReloadVersion(v => v + 1)
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to reject receiver leg.')
      await notifyError(err, 'Unable to reject receiver leg')
    } finally {
      setLegLoading(leg.id, false)
    }
  }

  function openThread(transactionId) {
    setThreadTransactionId(transactionId)
    setThreadOpen(true)
  }

  function closeThread() {
    setThreadOpen(false)
  }

  return (
    <>
      <div className="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <div>
          <h1 className="h2 mb-0">{statementMeta?.label || 'Statement'}</h1>
          <small className="text-muted">Read-only snapshot of intercompany legs for the selected period.</small>
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}

      <div className="card mb-4">
        <div className="card-body">
          {loadingMeta ? (
            <div>Loading filters…</div>
          ) : (
            <div className="row g-3">
              <div className="col-12 col-md-3">
                <label className="form-label">Period</label>
                <select
                  className="form-select"
                  name="period_id"
                  value={filters.period_id}
                  onChange={handleFilterChange}
                >
                  {meta?.periods?.map(period => (
                    <option key={period.id} value={period.id}>#{period.period_number ?? ''} - {period.label}</option>
                  ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">Company</label>
                <select
                  className="form-select"
                  name="company_id"
                  value={filters.company_id}
                  onChange={handleFilterChange}
                >
                  <option value="">All</option>
                  {meta?.companies?.map(company => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                  ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">Counterparty</label>
                <select
                  className="form-select"
                  name="counterparty_company_id"
                  value={filters.counterparty_company_id}
                  onChange={handleFilterChange}
                >
                  <option value="">All</option>
                  {meta?.companies?.map(company => (
                    <option key={company.id} value={company.id}>{company.name}</option>
                  ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">Status</label>
                <select
                  className="form-select"
                  name="status_id"
                  value={filters.status_id}
                  onChange={handleFilterChange}
                >
                  <option value="">All</option>
                  {meta?.leg_statuses?.map(status => (
                    <option key={status.id} value={status.id}>{status.display_label}</option>
                  ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">Agreement</label>
                <select
                  className="form-select"
                  name="agreement_status_id"
                  value={filters.agreement_status_id}
                  onChange={handleFilterChange}
                >
                  <option value="">All</option>
                  {meta?.agreement_statuses?.map(status => (
                    <option key={status.id} value={status.id}>{status.display_label}</option>
                  ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">Account Category</label>
                <select
                  className="form-select"
                  name="account_category_id"
                  value={filters.account_category_id}
                  onChange={handleFilterChange}
                >
                  <option value="">All</option>
                  {meta?.account_categories
                    ?.filter(cat => cat.financial_statement_id === Number(filters.financial_statement_id))
                    ?.map(cat => (
                      <option key={cat.id} value={cat.id}>{cat.label}</option>
                    ))}
                </select>
              </div>

              <div className="col-12 col-md-3">
                <label className="form-label">HFM Account</label>
                <select
                  className="form-select"
                  name="hfm_account_id"
                  value={filters.hfm_account_id}
                  onChange={handleFilterChange}
                  disabled={!selectedCategory}
                >
                  <option value="">All</option>
                  {selectedCategory?.accounts?.map(account => (
                    <option key={account.id} value={account.id}>{account.name}</option>
                  ))}
                </select>
              </div>
            </div>
          )}
        </div>
      </div>

      <div className="card mb-4">
        <div className="card-body d-flex justify-content-between flex-wrap">
          <div>
            <div className="text-muted small">Sender Total</div>
            <div className="fs-5">{formatAmount(data?.totals?.sender ?? 0, data?.transactions?.[0]?.currency)}</div>
          </div>
          <div>
            <div className="text-muted small">Receiver Total</div>
            <div className="fs-5">{formatAmount(data?.totals?.receiver ?? 0, data?.transactions?.[0]?.currency)}</div>
          </div>
          <div>
            <div className="text-muted small">Variance</div>
            <div className="fs-5">{formatAmount(data?.totals?.variance ?? 0, data?.transactions?.[0]?.currency)}</div>
          </div>
          <div>
            <div className="text-muted small">Transactions</div>
            <div className="fs-5">{data?.totals?.transactions ?? 0}</div>
          </div>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <strong>Transactions</strong>
        </div>
        {loadingData && !data ? (
          <div className="card-body">Loading statement…</div>
        ) : (
          <>
            <div className="card-body position-relative">
                {loadingData && (
                    <div
                        className="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white"
                        style={{ zIndex: 2, opacity: 0.55 }}
                    >
                        <div className="spinner-border text-primary" role="status" aria-label="Loading statement">
                            <span className="visually-hidden">Loading...</span>
                        </div>
                    </div>
                )}
                <div className="table-responsive">
                    <table className="table table-hover table-striped mb-0">
                        <thead>
                        <tr  className='table-primary'>
                            <th scope='col' role="button" onClick={() => toggleTransactionSort('sender_company')}>Pair {sortIndicator('sender_company')}</th>
                            <th>Description</th>
                            <th role="button" onClick={() => toggleTransactionSort('sender_company')}>Sender {sortIndicator('sender_company')}</th>
                            <th role="button" onClick={() => toggleTransactionSort('receiver_company')}>Receiver {sortIndicator('receiver_company')}</th>
                            <th role="button" onClick={() => toggleTransactionSort('variance')}>Variance {sortIndicator('variance')}</th>
                        </tr>
                        </thead>
                        <tbody>
                        {(data?.transactions?.length ?? 0) === 0 && (
                            <tr>
                                <td colSpan="5" className="text-center py-4 text-muted">No transactions for this filter.</td>
                            </tr>
                        )}
                        {data?.transactions?.map(tx => {
                  const senderLeg = tx.legs.sender
                  const receiverLeg = tx.legs.receiver
                  const senderAmountValue = senderLeg ? (legAmounts[senderLeg.id] ?? senderLeg.amount ?? '') : ''
                  const senderAdjustmentValue = senderLeg ? (legAdjustments[senderLeg.id] ?? senderLeg.adjustment_amount ?? 0) : ''
                  const senderFinalAmountValue = senderLeg ? calculateFinalAmount(senderAmountValue, senderAdjustmentValue) : null
                  const receiverAmountValue = receiverLeg ? (legAmounts[receiverLeg.id] ?? receiverLeg.amount ?? '') : ''
                  const receiverAgreementValue = receiverLeg ? (legAgreements[receiverLeg.id] ?? receiverLeg.agreement_status?.id ?? '') : ''
                  const receiverReasonValue = receiverLeg ? (legReasons[receiverLeg.id] ?? receiverLeg.disagree_reason ?? '') : ''
                  const receiverAgreementMeta = agreementMap[receiverAgreementValue]
                  const receiverReasonDisabled = receiverAgreementMeta?.name === 'AGREE'
                  const senderLoading = senderLeg ? actionLoading[senderLeg.id] : false
                  const receiverLoading = receiverLeg ? actionLoading[receiverLeg.id] : false
                  const senderCanEdit = Boolean(senderLeg?.permissions?.edit)
                  const senderCanReview = Boolean(senderLeg?.permissions?.review)
                  const receiverCanEdit = Boolean(receiverLeg?.permissions?.edit)
                  const receiverCanSubmit = Boolean(receiverLeg?.permissions?.submit)
                  const receiverCanReview = Boolean(receiverLeg?.permissions?.review)
                  const receiverActionsDisabled = !receiverLeg || !receiverDecisionReady(receiverLeg.id)

                            return (
                                <tr key={tx.transaction_id}>
                                    <td>
                                        <div className="fw-semibold">{tx.sender_company?.name} → {tx.receiver_company?.name}</div>
                                        <div className="small text-muted">{tx.currency}</div>
                                        <button
                                            type="button"
                                            className="btn btn-link btn-sm ps-0"
                                            onClick={() => openThread(tx.transaction_id)}
                                        >
                                            Comments
                                        </button>
                                    </td>
                                    <td>{formatDescription(tx.description)}</td>
                                    <td className="align-top">
                                        <LegCell leg={senderLeg} currency={tx.currency}>
                                            {senderLeg && (
                                                <>
                                                    <div className="small text-muted">
                                                        Adjustment: {formatAmount(senderAdjustmentValue, tx.currency)}
                                                    </div>
                                                    <div className="small fw-semibold">
                                                        Final Amount: {formatAmount(senderFinalAmountValue, tx.currency)}
                                                    </div>
                                                </>
                                            )}
                                            {(senderCanEdit || senderCanReview) && (
                                                <div className="mt-2 vstack gap-2">
                                                    {senderCanEdit && (
                                                        <div className="d-flex flex-wrap gap-2 align-items-center">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                className="form-control form-control-sm"
                                                                style={{ maxWidth: '140px' }}
                                                                value={senderAmountValue}
                                                                onChange={e => handleAmountInputChange(senderLeg.id, e.target.value)}
                                                            />
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                className="form-control form-control-sm"
                                                                style={{ maxWidth: '140px' }}
                                                                placeholder="Adjustment"
                                                                value={senderAdjustmentValue}
                                                                onChange={e => handleAdjustmentInputChange(senderLeg.id, e.target.value)}
                                                            />
                                                            <button
                                                                className="btn btn-sm btn-outline-primary"
                                                                type="button"
                                                                disabled={senderLoading}
                                                                onClick={() => saveSenderAmount(senderLeg)}
                                                            >
                                                                Save
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-primary"
                                                                type="button"
                                                                disabled={senderLoading}
                                                                onClick={() => submitLeg(senderLeg)}
                                                            >
                                                                Send for review
                                                            </button>
                                                        </div>
                                                    )}
                                                    {senderCanReview && (
                                                        <div className="d-flex flex-wrap gap-2">
                                                            <button
                                                                className="btn btn-sm btn-success"
                                                                type="button"
                                                                disabled={senderLoading}
                                                                onClick={() => approveLeg(senderLeg)}
                                                            >
                                                                Approve
                                                            </button>
                                                            <button
                                                                className="btn btn-sm btn-outline-danger"
                                                                type="button"
                                                                disabled={senderLoading}
                                                                onClick={() => rejectLeg(senderLeg)}
                                                            >
                                                                Reject
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </LegCell>
                                    </td>
                                    <td className="align-top">
                                        <LegCell leg={receiverLeg} currency={tx.currency}>
                                            {(receiverCanEdit || receiverCanReview) && (
                                                <div className="mt-2 vstack gap-2">
                              {receiverCanEdit && (
                                <>
                                  <div className="d-flex flex-wrap gap-2 align-items-center">
                                    <input
                                      type="number"
                                                                    step="0.01"
                                                                    className="form-control form-control-sm"
                                                                    style={{ maxWidth: '140px' }}
                                                                    value={receiverAmountValue}
                                                                    onChange={e => handleAmountInputChange(receiverLeg.id, e.target.value)}
                                                                />
                                    <select
                                      className="form-select form-select-sm"
                                      style={{ maxWidth: '160px' }}
                                      value={receiverAgreementValue}
                                      onChange={e => handleAgreementChange(receiverLeg.id, e.target.value)}
                                    >
                                      <option value="">Agreement…</option>
                                      {meta?.agreement_statuses?.map(option => (
                                                                        <option key={option.id} value={option.id}>{option.display_label}</option>
                                                                    ))}
                                                                </select>
                                                                <button
                                                                    className="btn btn-sm btn-outline-primary"
                                                                    type="button"
                                                                    disabled={receiverLoading}
                                                                    onClick={() => saveReceiverLeg(receiverLeg)}
                                    >
                                      Save
                                    </button>
                                    <button
                                      className="btn btn-sm btn-primary"
                                      type="button"
                                      disabled={receiverLoading || receiverActionsDisabled}
                                      onClick={() => submitReceiverLeg(receiverLeg)}
                                    >
                                      Send for review
                                    </button>
                                  </div>
                                  <textarea
                                    className="form-control form-control-sm"
                                    placeholder="Disagreement reason"
                                    disabled={receiverReasonDisabled}
                                    value={receiverReasonValue}
                                    onChange={e => handleReasonChange(receiverLeg.id, e.target.value)}
                                  />
                                                        </>
                                                    )}
                              {receiverCanReview && (
                                <div className="d-flex flex-wrap gap-2">
                                  <button
                                    className="btn btn-sm btn-success"
                                    type="button"
                                    disabled={receiverLoading || receiverActionsDisabled}
                                    onClick={() => approveReceiverLeg(receiverLeg)}
                                  >
                                    Approve
                                  </button>
                                  <button
                                    className="btn btn-sm btn-outline-danger"
                                    type="button"
                                    disabled={receiverLoading || receiverActionsDisabled}
                                    onClick={() => rejectReceiverLeg(receiverLeg)}
                                  >
                                    Reject
                                  </button>
                                </div>
                              )}
                                                </div>
                                            )}
                                        </LegCell>
                                    </td>
                                    <td>{formatAmount(tx.variance, tx.currency)}</td>
                                </tr>
                            )
                        })}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div className="text-muted small">
                Page {transactionPage} of {transactionLastPage} • {transactionTotal} transactions
              </div>
              <div className="d-flex align-items-center gap-2">
                <select
                  className="form-select form-select-sm"
                  value={tableParams.per_page}
                  onChange={e => changeTransactionPerPage(e.target.value)}
                >
                  {[10, 20, 50, 100].map(size => (
                    <option key={size} value={size}>{size} / page</option>
                  ))}
                </select>
                <div className="btn-group btn-group-sm">
                  <button
                    className="btn btn-outline-secondary"
                    disabled={transactionPage <= 1 || loadingData}
                    onClick={() => changeTransactionPage(transactionPage - 1)}
                  >
                    Previous
                  </button>
                  <button
                    className="btn btn-outline-secondary"
                    disabled={transactionPage >= transactionLastPage || loadingData}
                    onClick={() => changeTransactionPage(transactionPage + 1)}
                  >
                    Next
                  </button>
                </div>
              </div>
            </div>
          </>
        )}
      </div>
      <ThreadDrawer
        transactionId={threadTransactionId}
        open={threadOpen}
        onClose={closeThread}
      />
    </>
  )
}
