/**
 * Helpers do módulo DRE (frontend).
 *
 * Mantém formatação e classificação de variação num lugar só — evita
 * divergência entre componentes quando a UI de matriz, drill, gráficos
 * e KPIs crescer.
 */

const MONTH_LABELS = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
    'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
];

/**
 * Formata valor como BRL. Aceita string numérica ou number.
 * Retorna '—' para null/undefined.
 */
export function formatCurrency(value) {
    if (value === null || value === undefined || value === '') return '—';
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(num)) return '—';

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(num);
}

/**
 * Formata valor como percentual com N decimais.
 *   formatPercentage(12.3456)     → "12,3%"
 *   formatPercentage(12.3456, 2)  → "12,35%"
 */
export function formatPercentage(value, decimals = 1) {
    if (value === null || value === undefined || value === '') return '—';
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(num)) return '—';

    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(num) + '%';
}

/**
 * Classifica variação como favorável / desfavorável / neutra conforme a
 * natureza da linha gerencial.
 *
 * Convenção: receita com variação positiva = favorável; despesa com
 * variação positiva = desfavorável (gastamos mais). Subtotais seguem a
 * regra do próprio valor (positivo = favorável independente).
 */
export function classifyVariance(value, lineNature) {
    if (value === null || value === undefined) return 'neutral';
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (Number.isNaN(num) || num === 0) return 'neutral';

    if (lineNature === 'expense') {
        return num < 0 ? 'favorable' : 'unfavorable';
    }

    return num > 0 ? 'favorable' : 'unfavorable';
}

/**
 * Retorna o rótulo curto PT para um mês (1..12). Ignora 0 ou fora do range.
 *   monthLabel(1)  → 'Jan'
 *   monthLabel(12) → 'Dez'
 */
export function monthLabel(month) {
    const idx = Number(month);
    if (!Number.isInteger(idx) || idx < 1 || idx > 12) return '';
    return MONTH_LABELS[idx - 1];
}

/**
 * Converte date/string 'YYYY-MM-DD' em chave 'YYYY-MM'. Estável para
 * indexar matrices mensais vindas do backend.
 */
export function yearMonthKey(input) {
    if (!input) return '';
    const s = String(input);
    return s.length >= 7 ? s.slice(0, 7) : s;
}

/**
 * Quebra 'YYYY-MM' em { year, month }. Retorna null para entradas inválidas.
 */
export function splitYearMonth(ym) {
    if (!ym || typeof ym !== 'string' || ym.length < 7) return null;
    const [y, m] = ym.split('-');
    const year = Number(y);
    const month = Number(m);
    if (!Number.isInteger(year) || !Number.isInteger(month)) return null;
    if (month < 1 || month > 12) return null;
    return { year, month };
}

/**
 * Gera a lista de year_months entre start_date e end_date (Y-m-d).
 * Útil pra montar colunas da matriz mesmo quando algum mês vem vazio.
 */
export function yearMonthsBetween(startDate, endDate) {
    const start = splitYearMonthFromDate(startDate);
    const end = splitYearMonthFromDate(endDate);
    if (!start || !end) return [];

    const result = [];
    let { year, month } = start;

    while (year < end.year || (year === end.year && month <= end.month)) {
        result.push(
            `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}`
        );
        month += 1;
        if (month > 12) {
            month = 1;
            year += 1;
        }
    }

    return result;
}

function splitYearMonthFromDate(date) {
    if (!date) return null;
    const s = String(date);
    if (s.length < 7) return null;
    return {
        year: Number(s.slice(0, 4)),
        month: Number(s.slice(5, 7)),
    };
}
