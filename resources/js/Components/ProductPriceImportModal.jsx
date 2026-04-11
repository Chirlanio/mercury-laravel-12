import { useState, useRef } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import { ArrowUpTrayIcon, ArrowPathIcon, ArrowDownTrayIcon } from '@heroicons/react/24/outline';

export default function ProductPriceImportModal({ show, onClose, onCompleted }) {
    const [phase, setPhase] = useState('upload');
    const [file, setFile] = useState(null);
    const [results, setResults] = useState(null);
    const [error, setError] = useState('');
    const fileRef = useRef(null);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (selected) { setFile(selected); setError(''); }
    };

    const handleImport = async () => {
        if (!file) return;
        setPhase('processing');
        setError('');

        const formData = new FormData();
        formData.append('file', file);

        try {
            const { data } = await axios.post('/products/import-prices', formData, {
                timeout: 0,
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setResults(data);
            setPhase('results');
            onCompleted?.();
        } catch (err) {
            setPhase('upload');
            setError(err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Erro ao importar arquivo.');
        }
    };

    const handleClose = () => {
        setPhase('upload'); setFile(null); setResults(null); setError('');
        if (fileRef.current) fileRef.current.value = '';
        onClose();
    };

    const handleNewImport = () => {
        setPhase('upload'); setFile(null); setResults(null); setError('');
        if (fileRef.current) fileRef.current.value = '';
    };

    const footerContent = phase === 'upload' ? (
        <>
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose}>Cancelar</Button>
            <Button variant="primary" onClick={handleImport} disabled={!file} icon={ArrowUpTrayIcon}>Importar</Button>
        </>
    ) : phase === 'results' ? (
        <>
            <div className="flex-1" />
            <Button variant="outline" onClick={handleNewImport} icon={ArrowPathIcon}>Nova Importação</Button>
            <Button variant="primary" onClick={handleClose}>Fechar</Button>
        </>
    ) : null;

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Importar Preços"
            headerColor="bg-indigo-600"
            headerIcon={<ArrowUpTrayIcon className="h-5 w-5" />}
            maxWidth="lg"
            loading={phase === 'processing'}
            errorMessage={error}
            footer={footerContent && <StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {/* Upload Phase */}
            {phase === 'upload' && (
                <>
                    <div className="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <input ref={fileRef} type="file" accept=".xlsx,.xls,.csv"
                            onChange={handleFileChange} className="hidden" id="price-file-input" />
                        <label htmlFor="price-file-input" className="cursor-pointer">
                            <ArrowUpTrayIcon className="mx-auto h-10 w-10 text-gray-400 mb-2" />
                            <p className="text-sm text-gray-600">{file ? file.name : 'Clique para selecionar um arquivo'}</p>
                            <p className="text-xs text-gray-400 mt-1">XLSX, XLS ou CSV (máx. 10MB)</p>
                        </label>
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p className="text-xs text-blue-700 font-medium mb-1">Formato esperado da planilha:</p>
                        <p className="text-xs text-blue-600">
                            Coluna 1: <strong>referencia</strong> | Coluna 2: <strong>preco_venda</strong> | Coluna 3: <strong>custo</strong>
                        </p>
                        <p className="text-xs text-blue-500 mt-1">Formatos aceitos: R$ 1.234,56 | 1234,56 | 1234.56</p>
                    </div>
                </>
            )}

            {/* Results Phase */}
            {phase === 'results' && results && (
                <>
                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <StandardModal.MiniField label="Atualizados" value={(results.success || 0).toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Sem alteração" value={(results.unchanged || 0).toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Bloqueados" value={(results.skipped_locked || 0).toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Não encontrados" value={(results.not_found || 0).toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Rejeitados" value={(results.rejected_count || 0).toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Erros" value={(results.error_count || 0).toLocaleString('pt-BR')} />
                    </div>

                    {results.rejected_url && (
                        <a href={results.rejected_url}
                            className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800" download>
                            <ArrowDownTrayIcon className="h-4 w-4" />
                            Baixar linhas rejeitadas (XLSX)
                        </a>
                    )}

                    {results.errors?.length > 0 && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-md max-h-32 overflow-y-auto">
                            {results.errors.map((e, i) => <p key={i} className="text-xs text-red-700">{e}</p>)}
                        </div>
                    )}
                </>
            )}
        </StandardModal>
    );
}
