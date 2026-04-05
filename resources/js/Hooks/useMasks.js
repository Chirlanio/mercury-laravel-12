/**
 * Input mask utilities for Brazilian formats.
 * Pure JS — no external dependencies.
 */

/**
 * Format value as Brazilian currency: 1.234,56
 */
export function maskMoney(value) {
    let v = String(value).replace(/\D/g, '');
    if (!v) return '';
    v = v.replace(/^0+/, '') || '0';
    v = v.padStart(3, '0');
    const intPart = v.slice(0, -2);
    const decPart = v.slice(-2);
    const formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${formatted},${decPart}`;
}

/**
 * Parse Brazilian money string to float: "1.234,56" → 1234.56
 */
export function parseMoney(value) {
    if (!value) return 0;
    const cleaned = String(value).replace(/\./g, '').replace(',', '.');
    return parseFloat(cleaned) || 0;
}

/**
 * Format as CPF: 000.000.000-00
 */
export function maskCpf(value) {
    let v = String(value).replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    if (v.length > 6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    if (v.length > 3) return v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    return v;
}

/**
 * Format as CNPJ: 00.000.000/0000-00
 */
export function maskCnpj(value) {
    let v = String(value).replace(/\D/g, '').slice(0, 14);
    if (v.length > 12) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
    if (v.length > 8) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
    if (v.length > 5) return v.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
    if (v.length > 2) return v.replace(/(\d{2})(\d{1,3})/, '$1.$2');
    return v;
}

/**
 * Format as CPF or CNPJ (auto-detect by length)
 */
export function maskCpfCnpj(value) {
    const digits = String(value).replace(/\D/g, '');
    return digits.length <= 11 ? maskCpf(value) : maskCnpj(value);
}

/**
 * Format as phone: (00) 00000-0000 or (00) 0000-0000
 */
export function maskPhone(value) {
    let v = String(value).replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    if (v.length > 6) return v.replace(/(\d{2})(\d{4,5})(\d{0,4})/, '($1) $2-$3');
    if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
    return v;
}

/**
 * React-friendly masked input handler.
 * Returns an onChange handler that applies the mask before calling setValue.
 *
 * Usage:
 *   <input value={value} onChange={handleMasked(maskMoney, setValue)} />
 */
export function handleMasked(maskFn, setValue) {
    return (e) => {
        const masked = maskFn(e.target.value);
        setValue(masked);
    };
}
