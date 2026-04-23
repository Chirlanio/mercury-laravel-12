import { useCallback, useState } from 'react';

/**
 * Hook de lookups AJAX do módulo Cupons.
 * Encapsula os 4 endpoints do CouponController:
 *  - /coupons/lookup/existing         → banner warning de cupons ativos
 *  - /coupons/lookup/employees        → autocomplete por loja
 *  - /coupons/lookup/employee-details → CPF real ao selecionar employee
 *  - /coupons/suggest-code            → sugestão de código
 *
 * Cada lookup expõe { data, loading, error, run } — padrão mínimo pra
 * integrar facilmente no modal de criação.
 */
export default function useCoupons() {
    const [existing, setExisting] = useState({ data: [], loading: false, error: null });
    const [employees, setEmployees] = useState({ data: [], loading: false, error: null });

    const fetchJson = async (url) => {
        const resp = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!resp.ok) {
            throw new Error('HTTP ' + resp.status);
        }
        return resp.json();
    };

    const lookupExisting = useCallback(async (cpf, { type = null, storeCode = null } = {}) => {
        if (!cpf || cpf.replace(/\D/g, '').length < 11) {
            setExisting({ data: [], loading: false, error: null });
            return [];
        }
        setExisting({ data: [], loading: true, error: null });
        try {
            const params = new URLSearchParams({ cpf });
            if (type) params.append('type', type);
            if (storeCode) params.append('store_code', storeCode);
            const json = await fetchJson(`${route('coupons.lookup.existing')}?${params}`);
            setExisting({ data: json.existing || [], loading: false, error: null });
            return json.existing || [];
        } catch (err) {
            setExisting({ data: [], loading: false, error: err.message });
            return [];
        }
    }, []);

    const lookupEmployees = useCallback(async (storeCode) => {
        if (!storeCode) {
            setEmployees({ data: [], loading: false, error: null });
            return [];
        }
        setEmployees({ data: [], loading: true, error: null });
        try {
            const params = new URLSearchParams({ store_code: storeCode });
            const json = await fetchJson(`${route('coupons.lookup.employees')}?${params}`);
            setEmployees({ data: json.employees || [], loading: false, error: null });
            return json.employees || [];
        } catch (err) {
            setEmployees({ data: [], loading: false, error: err.message });
            return [];
        }
    }, []);

    const fetchEmployeeDetails = useCallback(async (employeeId) => {
        if (!employeeId) return null;
        try {
            const params = new URLSearchParams({ employee_id: employeeId });
            const json = await fetchJson(`${route('coupons.lookup.employee-details')}?${params}`);
            return json.employee || null;
        } catch {
            return null;
        }
    }, []);

    const suggestCode = useCallback(async (name, year = null) => {
        if (!name || name.trim().length < 2) return '';
        try {
            const params = new URLSearchParams({ name });
            if (year) params.append('year', year);
            const json = await fetchJson(`${route('coupons.suggest-code')}?${params}`);
            return json.code || '';
        } catch {
            return '';
        }
    }, []);

    const clearExisting = useCallback(() => {
        setExisting({ data: [], loading: false, error: null });
    }, []);

    return {
        existing,
        employees,
        lookupExisting,
        lookupEmployees,
        fetchEmployeeDetails,
        suggestCode,
        clearExisting,
    };
}
