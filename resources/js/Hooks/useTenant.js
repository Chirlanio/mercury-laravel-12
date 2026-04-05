import { usePage } from '@inertiajs/react';

/**
 * Hook to access tenant information in the frontend.
 *
 * @returns {{
 *   tenant: object|null,
 *   tenantName: string|null,
 *   tenantSlug: string|null,
 *   plan: object|null,
 *   modules: string[],
 *   hasModule: (slug: string) => boolean,
 *   settings: object,
 *   isTenantContext: boolean,
 * }}
 */
export default function useTenant() {
    const { tenant } = usePage().props;

    const modules = tenant?.modules || [];

    return {
        tenant,
        tenantName: tenant?.name || null,
        tenantSlug: tenant?.slug || null,
        plan: tenant?.plan || null,
        modules,
        hasModule: (slug) => modules.includes(slug),
        settings: tenant?.settings || {},
        isTenantContext: !!tenant,
    };
}
