import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    server: {
        // Força IPv4. Sem isto, o Vite no Windows bind em [::1] e o browser
        // trava tentando carregar módulos JS de http://[::1]:5173 (IPv6).
        host: '127.0.0.1',
        hmr: {
            host: '127.0.0.1',
        },
    },
});
