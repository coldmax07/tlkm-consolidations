import React, { useEffect, useMemo, useState } from 'react'
import { getJSON } from '../lib/http'
import { csrfToken } from '../lib/http'
import { notifyError, notifySuccess } from '../lib/notify'

function formatAmount(value, nature, currency = 'ZAR') {
  if (value === null || value === undefined) return '—'
  let number = Number(value)
  if (nature === 'PAYABLE' || nature === 'REVENUE') {
    number = -Math.abs(number)
  }
  const formatter = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  })
  const formatted = formatter.format(number)
  const needsBrackets = nature === 'PAYABLE' || nature === 'REVENUE'
  if (needsBrackets && number !== 0) {
    return `(${formatter.format(Math.abs(number))})`
  }
  return formatted
}

function varianceClass(value) {
  if (value === null || value === undefined) return ''
  const num = Number(value)
  if (num === 0) return ''
  return num > 0 ? 'text-success fw-semibold' : 'text-danger fw-semibold'
}

function formatDescription(value, limit = 50) {
  if (!value) return '—'
  return value.length > limit ? `${value.slice(0, limit)}…` : value
}

export default function ReportView({ statementSlug }) {
  const [data, setData] = useState(null)
  const [companyId, setCompanyId] = useState('')
  const [loading, setLoading] = useState(false)
  const [exporting, setExporting] = useState(false)
  const [error, setError] = useState('')

  const statementParam = statementSlug === 'balance-sheet' ? 'BALANCE_SHEET' : 'INCOME_STATEMENT'
  const isBalanceSheet = statementParam === 'BALANCE_SHEET'

  async function loadData(targetCompanyId) {
    setLoading(true)
    setError('')
    try {
      const params = new URLSearchParams()
      params.append('statement', statementParam)
      if (targetCompanyId) {
        params.append('company_id', targetCompanyId)
      }
      const res = await getJSON(`/api/reports?${params.toString()}`)
      setData(res)
      if (res?.meta?.is_admin) {
        if (targetCompanyId) {
          setCompanyId(String(targetCompanyId))
        } else {
          setCompanyId('')
        }
      } else {
        setCompanyId(res?.current_company?.id ? String(res.current_company.id) : '')
      }
    } catch (err) {
      setError(err?.data?.message || err.message || 'Failed to load report.')
      setData(null)
      await notifyError(err, 'Failed to load report')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadData(companyId)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [statementParam])

  const totals = data?.totals || {}
  const rows = data?.rows || []
  const isAdmin = Boolean(data?.meta?.is_admin)
  const companyName = data?.current_company?.name || '—'
  const requiresCompany = Boolean(data?.requires_company)
  const hasRows = rows.length > 0

  const canLoad = useMemo(() => {
    if (!isAdmin) return true
    return companyId !== ''
  }, [isAdmin, companyId])

  const handleCompanyChange = (value) => {
    setCompanyId(value)
    if (value) {
      loadData(value)
    }
  }

  async function handleExport() {
    if (isAdmin && !companyId) {
      const err = new Error('Select a company to export.')
      setError(err.message)
      await notifyError(err, 'Export blocked')
      return
    }
    setExporting(true)
    setError('')
    try {
      const params = new URLSearchParams()
      params.append('statement', statementParam)
      if (companyId) params.append('company_id', companyId)

      const res = await fetch(`/api/reports/export?${params.toString()}`, {
        method: 'GET',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
        },
      })

      if (!res.ok) {
        const text = await res.text()
        throw new Error(text || 'Failed to export report')
      }

      const blob = await res.blob()
      const filename = `report-${statementParam.toLowerCase()}-${companyId || 'company'}-${data?.period?.label || 'period'}.xlsx`
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      await notifySuccess('Excel export downloaded.')
    } catch (err) {
      setError(err.message || 'Failed to export report.')
      await notifyError(err, 'Excel export failed')
    } finally {
      setExporting(false)
    }
  }

  async function handleExportPdf() {
    if (isAdmin && !companyId) {
      const err = new Error('Select a company to export.')
      setError(err.message)
      await notifyError(err, 'Export blocked')
      return
    }
    setExporting(true)
    setError('')
    try {
      const params = new URLSearchParams()
      params.append('statement', statementParam)
      if (companyId) params.append('company_id', companyId)

      const res = await fetch(`/api/reports/export-pdf?${params.toString()}`, {
        method: 'GET',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
        },
      })

      if (!res.ok) {
        const text = await res.text()
        throw new Error(text || 'Failed to export report')
      }

      const blob = await res.blob()
      const filename = `report-${statementParam.toLowerCase()}-${companyId || 'company'}-${data?.period?.label || 'period'}.pdf`
      const url = window.URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
      await notifySuccess('PDF export downloaded.')
    } catch (err) {
      setError(err.message || 'Failed to export report.')
      await notifyError(err, 'PDF export failed')
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="d-flex flex-column gap-3">
      <div className="d-flex justify-content-between flex-wrap align-items-center">
        <div>
          <h1 className="h3 mb-1">{isBalanceSheet ? 'Balance Sheet Report' : 'Income Statement Report'}</h1>
          <div className="text-muted">Active period: {data?.period?.label ?? '—'}</div>
          <div className="text-muted">Company: {companyName}</div>
        </div>
        {isAdmin && (
          <div className="d-flex align-items-center gap-2">
            <label className="form-label mb-0">Select Company</label>
            <select
              className="form-select"
              value={companyId}
              onChange={e => handleCompanyChange(e.target.value)}
            >
              <option value="">Choose…</option>
              {(data?.meta?.companies ?? []).map(company => (
                <option key={company.id} value={company.id}>{company.name}</option>
              ))}
            </select>
          </div>
        )}

      </div>

      {error && <div className="alert alert-danger mb-0">{error}</div>}
      {requiresCompany && (
        <div className="alert alert-warning mb-0">Select a company to view reports.</div>
      )}
      {loading && !hasRows && <div className="alert alert-info mb-0">Loading report…</div>}
      {!loading && !error && rows.length === 0 && (
        <div className="alert alert-secondary mb-0">No records for the active period.</div>
      )}

      {hasRows && (
        <>
          <TotalsCard
            title={isBalanceSheet ? 'Net Working Capital' : 'Net Income'}
            items={isBalanceSheet
              ? [
                  { label: 'Receivable', value: totals.receivable, nature: 'RECEIVABLE' },
                  { label: 'Payable', value: totals.payable, nature: 'PAYABLE' },
                  { label: 'Variance', value: totals.net_variance, nature: 'RECEIVABLE', highlightVariance: true },
                ]
              : [
                  { label: 'Revenue', value: totals.revenue, nature: 'REVENUE' },
                  { label: 'Expense', value: totals.expense, nature: 'EXPENSE' },
                  { label: 'Variance', value: totals.net_variance, nature: 'REVENUE', highlightVariance: true },
                ]
            }
            transactions={totals.transactions}
          />

          <TotalsCard
            title="Confirmations"
            items={[
              { label: 'Current Company Total', value: totals.current_total, nature: 'RECEIVABLE' },
              { label: 'Counterparty Total', value: totals.counterparty_total, nature: 'PAYABLE' },
              { label: 'Variance', value: totals.confirmations_variance, nature: null, highlightVariance: true },
            ]}
          />

          <div className="card">
            <div className="card-header d-flex justify-content-between align-items-center">
              <strong>Transactions</strong>

                <div className="d-flex gap-2">
                    <button className="btn btn-primary" onClick={handleExport} disabled={exporting || (isAdmin && !companyId)}>
                        {exporting ? 'Exporting…' : 'Export Excel'}
                    </button>
                    <button className="btn btn-success" onClick={handleExportPdf} disabled={exporting || (isAdmin && !companyId)}>
                        {exporting ? 'Exporting…' : 'Export PDF'}
                    </button>
                </div>
            </div>

            <div className="card-body position-relative">
                {loading && (
                    <div
                        className="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white"
                        style={{ zIndex: 2, opacity: 0.55 }}
                    >
                        <div className="spinner-border text-primary" role="status" aria-label="Loading report">
                            <span className="visually-hidden">Loading...</span>
                        </div>
                    </div>
                )}
                <div className="table-responsive">
                    <table className="table table-striped mb-0">
                        <thead>
                        <tr className="table-primary">
                            <th colSpan={9}></th>
                            <th colSpan={4} className="text-center text-primary bg-primary text-white">Current Company</th>
                            {isAdmin && (
                                <th colSpan={4} className="text-center text-success bg-success text-white">Counter-part Company</th>
                            )}
                        </tr>
                        <tr>
                            <th>HFM Account</th>
                            <th>Trading Partner</th>
                            <th>Description</th>
                            <th>Adjustment (Sender)</th>
                            <th>Final Amount (Sender)</th>
                            <th className="">Current Company Amount</th>
                            <th className="">Counterparty Amount</th>
                            <th>Variance</th>
                            <th>Agreement</th>
                            <th className="bg-primary-subtle">Prepared By</th>
                            <th className="bg-primary-subtle">Prepared At</th>
                            <th className="bg-primary-subtle">Reviewed By</th>
                            <th className="bg-primary-subtle">Reviewed At</th>
                            {isAdmin && (
                                <>
                                    <th className="bg-success-subtle">Prepared By</th>
                                    <th className="bg-success-subtle">Prepared At</th>
                                    <th className="bg-success-subtle">Reviewed By</th>
                                    <th className="bg-success-subtle">Reviewed At</th>
                                </>
                            )}
                        </tr>
                        </thead>
                        <tbody>
                        {rows.map(row => (
                            <tr key={row.transaction_id}>
                                <td>{row.hfm_account || '—'}</td>
                                <td>{row.trading_partner || '—'}</td>
                                <td>{formatDescription(row.description)}</td>
                                <td>{formatAmount(row.adjustment_amount, mapNatureForFormatting(row.current_nature, isBalanceSheet))}</td>
                                <td>{formatAmount(row.final_amount, mapNatureForFormatting(row.current_nature, isBalanceSheet))}</td>
                                <td className="">{formatAmount(row.current_amount, mapNatureForFormatting(row.current_nature, isBalanceSheet))}</td>
                                <td className="">{formatAmount(row.counterparty_amount, mapNatureForFormatting(row.counterparty_nature, isBalanceSheet))}</td>
                                <td className={varianceClass(row.variance)}>
                                    {formatAmount(row.variance, null)}
                                </td>
                                <td>{row.agreement?.label || '—'}</td>
                                <td className="bg-primary-subtle">{row.prepared_by || '—'}</td>
                                <td className="bg-primary-subtle">{row.prepared_at ? new Date(row.prepared_at).toLocaleString() : '—'}</td>
                                <td className="bg-primary-subtle">{row.reviewed_by || '—'}</td>
                                <td className="bg-primary-subtle">{row.reviewed_at ? new Date(row.reviewed_at).toLocaleString() : '—'}</td>
                                {isAdmin && (
                                    <>
                                        <td className="bg-success-subtle">{row.counter_prepared_by || '—'}</td>
                                        <td className="bg-success-subtle">{row.counter_prepared_at ? new Date(row.counter_prepared_at).toLocaleString() : '—'}</td>
                                        <td className="bg-success-subtle">{row.counter_reviewed_by || '—'}</td>
                                        <td className="bg-success-subtle">{row.counter_reviewed_at ? new Date(row.counter_reviewed_at).toLocaleString() : '—'}</td>
                                    </>
                                )}
                            </tr>
                        ))}
                        </tbody>
                    </table>
                </div>

            </div>

          </div>
        </>
      )}
    </div>
  )
}

function Metric({ label, value, nature, plain = false, highlightVariance = false }) {
  return (
    <div>
      <div className="text-muted small">{label}</div>
      <div className={`fs-5 fw-semibold ${highlightVariance ? varianceClass(value) : ''}`}>
        {plain ? value ?? '—' : formatAmount(value ?? 0, nature)}
      </div>
    </div>
  )
}

function mapNatureForFormatting(nature, isBalanceSheet) {
  if (!nature) return null
  if (isBalanceSheet && nature === 'PAYABLE') return 'PAYABLE'
  if (!isBalanceSheet && nature === 'EXPENSE') return 'EXPENSE'
  return nature
}

function TotalsCard({ title, items, transactions }) {
  return (
    <div className="card">
      <div className="card-header">
        <strong>{title}</strong>
      </div>
      <div className="card-body d-flex flex-wrap gap-4">
        {items.map(item => (
            <Metric
              key={item.label}
              label={item.label}
              value={item.value}
              nature={item.nature}
              plain={item.plain}
              highlightVariance={item.highlightVariance}
            />
        ))}
        {transactions !== undefined && (
          <Metric label="Transactions" value={transactions} plain />
        )}
      </div>
    </div>
  )
}
