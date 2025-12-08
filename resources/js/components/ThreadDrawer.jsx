import React, { useEffect, useState } from 'react'
import { getJSON, postForm, postJSON } from '../lib/http'

function MessageCard({ message, onAttach }) {
  const [uploading, setUploading] = useState(false)

  async function handleFileChange(event) {
    if (!event.target.files?.length) return
    const file = event.target.files[0]
    setUploading(true)
    try {
      await onAttach(message.id, file)
    } finally {
      setUploading(false)
      event.target.value = ''
    }
  }

  return (
    <div className="mb-3 p-2 border rounded">
      <div className="d-flex justify-content-between">
        <div>
          <strong>{message.company?.name || message.author?.name}</strong>
          {message.role_context && <span className="ms-2 badge text-bg-light">{message.role_context}</span>}
        </div>
        <small className="text-muted">{new Date(message.created_at).toLocaleString()}</small>
      </div>
      <p className="mb-2">{message.body}</p>
      {message.attachments?.length > 0 && (
        <div className="mb-2">
          {message.attachments.map(att => (
            <a key={att.id} href={att.url} target="_blank" rel="noreferrer" className="d-block">
              <i className="bi bi-paperclip me-1"></i>{att.filename}
            </a>
          ))}
        </div>
      )}
      <label className="btn btn-sm btn-outline-secondary mb-0">
        {uploading ? 'Uploading…' : 'Attach file'}
        <input type="file" className="d-none" onChange={handleFileChange} disabled={uploading} />
      </label>
    </div>
  )
}

export default function ThreadDrawer({ transactionId, open, onClose }) {
  const [thread, setThread] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [body, setBody] = useState('')
  const [sending, setSending] = useState(false)

  useEffect(() => {
    if (open && transactionId) {
      loadThread()
    }
  }, [open, transactionId])

  async function loadThread() {
    setLoading(true)
    setError('')
    try {
      const data = await getJSON(`/api/transactions/${transactionId}/thread`)
      setThread(data)
    } catch (err) {
      setError(err.message || 'Unable to load thread.')
    } finally {
      setLoading(false)
    }
  }

  async function sendMessage(event) {
    event.preventDefault()
    if (!body.trim()) return
    setSending(true)
    setError('')
    try {
      await postJSON(`/api/transactions/${transactionId}/messages`, { body })
      setBody('')
      await loadThread()
    } catch (err) {
      setError(err?.data?.message || err.message || 'Unable to send message.')
    } finally {
      setSending(false)
    }
  }

  async function attachFile(messageId, file) {
    const formData = new FormData()
    formData.append('file', file)
    await postForm(`/api/messages/${messageId}/attachments`, formData)
    await loadThread()
  }

  return (
    <div className={`thread-drawer ${open ? 'show' : ''}`}>
      <div className="thread-drawer-backdrop" onClick={onClose}></div>
      <div className="thread-drawer-panel">
        <div className="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
          <h5 className="mb-0">Transaction #{transactionId} conversation</h5>
          <button className="btn-close" onClick={onClose}></button>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}
        {loading ? (
          <div>Loading thread…</div>
        ) : (
          <div className="thread-messages">
            {thread?.messages?.length ? (
              thread.messages.map(message => (
                <MessageCard key={message.id} message={message} onAttach={attachFile} />
              ))
            ) : (
              <p className="text-muted">No messages yet.</p>
            )}
          </div>
        )}
        <form className="mt-3" onSubmit={sendMessage}>
          <textarea
            className="form-control mb-2"
            placeholder="Add a message…"
            value={body}
            onChange={e => setBody(e.target.value)}
            rows={3}
          />
          <div className="d-flex justify-content-end">
            <button className="btn btn-primary" type="submit" disabled={sending}>
              {sending ? 'Sending…' : 'Send'}
            </button>
          </div>
        </form>
      </div>
      <style>{`
        .thread-drawer {
          position: fixed;
          inset: 0;
          pointer-events: none;
        }
        .thread-drawer.show {
          pointer-events: auto;
        }
        .thread-drawer-backdrop {
          position: absolute;
          inset: 0;
          background: rgba(0,0,0,0.4);
          opacity: ${open ? 1 : 0};
          transition: opacity .3s ease;
        }
        .thread-drawer-panel {
          position: absolute;
          top: 0;
          right: 0;
          width: min(420px, 100%);
          height: 100%;
          background: #fff;
          padding: 1.5rem;
          overflow-y: auto;
          transform: translateX(${open ? '0' : '100%'});
          transition: transform .3s ease;
          box-shadow: -4px 0 16px rgba(0,0,0,0.1);
        }
        .thread-messages {
          max-height: 60vh;
          overflow-y: auto;
        }
      `}</style>
    </div>
  )
}
