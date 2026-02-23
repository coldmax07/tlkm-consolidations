<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Confirmations</title>
      @viteReactRefresh
    @vite(['resources/js/app.jsx'])
  </head>
  <body>
    <div id="root"></div>
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  </body>
</html>
