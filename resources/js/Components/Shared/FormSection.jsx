/**
 * Wrapper para secoes de formulario agrupadas com titulo e grid responsivo.
 *
 * @param {string} title - Titulo da secao
 * @param {number} cols - Numero de colunas no grid (1, 2, 3 ou 4). Default: 2
 * @param {React.ReactNode} children - Campos do formulario
 * @param {string} className - Classes adicionais no wrapper (opcional)
 *
 * @example
 * <FormSection title="Dados Pessoais">
 *   <div>
 *     <InputLabel value="Nome" />
 *     <TextInput value={data.name} onChange={e => setData('name', e.target.value)} />
 *     <InputError message={errors.name} />
 *   </div>
 *   <div>
 *     <InputLabel value="CPF" />
 *     <TextInput value={data.cpf} onChange={e => setData('cpf', e.target.value)} />
 *   </div>
 * </FormSection>
 *
 * <FormSection title="Endereco" cols={3}>
 *   ...
 * </FormSection>
 */

const GRID_COLS = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
};

export default function FormSection({ title, cols = 2, children, className = '' }) {
    const gridClass = GRID_COLS[cols] || GRID_COLS[2];

    return (
        <div className={`bg-gray-50 p-4 rounded-lg ${className}`}>
            {title && (
                <h4 className="text-sm font-medium text-gray-900 mb-4">
                    {title}
                </h4>
            )}
            <div className={`grid ${gridClass} gap-4`}>
                {children}
            </div>
        </div>
    );
}
