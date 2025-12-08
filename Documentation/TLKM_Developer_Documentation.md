# TLKM Confirmations Developer Documentation

## Overview

This project is a Laravel + React web application that integrates a React SPA directly inside a Laravel installation using Vite. It does not use Inertia or Laravel starter kits. The frontend leverages Bootstrap 5, Bootstrap Icons, and a custom Soft-UI inspired theme.

### Key Technologies

- Backend: Laravel 11 (PHP 8.2+)
- Frontend: React 18 + Vite
- Styling: Bootstrap 5.3 + custom theme (Soft-UI inspired)
- Icons: Bootstrap Icons
- Authentication: Laravel sessions via the web guard
- Routing: React Router DOM (SPA) + Laravel fallback route

---

## Project Structure

```
tlkm-comfirmations/
├── app/                 # Laravel backend logic
├── public/              # Web server root
├── resources/
│   ├── js/              # React application
│   │   ├── app.jsx      # React entry point
│   │   ├── layouts/     # App layouts (AuthLayout, AdminLayout)
│   │   ├── pages/       # Page components (Login, Dashboard, etc.)
│   │   └── lib/         # HTTP helpers & utilities
│   ├── css/             # CSS overrides
│   │   └── theme.css    # Custom Soft-UI inspired theme
│   └── views/
│       └── app.blade.php # Blade shell that bootstraps React
├── routes/
│   └── web.php          # All routes (auth + SPA fallback)
├── vite.config.js       # Vite + React config
└── package.json         # JS dependencies
```

---

## Frontend Setup

### 1. Dependencies

```bash
npm install
npm install react react-dom react-router-dom bootstrap @popperjs/core bootstrap-icons
```

### 2. Entry File (resources/js/app.jsx)

Defines routing, layout imports, and Vite integration.

- Imports Bootstrap + theme:

```jsx
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import '../css/theme.css'
```

- Routes are handled with React Router (/login, /admin).

---

## Laravel Configuration

### Blade Shell

`resources/views/app.blade.php`:

```html
<head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @viteReactRefresh
  @vite(['resources/js/app.jsx'])
</head>
<body>
  <div id="root"></div>
</body>
```

### Catch-All Route

Ensures SPA handles all navigation:

```php
Route::get('/{any}', fn() => view('app'))->where('any', '.*');
```

---

## Authentication (Session-Based)

The app uses Laravel sessions and the default web guard (no API tokens).

### Example Auth Routes (web.php)

```php
Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (Auth::attempt($credentials, true)) {
        $request->session()->regenerate();
        return response()->json(['ok' => true]);
    }

    return response()->json(['ok' => false, 'message' => 'Invalid credentials'], 422);
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return response()->json(['ok' => true]);
});

Route::get('/user', fn(Request $r) => $r->user() ?: response()->json(['message' => 'Unauthenticated'], 401));
```

### CSRF Token

React fetches the token from `<meta name="csrf-token">` and sends it with every POST.

### Login Page Logic

- Submits /login
- On success: redirect to /admin
- On failure: display alert

---

## Layouts

### AuthLayout

Centered minimal layout used for /login page.

### AdminLayout

- Sidebar navigation styled with Soft-UI look.
- Responsive top navbar with sign-out button.
- Uses `vh-100` to fill viewport height.

Sidebar uses Bootstrap icons and structure:

```jsx
<nav className="sui-sidebar vh-100">
  <ul className="nav flex-column px-2">
    <li className="nav-item mt-2">
      <Link className="nav-link active" to="/admin">
        <span className="sui-icon"><i className="bi bi-speedometer2"></i></span>
        <span>Dashboard</span>
      </Link>
    </li>
  </ul>
</nav>
```

---

## Styling: Soft-UI Theme

The `theme.css` file overrides Bootstrap using CSS variables:

- Soft shadows & rounded corners
- Card hover lift effects
- Inset icon boxes & chips
- Sticky table headers
- Custom gradients and background

Sample helpers:

```css
.card.card-lift:hover { transform: translateY(-2px); box-shadow: var(--sui-shadow); }
.table.sui-table tbody tr:hover { background-color: rgba(0,163,224,.035); }
.sui-chip { display:inline-flex; gap:.375rem; padding:.35rem .6rem; border-radius:1rem; }
.main-shell { background: linear-gradient(180deg,#fff 0%,#f7f9fb 60%,#f3f6f9 100%); }
```

---

## Development Commands

| Task                  | Command           |
|-----------------------|-------------------|
| Start Laravel server  | `php artisan serve`|
| Start Vite dev server | `npm run dev`     |
| Build production assets | `npm run build`  |

Both servers must run concurrently during development.

---

## Recommended Conventions

- Keep all React pages in `resources/js/pages/`
- Place reusable UI components (widgets, charts, forms) in `resources/js/components/`
- Use `lib/http.js` for all network calls.
- Avoid inline styles; rely on Bootstrap utilities and theme helpers.

---

## Next Steps

1. Add more admin pages (Tables, Billing, Profile, etc.)
2. Connect real data endpoints.
3. Enhance dashboard with cards, metrics, and charts.
4. Optionally integrate Laravel policies for role-based access.

---

## Contributors

- Project Setup: Laravel + React integration
- UI/UX: Telkom blue palette + Soft-UI style
- Author: Maxwell Mphioe

---

## License

Internal proprietary project for Lerato Bopape’s Workspace. Redistribution prohibited.
