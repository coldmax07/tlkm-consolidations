import React, { useEffect, useMemo, useState } from 'react'
import Highcharts from 'highcharts'
import Exporting from 'highcharts/modules/exporting'

if (typeof Exporting === 'function') {
  Exporting(Highcharts)
  Highcharts.setOptions({ credits: { enabled: false }, lang: { thousandsSep: ' ' } })
}
import { getJSON } from '../lib/http'

export default function Dashboard() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [companyId, setCompanyId] = useState('')
  const [modalInfo, setModalInfo] = useState(null)

  const isAdmin = Boolean(data?.meta?.is_admin)
  const periodLabel = data?.period?.label || '—'

  async function load() {
    setLoading(true)
    setError('')
    try {
      const params = new URLSearchParams()
      if (companyId) params.append('company_id', companyId)
      const res = await getJSON(`/api/dashboard?${params.toString()}`)
      setData(res)
      if (res.requires_company) {
        setCompanyId('')
      } else if (res.current_company?.id) {
        setCompanyId(String(res.current_company.id))
      }
    } catch (err) {
      setError(err.message || 'Failed to load dashboard.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    load()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [companyId])

  useEffect(() => {
    if (!data || data.requires_company || loading) return
    renderCompletionChart(data)
    renderEntityChart(data)
    renderVolumeChart('bs', data)
    renderVolumeChart('is', data)
    renderStatusChart(data)
    renderAgreementChart(data)
    renderTrendChart(trend)
    renderAccountsChart('bs', data)
    renderAccountsChart('is', data)
  }, [data, loading])

  const completion = data?.completion || {}
  const entities = data?.entities || []
  const variance = completion.variance || {}
  const ageing = data?.ageing || {}
  const trend = data?.trend || []
  const exposure = data?.exposure || {}
  const topAccounts = data?.top_accounts || {}

  const bsSeries = useMemo(() => entities.map(e => ({
    name: e.name,
    y: e.bs_volume_abs || 0,
  })), [entities])

  const isSeries = useMemo(() => entities.map(e => ({
    name: e.name,
    y: e.is_volume_abs || 0,
  })), [entities])

  return (
    <>
        <div className="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
        <div>
          <h1 className="h2">Dashboard</h1>
          <div className="text-muted">Period: #{data?.period?.period_number ?? ''} - {periodLabel}</div>
          <div className="text-muted">Company: {data?.current_company?.name || '—'}</div>
        </div>
        <div className="d-flex gap-2 align-items-center">
          {isAdmin && (
            <select
              className="form-select"
              value={companyId}
              onChange={e => setCompanyId(e.target.value)}
            >
              <option value="">Select company…</option>
              {(data?.meta?.companies ?? []).map(c => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
          <button className="btn btn-outline-primary" onClick={load} disabled={loading}>Refresh</button>
        </div>
      </div>

      {error && <div className="alert alert-danger">{error}</div>}
      {data?.requires_company && (
        <div className="alert alert-warning">Select a company to view the dashboard.</div>
      )}

      {!loading && !error && !data?.requires_company && (
        <div className="row g-3">
          <div className="col-12 col-lg-4">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Completion</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Completion',
                  body: 'Shows how many confirmations are fully done. Completed means both sides reviewed and the receiver chose Agree/Disagree. Outstanding is everything else.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="completion-chart" style={{ height: 260 }}></div>
                <div className="mt-3 small text-muted">
                  Completed: {completion.completed ?? 0} / {completion.population ?? 0} (Outstanding: {completion.outstanding ?? 0})
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-8">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Per-Entity Completion</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Per-Entity Completion',
                  body: 'For each company, shows % of confirmations completed vs total for the active period.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="entity-chart" style={{ height: 260 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Balance Sheet Volume (Absolute)</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Balance Sheet Volume',
                  body: 'Absolute amounts of balance sheet transactions per company (receivable and payable combined), in Rands.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="bs-volume-chart" style={{ height: 260 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Income Statement Volume (Absolute)</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Income Statement Volume',
                  body: 'Absolute amounts of income statement transactions per company (revenue and expense combined), in Rands.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="is-volume-chart" style={{ height: 260 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Status Mix</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Status Mix',
                  body: 'Counts of confirmations by workflow status (draft, pending review, reviewed, rejected) for the active period.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="status-chart" style={{ height: 240 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Agreements</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Agreements',
                  body: 'Receiver responses: Agree, Disagree, or Unknown. Unknown means the receiver has not chosen yet.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="agreement-chart" style={{ height: 240 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Variance Quality</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Variance Quality',
                  body: 'Shows what percentage of confirmations have zero variance between sender and receiver amounts. Lower variance is better.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div className="h4">{variance.zero_pct ?? 0}% zero variance</div>
                <div className="text-muted small">Zero: {variance.zero ?? 0} • Non-zero: {variance.non_zero ?? 0}</div>
                <div className="mt-3">
                  <div className="fw-semibold mb-2">Top Variances</div>
                  <ul className="list-group small">
                    {(variance.top ?? []).map((item, idx) => (
                      <li key={idx} className="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                          <div className="fw-semibold">{item.trading_partner || '—'}</div>
                          <div className="text-muted">{item.hfm_account || '—'}</div>
                        </div>
                        <div className="fw-semibold">{formatRand(item.variance || 0)}</div>
                      </li>
                    ))}
                    {(variance.top ?? []).length === 0 && (
                      <li className="list-group-item text-muted">No variances</li>
                    )}
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Completion Trend</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Completion Trend',
                  body: 'Completion percentage over the last few periods. Helps you see if confirmations are closing faster over time.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="trend-chart" style={{ height: 240 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Ageing (Cycle Time)</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Ageing / Cycle Time',
                  body: 'Average days to move from Draft to Sender Reviewed, and from Sender Reviewed to Receiver Reviewed.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div className="row text-center">
                  <div className="col-6">
                    <div className="text-muted small">Draft → Sender Reviewed</div>
                    <div className="fs-4 fw-semibold">{ageing?.draft_to_sender_reviewed?.average_days ?? 0} days</div>
                    <div className="text-muted small">{ageing?.draft_to_sender_reviewed?.samples ?? 0} samples</div>
                  </div>
                  <div className="col-6">
                    <div className="text-muted small">Sender → Receiver Reviewed</div>
                    <div className="fs-4 fw-semibold">{ageing?.sender_to_receiver_reviewed?.average_days ?? 0} days</div>
                    <div className="text-muted small">{ageing?.sender_to_receiver_reviewed?.samples ?? 0} samples</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Net Exposure</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Net Exposure',
                  body: 'Balance Sheet: net receivable/payable. Income Statement: net income/loss for the current company.'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div className="row text-center">
                  <div className="col-6">
                    <div className="text-muted small">Balance Sheet Net</div>
                    <div className="fs-4 fw-semibold">{formatRand(exposure?.balance_sheet_net ?? 0)}</div>
                  </div>
                  <div className="col-6">
                    <div className="text-muted small">Income Statement Net</div>
                    <div className="fs-4 fw-semibold">{formatRand(exposure?.income_statement_net ?? 0)}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Top Accounts (BS)</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Top Accounts (BS)',
                  body: 'HFM accounts with the highest balance sheet activity (absolute amounts).'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="bs-accounts-chart" style={{ height: 240 }}></div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-6">
            <div className="card h-100">
              <div className="card-header d-flex justify-content-between align-items-center">
                <strong>Top Accounts (IS)</strong>
                <button className="btn btn-link p-0" aria-label="Info" onClick={() => setModalInfo({
                  title: 'Top Accounts (IS)',
                  body: 'HFM accounts with the highest income statement activity (absolute amounts).'
                })}>
                  <i className="bi bi-info-circle"></i>
                </button>
              </div>
              <div className="card-body">
                <div id="is-accounts-chart" style={{ height: 240 }}></div>
              </div>
            </div>
          </div>
        </div>
      )}
      {modalInfo && (
        <div className="modal fade show" style={{ display: 'block', background: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{modalInfo.title}</h5>
                <button type="button" className="btn-close" aria-label="Close" onClick={() => setModalInfo(null)}></button>
              </div>
              <div className="modal-body">
                <p>{modalInfo.body}</p>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary" onClick={() => setModalInfo(null)}>Close</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

function renderCompletionChart(data) {
  const completed = data?.completion?.completed ?? 0
  const outstanding = data?.completion?.outstanding ?? 0
  Highcharts.chart('completion-chart', {
    chart: { type: 'pie' },
    exporting: { enabled: true },
    title: { text: null },
    tooltip: { pointFormat: '<b>{point.y}</b> ({point.percentage:.1f}%)' },
    plotOptions: {
      pie: {
        innerSize: '60%',
        dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' }
      }
    },
    series: [{
      name: 'Confirmations',
      data: [
        { name: 'Completed', y: completed, color: '#00a1d6' },
        { name: 'Outstanding', y: outstanding, color: '#dee2e6' },
      ]
    }]
  })
}

function renderEntityChart(data) {
  const entities = (data?.entities ?? []).sort((a, b) => (b.completion_pct || 0) - (a.completion_pct || 0))
  Highcharts.chart('entity-chart', {
    chart: { type: 'bar' },
    exporting: { enabled: true },
    title: { text: null },
    xAxis: { categories: entities.map(e => e.name), title: { text: null } },
    yAxis: {
      min: 0,
      max: 100,
      title: { text: '% Completion' },
    },
    tooltip: {
      shared: true,
      formatter() {
        const e = entities[this.points[0].point.index]
        return `<b>${e.name}</b><br/>Completed: ${e.completed}/${e.population}<br/>Completion: ${e.completion_pct}%`
      }
    },
    plotOptions: {
      series: { stacking: 'normal' }
    },
    series: [{
      name: 'Completion %',
      data: entities.map(e => e.completion_pct || 0),
      color: '#00a1d6'
    }]
  })
}

function renderVolumeChart(type, data) {
  const entities = data?.entities ?? []
  const series = entities.map(e => ({
    name: e.name,
    y: type === 'bs' ? (e.bs_volume_abs || 0) : (e.is_volume_abs || 0),
  }))

  Highcharts.chart(`${type}-volume-chart`, {
    chart: { type: 'column' },
    exporting: { enabled: true },
    title: { text: null },
    xAxis: { type: 'category' },
    yAxis: {
      min: 0,
      title: { text: 'Absolute Amount' },
      labels: { formatter() { return formatRand(this.value) } },
    },
    tooltip: {
      pointFormatter() {
        return `${this.name}: <b>${formatRand(this.y)}</b>`
      }
    },
    series: [{
      name: type === 'bs' ? 'BS Volume' : 'IS Volume',
      data: series,
      color: type === 'bs' ? '#00a1d6' : '#008000'
    }]
  })
}

function formatRand(value) {
  const num = Number(value || 0)
  if (num >= 1_000_000_000) return `R ${(num / 1_000_000_000).toFixed(1)}b`
  if (num >= 1_000_000) return `R ${(num / 1_000_000).toFixed(1)}m`
  if (num >= 1_000) return `R ${(num / 1_000).toFixed(1)}k`
  return `R ${num.toFixed(0)}`
}

function renderStatusChart(data) {
  const counts = data?.completion?.status_counts || {}
  const categories = Object.keys(counts)
  const series = categories.map(key => counts[key] || 0)

  Highcharts.chart('status-chart', {
    chart: { type: 'column' },
    exporting: { enabled: true },
    title: { text: null },
    xAxis: { categories, title: { text: 'Status' } },
    yAxis: { min: 0, title: { text: 'Count' } },
    series: [{
      name: 'Confirmations',
      data: series,
      color: '#00a1d6',
    }]
  })
}

function renderAgreementChart(data) {
  const counts = data?.completion?.agreement_counts || {}
  const series = Object.keys(counts).map(key => ({ name: key, y: counts[key] || 0 }))

  Highcharts.chart('agreement-chart', {
    chart: { type: 'pie' },
    exporting: { enabled: true },
    title: { text: null },
    tooltip: { pointFormat: '<b>{point.y}</b> ({point.percentage:.1f}%)' },
    plotOptions: {
      pie: {
        dataLabels: { enabled: true, format: '{point.name}: {point.percentage:.1f}%' }
      }
    },
    series: [{
      name: 'Agreements',
      data: series,
      colors: ['#00a1d6', '#f39c12', '#7f8c8d']
    }]
  })
}

function renderTrendChart(points) {
  if (!points || points.length === 0) {
    Highcharts.chart('trend-chart', { title: { text: 'No data' } })
    return
  }
  Highcharts.chart('trend-chart', {
    chart: { type: 'line' },
    exporting: { enabled: true },
    title: { text: null },
    xAxis: { categories: points.map(p => p.label) },
    yAxis: { min: 0, max: 100, title: { text: 'Completion %' } },
    tooltip: {
      formatter() {
        const p = points[this.point.index]
        return `<b>${p.label}</b><br/>Completion: ${p.completion_pct}%<br/>Completed: ${p.completed}/${p.population}`
      }
    },
    series: [{
      name: 'Completion %',
      data: points.map(p => p.completion_pct || 0),
      color: '#00a1d6',
    }]
  })
}

function renderAccountsChart(type, data) {
  const groups = data?.top_accounts || {}
  const key = type === 'bs' ? 'BALANCE_SHEET' : 'INCOME_STATEMENT'
  const items = groups[key] || []

  Highcharts.chart(`${type}-accounts-chart`, {
    chart: { type: 'bar' },
    exporting: { enabled: true },
    title: { text: null },
    xAxis: { categories: items.map(i => i.account) },
    yAxis: {
      min: 0,
      title: { text: 'Abs Amount' },
      labels: { formatter() { return formatRand(this.value) } },
    },
    tooltip: {
      pointFormatter() { return `<b>${formatRand(this.y)}</b>` }
    },
    series: [{
      name: type === 'bs' ? 'BS Accounts' : 'IS Accounts',
      data: items.map(i => i.total_abs || 0),
      color: type === 'bs' ? '#00a1d6' : '#008000',
    }]
  })
}
