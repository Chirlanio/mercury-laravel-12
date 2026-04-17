import { useRef } from 'react';
import { PaperClipIcon, XMarkIcon, CloudArrowUpIcon } from '@heroicons/react/24/outline';

/**
 * Upload múltiplo de arquivos para estorno (NF digitalizada, comprovante
 * de chargeback, print do cliente, etc.). Mantém a lista em memória até
 * o submit do formulário.
 *
 * @param {File[]} files Arquivos selecionados
 * @param {Function} onChange (File[]) => void
 * @param {number} maxFiles Limite de anexos (default 10)
 * @param {number} maxSizeMB Tamanho máximo por arquivo em MB (default 10)
 */
export default function ReversalFilesUpload({
    files = [],
    onChange,
    maxFiles = 10,
    maxSizeMB = 10,
}) {
    const inputRef = useRef(null);

    const handleSelect = (e) => {
        const list = Array.from(e.target.files || []);
        const valid = list.filter((f) => f.size <= maxSizeMB * 1024 * 1024);
        const next = [...files, ...valid].slice(0, maxFiles);
        onChange(next);
        if (inputRef.current) inputRef.current.value = '';
    };

    const removeAt = (idx) => {
        const next = [...files];
        next.splice(idx, 1);
        onChange(next);
    };

    const formatSize = (bytes) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    };

    return (
        <div>
            <input
                ref={inputRef}
                type="file"
                multiple
                onChange={handleSelect}
                className="hidden"
                accept=".pdf,.png,.jpg,.jpeg,.webp,.xlsx,.xls,.csv"
            />

            <button
                type="button"
                onClick={() => inputRef.current?.click()}
                disabled={files.length >= maxFiles}
                className="w-full border-2 border-dashed border-gray-300 rounded-lg p-4 text-center text-gray-600 hover:border-indigo-400 hover:text-indigo-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <CloudArrowUpIcon className="h-8 w-8 mx-auto mb-2" />
                <p className="text-sm font-medium">
                    Clique para selecionar arquivos
                </p>
                <p className="text-xs text-gray-400 mt-1">
                    PDF, imagens ou planilhas — máx {maxSizeMB}MB cada, até {maxFiles} arquivos
                </p>
            </button>

            {files.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {files.map((file, idx) => (
                        <li
                            key={`${file.name}-${idx}`}
                            className="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg px-3 py-2"
                        >
                            <div className="flex items-center gap-2 min-w-0">
                                <PaperClipIcon className="h-4 w-4 text-gray-400 flex-shrink-0" />
                                <span className="text-sm text-gray-700 truncate">
                                    {file.name}
                                </span>
                                <span className="text-xs text-gray-400 flex-shrink-0">
                                    {formatSize(file.size)}
                                </span>
                            </div>
                            <button
                                type="button"
                                onClick={() => removeAt(idx)}
                                className="ml-2 text-gray-400 hover:text-red-600"
                                title="Remover"
                            >
                                <XMarkIcon className="h-4 w-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
