import '../css/app.css';
import 'leaflet/dist/leaflet.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// CSRF: deixe o axios do Inertia anexar X-XSRF-TOKEN do cookie automaticamente.
// Forçar X-CSRF-TOKEN da meta tag causa 419 quando o token de sessão rotaciona
// (ex: após regenerateToken no Profile/LGPD ou login em outra aba) — a meta fica
// stale enquanto o cookie XSRF-TOKEN é mantido em dia pelo browser.

// Handle 419 CSRF token errors. Inertia v2 dispara `invalid` (não `error`) para
// respostas não-Inertia como o HTML "Page Expired" do Laravel.
router.on('invalid', (event) => {
    const status = event.detail?.response?.status;
    if (status === 419) {
        event.preventDefault();
        console.error('CSRF token mismatch. A sessão pode ter expirado.');

        if (document.getElementById('session-expired-modal')) {
            return;
        }

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
        ).then((module) => {
            const page = module.default;
            // Apply persistent layout for tenant pages (skip Auth and Central pages)
            if (!page.layout && !name.startsWith('Auth/') && !name.startsWith('Central/')) {
                page.layout = (page) => <AuthenticatedLayout>{page}</AuthenticatedLayout>;
            }
            return module;
        }),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
