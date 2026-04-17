const PIX_KEY_TYPES = [
    { value: 'cpf', label: 'CPF' },
    { value: 'cnpj', label: 'CNPJ' },
    { value: 'email', label: 'E-mail' },
    { value: 'phone', label: 'Celular' },
    { value: 'random', label: 'Aleatória' },
];

/**
 * Bloco condicional de campos PIX. Renderizar quando payment_type
 * selecionado indicar PIX. Aceita banks vindos do backend.
 *
 * @param {object} value { pix_key_type, pix_key, pix_beneficiary, pix_bank_id }
 * @param {Function} onChange (patch) => void
 * @param {object} errors Erros inline
 * @param {Array} banks Lista de bancos [{id, bank_name, cod_bank}]
 */
export default function PixFieldsSection({ value = {}, onChange, errors = {}, banks = [] }) {
    const set = (patch) => onChange({ ...value, ...patch });

    return (
        <div className="p-4 bg-teal-50 border border-teal-200 rounded-lg space-y-4">
            <p className="text-xs font-semibold text-teal-700 uppercase">
                Dados PIX para devolução
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Tipo de Chave *
                    </label>
                    <select
                        value={value.pix_key_type || ''}
                        onChange={(e) => set({ pix_key_type: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    >
                        <option value="">Selecione...</option>
                        {PIX_KEY_TYPES.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                    {errors.pix_key_type && (
                        <p className="mt-1 text-xs text-red-600">{errors.pix_key_type}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Chave PIX *
                    </label>
                    <input
                        type="text"
                        value={value.pix_key || ''}
                        onChange={(e) => set({ pix_key: e.target.value })}
                        placeholder="Digite a chave PIX"
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                    {errors.pix_key && (
                        <p className="mt-1 text-xs text-red-600">{errors.pix_key}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Nome do Beneficiário *
                    </label>
                    <input
                        type="text"
                        value={value.pix_beneficiary || ''}
                        onChange={(e) => set({ pix_beneficiary: e.target.value })}
                        placeholder="Nome completo do titular da chave"
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                    {errors.pix_beneficiary && (
                        <p className="mt-1 text-xs text-red-600">{errors.pix_beneficiary}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Banco
                    </label>
                    <select
                        value={value.pix_bank_id || ''}
                        onChange={(e) => set({ pix_bank_id: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    >
                        <option value="">—</option>
                        {banks.map((b) => (
                            <option key={b.id} value={b.id}>
                                {b.cod_bank ? `${b.cod_bank} — ` : ''}
                                {b.bank_name}
                            </option>
                        ))}
                    </select>
                    {errors.pix_bank_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.pix_bank_id}</p>
                    )}
                </div>
            </div>
        </div>
    );
}
