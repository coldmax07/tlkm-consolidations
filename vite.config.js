import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [
        laravel({
            input: [ 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(), // enables HMR, JSX transform, fast refresh
    ],
    server: {
        proxy: {
            '/login': 'http://localhost:8000',
            '/logout': 'http://localhost:8000',
            '/csrf-token': 'http://localhost:8000',
            '/api': 'http://localhost:8000',
            '/user': 'http://localhost:8000',
        },
    },
});
