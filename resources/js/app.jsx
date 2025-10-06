import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Configure CSRF token for Inertia requests
router.on('before', (event) => {
    // Sempre pegar o token mais recente do DOM
    const token = document.head.querySelector('meta[name="csrf-token"]');
    if (token) {
        event.detail.visit.headers = {
            ...event.detail.visit.headers,
            'X-CSRF-TOKEN': token.content,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }
});

// Handle 419 CSRF token errors
router.on('error', (event) => {
    const { detail } = event;
    if (detail.page && detail.page.status === 419) {
        console.error('CSRF token mismatch. A sessão pode ter expirado.');

        // Criar modal personalizado para erro de sessão
        const modalHtml = `
            <div id="session-expired-modal" style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.5);">
                <div style="background: white; border-radius: 12px; padding: 24px; max-width: 400px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);">
                    <div style="display: flex; align-items: start; gap: 16px;">
                        <div style="flex-shrink: 0;">
                            <svg style="width: 24px; height: 24px; color: #DC2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin: 0 0 8px 0;">
                                Sessão Expirada
                            </h3>
                            <p style="font-size: 14px; color: #6B7280; margin: 0 0 20px 0;">
                                Sua sessão expirou por segurança. Para continuar, recarregue a página para fazer login novamente.
                            </p>
                            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                                <button id="cancel-reload" style="padding: 8px 16px; border: 1px solid #D1D5DB; background: white; border-radius: 6px; font-size: 14px; font-weight: 500; color: #374151; cursor: pointer;">
                                    Cancelar
                                </button>
                                <button id="confirm-reload" style="padding: 8px 16px; border: none; background: #DC2626; border-radius: 6px; font-size: 14px; font-weight: 500; color: white; cursor: pointer;">
                                    Recarregar Página
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        document.getElementById('confirm-reload').addEventListener('click', () => {
            window.location.reload();
        });

        document.getElementById('cancel-reload').addEventListener('click', () => {
            document.getElementById('session-expired-modal').remove();
        });
    }
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
