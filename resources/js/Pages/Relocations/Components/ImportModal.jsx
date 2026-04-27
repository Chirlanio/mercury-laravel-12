import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    ArrowUpTrayIcon,
    ArrowDownTrayIcon,
    DocumentArrowUpIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';

const TEMPLATE_HEADERS_REF = [
    'Origem', 'Destino', 'Referencia', 'Tamanho', 'Quantidade',
];

const TEMPLATE_HEADERS_BARCODE = [
    'Origem', 'Destino', 'Codigo_Barras', 'Quantidade',
];

const TEMPLATE_HEADERS_EXTRA = [
    'titulo', 'tipo', 'prioridade', 'prazo', 'produto', 'cor', 'observacao',
];

/**
 * Modal de import. Fluxo em 3 estados:
 *  1. Upload — usuário escolhe arquivo
 *  2. Preview — mostra sample + erros, usuário decide importar ou voltar
 *  3. Submit — POST persiste e redireciona com flash message
 */
export default function ImportModal({ show, onClose }) {
    const fileInputRef = useRef(null);
    const [step, setStep] = useState('upload');  // upload | preview
    const [file, setFile] = useState(null);
    const [previewData, setPreviewData] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const reset = () => {
        setStep('upload');
        setFile(null);
        setPreviewData(null);
        setError(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const onFileChange = (e) => {
        setFile(e.target.files[0] || null);
        setPreviewData(null);
        setError(null);
    };

    const submitPreview = async () => {
        if (!file) return;
        setPreviewing(true);
        setError(null);
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await window.axios.post(route('relocations.import.preview'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setPreviewData(res.data);
            setStep('preview');
        } catch (e) {
            setError(e.response?.data?.error || e.response?.data?.message || 'Falha ao gerar preview.');
        } finally {
            setPreviewing(false);
        }
    };

    const submitImport = () => {
        if (!file) return;
        setSubmitting(true);

        const formData = new FormData();
        formData.append('file', file);

        router.post(route('relocations.import.store'), formData, {
            forceFormData: true,
            onSuccess: () => {
                reset();
                onClose();
            },
            onError: (errs) => {
                setError(errs?.file || 'Falha ao importar.');
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Importar remanejos"
            subtitle={
                step === 'upload'
                    ? 'Envie uma planilha (XLSX/CSV) com 1 linha por item'
                    : `${previewData?.total_rows ?? 0} linhas analisadas`
            }
            headerColor="bg-indigo-600"
            headerIcon={<ArrowUpTrayIcon className="h-5 w-5" />}
            maxWidth="5xl"
            errorMessage={error}
            footer={
                step === 'upload' ? (
                    <StandardModal.Footer
                        onCancel={handleClose}
                        onSubmit={submitPreview}
                        submitLabel="Analisar planilha"
                        processing={previewing}
                        submitDisabled={!file}
                    />
                ) : (
                    <StandardModal.Footer>
                        <div className="flex w-full items-center justify-between gap-2">
                            <Button variant="outline" onClick={() => setStep('upload')}>
                                Voltar
                            </Button>
                            <div className="flex gap-2">
                                <Button variant="outline" onClick={handleClose}>
                                    Cancelar
                                </Button>
                                <Button
                                    variant="primary"
                                    icon={DocumentArrowUpIcon}
                                    onClick={submitImport}
                                    loading={submitting}
                                    disabled={!previewData || previewData.valid_rows === 0}
                                >
                                    Importar {previewData?.groups_count ?? 0} remanejo(s) com {previewData?.valid_rows ?? 0} item(ns)
                                </Button>
                            </div>
                        </div>
                    </StandardModal.Footer>
                )
            }
        >
            {step === 'upload' && (
                <>
                    <StandardModal.Section title="Planilha">
                        <p className="text-sm text-gray-600 mb-3">
                            Aceita XLSX, XLS ou CSV (máx. 10MB), incluindo o formato v1 do
                            Mercury (separador <code className="bg-gray-100 px-1 rounded">;</code>).
                            Linhas com a mesma combinação de <strong>origem + destino + título</strong>
                            são agrupadas no mesmo remanejo. Cada item é casado com o catálogo
                            via <strong>código de barras</strong> (recomendado, match exato) ou
                            via <strong>referência + tamanho</strong>.
                        </p>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={onFileChange}
                            className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        />
                        {file && (
                            <p className="mt-2 text-xs text-gray-600">
                                Selecionado: <strong>{file.name}</strong> ({(file.size / 1024).toFixed(1)} KB)
                            </p>
                        )}
                    </StandardModal.Section>

                    <StandardModal.Section title="Baixar modelo">
                        <p className="text-sm text-gray-600 mb-3">
                            Faça download de uma planilha modelo já preenchida com produtos
                            reais do seu catálogo — basta editar as quantidades, lojas e
                            importar de volta.
                        </p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <a
                                href={route('relocations.import.template', { mode: 'barcode' })}
                                download
                                className="flex items-center gap-3 p-3 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 transition-colors"
                            >
                                <ArrowDownTrayIcon className="h-5 w-5 text-emerald-700 shrink-0" />
                                <div className="min-w-0">
                                    <div className="text-sm font-semibold text-emerald-900">
                                        Modelo — Código de barras
                                    </div>
                                    <div className="text-xs text-emerald-700">
                                        4 colunas · match exato (recomendado)
                                    </div>
                                </div>
                            </a>
                            <a
                                href={route('relocations.import.template', { mode: 'reference' })}
                                download
                                className="flex items-center gap-3 p-3 rounded-lg border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 transition-colors"
                            >
                                <ArrowDownTrayIcon className="h-5 w-5 text-indigo-700 shrink-0" />
                                <div className="min-w-0">
                                    <div className="text-sm font-semibold text-indigo-900">
                                        Modelo — Referência + Tamanho
                                    </div>
                                    <div className="text-xs text-indigo-700">
                                        5 colunas · formato v1 legado
                                    </div>
                                </div>
                            </a>
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Modo 1 — Código de barras (recomendado)">
                        <p className="text-sm text-gray-600 mb-2">
                            Match exato pelo cadastro de variantes (<code>S2235800040003U40 </code>
                            ou <code> 7900334498180</code> = EAN13). Não exige tamanho — o sistema
                            descobre o produto e o tamanho a partir do código.
                        </p>
                        <div className="bg-emerald-50 border border-emerald-200 rounded p-3 font-mono text-xs">
                            {TEMPLATE_HEADERS_BARCODE.join(';')}
                        </div>
                        <p className="text-xs text-gray-500 mt-2">
                            Aliases aceitos: <code>codigo_barras</code>, <code>cod_barras</code>,
                            <code> ean</code>, <code>ean13</code>, <code>barcode</code>,
                            <code> aux_reference</code>.
                        </p>
                    </StandardModal.Section>

                    <StandardModal.Section title="Modo 2 — Referência + Tamanho (formato v1 legado)">
                        <p className="text-sm text-gray-600 mb-2">
                            Cabeçalho exato da planilha v1 (CSV separado por <code className="bg-gray-100 px-1 rounded">;</code>):
                        </p>
                        <div className="bg-indigo-50 border border-indigo-200 rounded p-3 font-mono text-xs">
                            {TEMPLATE_HEADERS_REF.join(';')}
                        </div>
                        <p className="text-xs text-gray-500 mt-2">
                            Funciona com layout "single" (mesmo par origem-destino em todas as
                            linhas) ou "multiple" (vários pares — gera um remanejo por par).
                            O tamanho é o <strong>label comercial</strong> (ex: 34, 36, 38, P, M, G, UN).
                        </p>
                    </StandardModal.Section>

                    <StandardModal.Section title="Colunas extras opcionais">
                        <p className="text-sm text-gray-600 mb-2">
                            Aceita variações em maiúscula/minúscula, com ou sem acento. Colunas
                            obrigatórias: <strong>Origem</strong>, <strong>Destino</strong>,
                            <strong> Quantidade</strong> + (<strong>Codigo_Barras</strong> OU
                            <strong> Referencia</strong>+<strong>Tamanho</strong>). As demais
                            enriquecem o cabeçalho do remanejo:
                        </p>
                        <div className="bg-gray-50 rounded p-3 font-mono text-xs flex flex-wrap gap-1">
                            {TEMPLATE_HEADERS_EXTRA.map((h) => (
                                <span key={h} className="bg-white border rounded px-2 py-0.5">{h}</span>
                            ))}
                        </div>
                    </StandardModal.Section>
                </>
            )}

            {step === 'preview' && previewData && (
                <>
                    <StandardModal.Section title="Resumo">
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <StandardModal.InfoCard label="Total linhas" value={previewData.total_rows} />
                            <StandardModal.InfoCard label="Válidas" value={previewData.valid_rows} />
                            <StandardModal.InfoCard label="Com erro" value={previewData.invalid_rows} />
                            <StandardModal.InfoCard label="Remanejos" value={previewData.groups_count} />
                        </div>

                        <h4 className="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-2">
                            Match com catálogo
                        </h4>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div className="rounded-lg p-3 bg-emerald-50 border border-emerald-200">
                                <div className="text-[10px] uppercase font-semibold text-emerald-700">
                                    ✓ Por código de barras
                                </div>
                                <div className="text-2xl font-bold text-emerald-900 tabular-nums mt-1">
                                    {previewData.resolved_by_barcode ?? 0}
                                </div>
                                <div className="text-xs text-emerald-700">match exato</div>
                            </div>
                            <div className="rounded-lg p-3 bg-blue-50 border border-blue-200">
                                <div className="text-[10px] uppercase font-semibold text-blue-700">
                                    ✓ Por referência + tamanho
                                </div>
                                <div className="text-2xl font-bold text-blue-900 tabular-nums mt-1">
                                    {previewData.resolved_by_reference ?? 0}
                                </div>
                                <div className="text-xs text-blue-700">via product_sizes.name</div>
                            </div>
                            <div className={`rounded-lg p-3 border ${(previewData.unresolved ?? 0) > 0 ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200'}`}>
                                <div className="text-[10px] uppercase font-semibold text-gray-700">
                                    ⚠ Sem match no catálogo
                                </div>
                                <div className={`text-2xl font-bold tabular-nums mt-1 ${(previewData.unresolved ?? 0) > 0 ? 'text-amber-900' : 'text-gray-700'}`}>
                                    {previewData.unresolved ?? 0}
                                </div>
                                <div className="text-xs text-gray-600">
                                    {(previewData.unresolved ?? 0) > 0
                                        ? 'serão importados sem product_id'
                                        : 'todos resolvidos'}
                                </div>
                            </div>
                        </div>
                    </StandardModal.Section>

                    {previewData.errors?.length > 0 && (
                        <StandardModal.Section
                            title={`Erros encontrados (${previewData.errors.length})`}
                        >
                            <div className="bg-red-50 border border-red-200 rounded p-3 max-h-48 overflow-y-auto">
                                {previewData.errors.map((err, i) => (
                                    <div key={i} className="text-xs text-red-800 font-mono">
                                        <ExclamationTriangleIcon className="inline h-3 w-3 mr-1" />
                                        {err}
                                    </div>
                                ))}
                            </div>
                            <p className="text-xs text-gray-600 mt-2">
                                Linhas com erro serão ignoradas. Apenas as válidas serão importadas.
                            </p>
                        </StandardModal.Section>
                    )}

                    {previewData.sample?.length > 0 && (
                        <StandardModal.Section title={`Amostra (${previewData.sample.length} linhas)`}>
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-xs">
                                    <thead className="bg-gray-50 uppercase text-gray-600">
                                        <tr>
                                            <th className="px-2 py-2 text-left">Origem</th>
                                            <th className="px-2 py-2 text-left">Destino</th>
                                            <th className="px-2 py-2 text-left">Referência</th>
                                            <th className="px-2 py-2 text-left">Produto</th>
                                            <th className="px-2 py-2 text-right">Qtd.</th>
                                            <th className="px-2 py-2 text-left">Tamanho</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {previewData.sample.map((row, i) => (
                                            <tr key={i}>
                                                <td className="px-2 py-2 font-mono">{row.origin_code}</td>
                                                <td className="px-2 py-2 font-mono">{row.destination_code}</td>
                                                <td className="px-2 py-2 font-mono">{row.product_reference}</td>
                                                <td className="px-2 py-2">{row.product_name || '—'}</td>
                                                <td className="px-2 py-2 text-right tabular-nums">{row.qty_requested}</td>
                                                <td className="px-2 py-2">{row.size || '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </StandardModal.Section>
                    )}

                    {previewData.valid_rows > 0 && (
                        <StandardModal.Highlight>
                            <CheckCircleIcon className="inline h-4 w-4 text-green-600 mr-1" />
                            Pronto pra importar <strong>{previewData.groups_count}</strong> remanejo(s)
                            com <strong>{previewData.valid_rows}</strong> item(ns) totais. Todos
                            nascerão em estado <strong>Rascunho</strong>.
                        </StandardModal.Highlight>
                    )}
                </>
            )}
        </StandardModal>
    );
}
