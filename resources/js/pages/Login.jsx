// resources/js/pages/Login.jsx
import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { postJSON } from '../lib/http'
import { csrfToken } from '../lib/http'

export default function Login() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await fetch('/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })

      await postJSON('/login', { email, password, remember })
      navigate('/admin')
    } catch (err) {
      if (err.status === 419) {
        try {
          await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          })
          await postJSON('/login', { email, password, remember })
          navigate('/admin')
          return
        } catch (retryErr) {
          setError(retryErr.data?.message || retryErr.message || 'Login failed (CSRF).')
          return
        }
      }
      setError(err.data?.message || err.message || 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <form className="p-4 p-md-5 border rounded-3 bg-white needs-validation" noValidate onSubmit={handleSubmit}>
        <div className="d-flex justify-content-center mb-3">
            <svg width="129" height="50" viewBox="0 0 129 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g clipPath="url(#clip0_336_7060)">
                    <path d="M32.87 14.1926H27.8892V18.9156H32.87C33.0108 18.15 33.0844 17.3608 33.0844 16.5541C33.0844 15.7474 33.0108 14.9582 32.87 14.1926Z" fill="#91E200"></path>
                    <path d="M114.32 37.5572V26.9608C114.32 22.4385 112.484 20.3325 108.543 20.3325C106.504 20.3325 104.852 21.2474 103.738 22.9849C102.911 21.2247 101.223 20.3325 98.7123 20.3325C96.8561 20.3325 95.2734 21.1298 94.1947 22.5929C94.1187 22.1509 93.9723 21.4514 93.8462 21.0688C93.7999 20.929 93.7531 20.836 93.7177 20.777C93.0385 20.6405 92.3362 20.5687 91.6169 20.5687C90.8976 20.5687 90.2057 20.6395 89.5312 20.7736V37.5572C90.3559 37.7216 91.2084 37.8075 92.0807 37.8075C92.953 37.8075 93.8055 37.7211 94.6302 37.5572V28.8093C94.6302 25.9892 95.4647 24.7858 97.4215 24.7858C98.8941 24.7858 99.3763 25.6308 99.3763 28.1344V37.5572C100.201 37.7211 101.053 37.8075 101.926 37.8075C102.799 37.8075 103.651 37.7216 104.475 37.5572V28.8093C104.475 25.9892 105.31 24.7858 107.266 24.7858C108.739 24.7858 109.221 25.6308 109.221 28.1344V37.5568C110.045 37.7206 110.898 37.8071 111.77 37.8071C112.643 37.8071 113.495 37.7211 114.32 37.5568" fill="#0099FF"></path><path d="M46.6802 13.8621V34.9738C46.6802 36.539 47.9488 37.8076 49.5135 37.8076C50.3254 37.8076 51.1155 37.7164 51.875 37.544V13.8621C51.0357 13.692 50.1671 13.6023 49.2773 13.6023C48.3875 13.6023 47.519 13.692 46.6797 13.8621" fill="#0099FF"></path>
                    <path d="M26.0001 37.5478V14.1926H14.4071C14.2663 14.9582 14.1926 15.7474 14.1926 16.5541C14.1926 17.3608 14.2663 18.15 14.4071 18.9156H20.8048V37.5478C21.6441 37.7183 22.5126 37.8075 23.4024 37.8075C24.2922 37.8075 25.1608 37.7178 26.0001 37.5478Z" fill="#0099FF"></path>
                    <path d="M39.5418 27.1459C39.4091 25.2841 38.3738 24.1813 36.7387 24.1813C34.8547 24.1813 33.7982 25.1519 33.5148 27.1459H39.5418ZM37.4419 38.0437C34.585 38.0437 32.352 37.2866 30.8057 35.7937C29.2457 34.2875 28.4546 32.0994 28.4546 29.2911C28.4546 26.7015 29.2207 24.4821 30.6701 22.873C32.1669 21.2115 34.2856 20.333 36.7977 20.333C41.3153 20.333 44.3503 23.5801 44.3503 28.4126C44.3503 29.0847 44.3314 30.0472 44.1661 30.6419L44.1193 30.8109H33.4775C33.7165 32.9145 35.1773 33.9805 37.8231 33.9805C39.1998 33.9805 40.7027 33.7113 42.1696 33.2027L42.3562 33.1379L42.4493 33.3118C42.6996 33.7817 42.9064 34.2937 43.0817 34.8769C43.246 35.4532 43.3452 36.044 43.4127 36.8568L43.4274 37.033L43.2611 37.093C41.5183 37.724 39.5607 38.0442 37.4424 38.0442" fill="#0099FF"></path>
                    <path d="M59.8095 37.5478V13.8625C58.9703 13.692 58.1017 13.6028 57.2119 13.6028C56.3221 13.6028 55.4535 13.6925 54.6143 13.8625V37.5478C55.4535 37.7183 56.3221 37.8076 57.2119 37.8076C58.1017 37.8076 58.9703 37.7183 59.8095 37.5478Z" fill="#0099FF"></path>
                    <path d="M79.0121 24.1813C77.4408 24.1813 76.1622 25.4371 76.1622 26.9811V31.3663C76.1622 32.9263 77.4408 34.1954 79.0121 34.1954C80.5834 34.1954 81.8912 32.9528 81.8912 31.3663V26.9811C81.8912 25.4371 80.5995 24.1813 79.0121 24.1813ZM79.0121 38.0437C73.9283 38.0437 70.77 34.6616 70.77 29.2174C70.77 26.5919 71.5172 24.3744 72.9313 22.8041C74.3874 21.1874 76.49 20.3325 79.0121 20.3325C81.5342 20.3325 83.625 21.1831 85.0816 22.7927C86.5032 24.3631 87.2546 26.5848 87.2546 29.2179C87.2546 34.662 84.0964 38.0442 79.0126 38.0442" fill="#0099FF"></path>
                    <path d="M65.9272 28.5892L70.3299 20.7641C69.4684 20.6352 68.5871 20.5686 67.6898 20.5686C66.7116 20.5686 65.7519 20.6479 64.8168 20.8005L60.5859 28.7974L65.5167 37.5713C66.4604 37.7267 67.4295 37.8075 68.4171 37.8075C69.3192 37.8075 70.2057 37.7399 71.0719 37.6096L65.9272 28.5892Z" fill="#0099FF">

                    </path></g><defs><clipPath id="clip0_336_7060"><rect width="128.512" height="52" fill="white"></rect></clipPath></defs></svg>
        </div>
      <h2 className="h3 mb-3 fw-normal text-center">Please sign in</h2>

      {error ? <div className="alert alert-danger">{error}</div> : null}

      <div className="form-floating mb-2">
        <input type="email" className="form-control" id="floatingInput"
               placeholder="name@example.com" required
               value={email} onChange={e => setEmail(e.target.value)} />
        <label htmlFor="floatingInput">Email address</label>
      </div>

      <div className="form-floating mb-3">
        <input type="password" className="form-control" id="floatingPassword"
               placeholder="Password" required
               value={password} onChange={e => setPassword(e.target.value)} />
        <label htmlFor="floatingPassword">Password</label>
      </div>

      <div className="form-check text-start my-3">
        <input className="form-check-input" type="checkbox" id="rememberMe"
               checked={remember} onChange={e => setRemember(e.target.checked)} />
        <label className="form-check-label" htmlFor="rememberMe">Remember me</label>
      </div>

      <button className="w-100 btn btn-lg btn-primary" type="submit" disabled={loading}>
        {loading ? 'Signing in…' : 'Sign in'}
      </button>
      <button className="w-100 btn btn-lg btn-secondary mt-3" type="button" disabled={loading}>
        <i className="bi bi-windows"></i> Sign in with SSO
      </button>
    </form>
  )
}
