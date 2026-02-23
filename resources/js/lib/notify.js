function hasSwal() {
  return typeof window !== 'undefined' && window.Swal && typeof window.Swal.fire === 'function'
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;')
}

export function extractErrorMessages(err, fallback = 'Request failed.') {
  const messages = []
  const validation = err?.data?.errors

  if (validation && typeof validation === 'object') {
    Object.values(validation).forEach(value => {
      if (Array.isArray(value)) {
        value.forEach(item => {
          if (item) messages.push(String(item))
        })
      } else if (value) {
        messages.push(String(value))
      }
    })
  }

  if (err?.data?.message) messages.push(String(err.data.message))
  if (err?.message) messages.push(String(err.message))

  const unique = [...new Set(messages.map(m => m.trim()).filter(Boolean))]
  return unique.length ? unique : [fallback]
}

export async function notifyError(err, title = 'Action failed') {
  const messages = extractErrorMessages(err)
  if (hasSwal()) {
    await window.Swal.fire({
      icon: 'error',
      title,
      html: messages.map(msg => `• ${escapeHtml(msg)}`).join('<br>'),
      confirmButtonText: 'OK',
    })
    return
  }

  window.alert([title, ...messages].join('\n'))
}

export async function notifySuccess(message, title = 'Success') {
  if (hasSwal()) {
    await window.Swal.fire({
      icon: 'success',
      title,
      text: message,
      timer: 1800,
      showConfirmButton: false,
    })
    return
  }

  window.alert(message)
}

export async function promptReason(options = {}) {
  const title = options.title || 'Provide a reason'
  const text = options.text || ''
  const placeholder = options.placeholder || 'Reason'

  if (hasSwal()) {
    const result = await window.Swal.fire({
      title,
      text,
      input: 'textarea',
      inputPlaceholder: placeholder,
      inputAttributes: {
        'aria-label': placeholder,
      },
      showCancelButton: true,
      confirmButtonText: 'Submit',
      cancelButtonText: 'Cancel',
      inputValidator: (value) => {
        if (!value || !value.trim()) {
          return 'Reason is required.'
        }
        return null
      },
    })

    if (!result.isConfirmed) return null
    return result.value.trim()
  }

  const reason = window.prompt(title)
  if (!reason || !reason.trim()) return null
  return reason.trim()
}
