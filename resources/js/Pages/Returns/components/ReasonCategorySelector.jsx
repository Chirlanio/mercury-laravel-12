import { useMemo } from 'react';

/**
 * Select de categoria (6 enum) + select de motivo em cascata filtrado
 * por categoria. Categoria obrigatória; motivo específico opcional
 * (alguns casos a categoria já é suficiente).
 *
 * @param {string} category Categoria atual (enum value)
 * @param {number|string} reasonId ID do motivo atual (opcional)
 * @param {Function} onChange ({ category, return_reason_id }) => void
 * @param {object} categoryOptions { slug: label } vindo do backend
 * @param {Array} reasons Lista completa de motivos [{ id, name, code, category }]
 * @param {object} errors { reason_category, return_reason_id }
 */
export default function ReasonCategorySelector({
    category,
    reasonId,
    onChange,
    categoryOptions = {},
    reasons = [],
    errors = {},
}) {
    // Filtra motivos pela categoria atual
    const filteredReasons = useMemo(() => {
        if (!category) return [];
        return reasons.filter((r) => r.category === category);
    }, [reasons, category]);

    const handleCategoryChange = (newCategory) => {
        // Mudou categoria → limpa motivo (pode não existir na nova categoria)
        onChange({ category: newCategory, return_reason_id: '' });
    };

    const handleReasonChange = (newReasonId) => {
        onChange({ category, return_reason_id: newReasonId });
    };

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Categoria do motivo *
                </label>
                <select
                    value={category || ''}
                    onChange={(e) => handleCategoryChange(e.target.value)}
                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Selecione...</option>
                    {Object.entries(categoryOptions).map(([slug, label]) => (
                        <option key={slug} value={slug}>
                            {label}
                        </option>
                    ))}
                </select>
                {errors.reason_category && (
                    <p className="mt-1 text-xs text-red-600">{errors.reason_category}</p>
                )}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Motivo específico
                </label>
                <select
                    value={reasonId || ''}
                    onChange={(e) => handleReasonChange(e.target.value)}
                    disabled={!category}
                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                >
                    <option value="">
                        {!category
                            ? 'Selecione a categoria primeiro'
                            : filteredReasons.length === 0
                            ? 'Nenhum motivo cadastrado nesta categoria'
                            : 'Selecione ou deixe em branco'}
                    </option>
                    {filteredReasons.map((r) => (
                        <option key={r.id} value={r.id}>
                            {r.name}
                        </option>
                    ))}
                </select>
                {errors.return_reason_id && (
                    <p className="mt-1 text-xs text-red-600">{errors.return_reason_id}</p>
                )}
            </div>
        </div>
    );
}
