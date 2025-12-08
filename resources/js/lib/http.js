// resources/js/lib/http.js
export function csrfToken() {
  if (window.__csrfToken) return window.__csrfToken
  const el = document.querySelector('meta[name="csrf-token"]')
  return el ? el.getAttribute('content') : ''
}

async function refreshCsrfToken() {
  const res = await fetch('/csrf-token', {
    method: 'GET',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  })
  const data = await res.json()
  if (data?.token) {
    window.__csrfToken = data.token
  }
}

async function requestJSON(url, { method = 'GET', body } = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken(),
  }
  const options = {
    method,
    credentials: 'same-origin',
    headers,
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }

  async function doFetch() {
    const res = await fetch(url, options)
    const text = await res.text()
    const trimmed = text.trim()
    let data = {}
    if (trimmed) {
      try {
        data = JSON.parse(trimmed)
      } catch {
        data = { message: trimmed }
      }
    }

    if (!res.ok) {
      const err = new Error(data?.message || 'Request failed')
      err.status = res.status
      err.data = data
      throw err
    }

    return data
  }

  try {
    // proactively refresh CSRF for mutating verbs
    if (method !== 'GET') {
      await refreshCsrfToken()
      headers['X-CSRF-TOKEN'] = csrfToken()
      if (body && typeof body === 'object') {
        body._token = csrfToken()
      }
    }
    return await doFetch()
  } catch (err) {
    if (err.status === 419) {
      await refreshCsrfToken()
      headers['X-CSRF-TOKEN'] = csrfToken()
      if (body && typeof body === 'object') {
        body._token = csrfToken()
        options.body = JSON.stringify(body)
      }
      return await doFetch()
    }
    throw err
  }
}

export function getJSON(url) {
  return requestJSON(url)
}

export function postJSON(url, body) {
  return requestJSON(url, { method: 'POST', body })
}

export function putJSON(url, body) {
  return requestJSON(url, { method: 'PUT', body })
}

export function deleteJSON(url) {
  return requestJSON(url, { method: 'DELETE' })
}

export function patchJSON(url, body) {
  return requestJSON(url, { method: 'PATCH', body })
}

export async function postForm(url, formData) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-CSRF-TOKEN': csrfToken(),
    },
    body: formData,
  })

  const text = await res.text()
  const trimmed = text.trim()
  let data = {}
  if (trimmed) {
    try {
      data = JSON.parse(trimmed)
    } catch {
      data = { message: trimmed }
    }
  }

  if (!res.ok) {
    const err = new Error(data?.message || 'Request failed')
    err.status = res.status
    err.data = data
    throw err
  }

  return data
}
