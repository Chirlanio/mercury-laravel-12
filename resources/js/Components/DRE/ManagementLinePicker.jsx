import { useMemo } from 'react';
import { CheckCircleIcon } from '@heroicons/react/24/outline';

/**
 * Select agrupado por subtotal. Cada grupo é delimitado pelo próximo
 * subtotal encontrado em ordem — visualmente ajuda o usuário a enxergar
 * "até onde" cada bloco vai (até Faturamento Líquido, até ROL, etc.).
 *
 * Props:
 *   - lines: array [{id, code, label, is_subtotal, sort_order, nature}]
 *   - value: id atualmente selecionado (ou null)
 *   - onChange: (id|null) => void
 *   - disabled: boolean
 */
export default function ManagementLinePicker({
    lines = [],
    value = null,
    onChange,
    disabled = false,
    placeholder = 'Selecione uma linha gerencial…',
    allowEmpty = true,
    id,
    name,
}) {
    const groups = useMemo(() => {
        const ordered = [...lines].sort((a, b) => {
            if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
            return (a.is_subtotal ? 1 : 0) - (b.is_subtotal ? 1 : 0);
        });

        const blocks = [];
        let currentBlock = { title: 'Abertura do DRE', items: [] };

        ordered.forEach((line) => {
            currentBlock.items.push(line);
            if (line.is_subtotal) {
                blocks.push(currentBlock);
                currentBlock = { title: `Após ${line.label}`, items: [] };
            }
        });

        if (currentBlock.items.length > 0) blocks.push(currentBlock);

        return blocks;
    }, [lines]);

    return (
        <select
            id={id}
            name={name}
            value={value ?? ''}
            disabled={disabled}
            onChange={(e) => {
                const v = e.target.value;
                onChange(v === '' ? null : Number(v));
            }}
            className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full disabled:bg-gray-50 disabled:text-gray-400"
        >
            {allowEmpty && (
                <option value="">{placeholder}</option>
            )}
            {groups.map((block, idx) => (
                <optgroup key={idx} label={block.title}>
                    {block.items.map((line) => (
                        <option
                            key={line.id}
                            value={line.id}
                            style={{
                                fontWeight: line.is_subtotal ? 600 : 400,
                            }}
                        >
                            {line.is_subtotal ? '▸ ' : '  '}
                            {line.label}
                        </option>
                    ))}
                </optgroup>
            ))}
        </select>
    );
}
