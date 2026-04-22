import {
    BanknotesIcon,
    ChartBarIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';

/**
 * Cards de KPIs da matriz DRE.
 *
 * Valores vêm do endpoint `DreMatrixController@show` no campo `kpis` —
 * já pré-computados por `DreMatrixService::kpis()`:
 *   - faturamento_liquido: { actual, budget, previous_year }
 *   - ebitda:              { actual, budget, previous_year }
 *   - margem_liquida:      { actual, budget, previous_year } (em %)
 *   - nao_classificado:    { actual, budget, previous_year }
 *
 * Variação exibida: actual vs budget (em %). Útil para ver rapidamente
 * se estamos acima/abaixo do orçado em cada KPI. Comparativo com ano
 * anterior fica na matriz detalhada.
 */
export default function KpiCards({ kpis, loading = false }) {
    if (!kpis) {
        return <StatisticsGrid loading={loading} cards={[]} cols={4} />;
    }

    const variance = (actual, budget) => {
        const a = Number(actual ?? 0);
        const b = Number(budget ?? 0);
        if (Math.abs(b) < 0.0001) return null;
        return ((a - b) / Math.abs(b)) * 100;
    };

    const fat = kpis.faturamento_liquido ?? zeros();
    const ebitda = kpis.ebitda ?? zeros();
    const margem = kpis.margem_liquida ?? zeros();
    const naoClass = kpis.nao_classificado ?? zeros();

    const cards = [
        {
            label: 'Faturamento Líquido',
            value: Number(fat.actual ?? 0),
            format: 'currency',
            color: 'green',
            icon: BanknotesIcon,
            sub: `Orçado: ${formatBRL(fat.budget)}`,
            variation: variance(fat.actual, fat.budget),
        },
        {
            label: 'EBITDA',
            value: Number(ebitda.actual ?? 0),
            format: 'currency',
            color: 'indigo',
            icon: CurrencyDollarIcon,
            sub: `Orçado: ${formatBRL(ebitda.budget)}`,
            variation: variance(ebitda.actual, ebitda.budget),
        },
        {
            label: 'Margem Líquida',
            value: Number(margem.actual ?? 0),
            format: 'percentage',
            color: 'blue',
            icon: ChartBarIcon,
            sub: `Orçado: ${formatPct(margem.budget)}`,
            variation: variance(margem.actual, margem.budget),
        },
        {
            label: 'Não Classificado',
            value: Number(naoClass.actual ?? 0),
            format: 'currency',
            color: Math.abs(Number(naoClass.actual ?? 0)) > 0.01 ? 'red' : 'gray',
            icon: ExclamationTriangleIcon,
            sub: Math.abs(Number(naoClass.actual ?? 0)) > 0.01
                ? 'Contas sem mapeamento DRE'
                : 'Todas contas mapeadas',
        },
    ];

    return <StatisticsGrid cards={cards} loading={loading} cols={4} />;
}

function zeros() {
    return { actual: 0, budget: 0, previous_year: 0 };
}

function formatBRL(value) {
    const num = typeof value === 'number' ? value : parseFloat(value ?? 0);
    if (Number.isNaN(num)) return '—';

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(num);
}

function formatPct(value) {
    const num = typeof value === 'number' ? value : parseFloat(value ?? 0);
    if (Number.isNaN(num)) return '—';

    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }).format(num) + '%';
}
