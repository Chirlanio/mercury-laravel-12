import { useState, useRef } from 'react';
import axios from 'axios';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function ProductPriceImportModal({ show, onClose, onCompleted }) {
    const [phase, setPhase] = useState('upload'); // upload | processing | results
    const [file, setFile] = useState(null);
    const [results, setResults] = useState(null);
    const [error, setError] = useState('');
    const fileRef = useRef(null);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (selected) {
            setFile(selected);
            setError('');
        }
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
            onCompleted && onCompleted();
        } catch (err) {
            setPhase('upload');
            const msg = err.response?.data?.message || err.response?.data?.errors?.file?.[0] || 'Erro ao importar arquivo.';
            setError(msg);
        }
    };

    const handleClose = () => {
        setPhase('upload');
        setFile(null);
        setResults(null);
        setError('');
        if (fileRef.current) fileRef.current.value = '';
        onClose();
    };

    const handleNewImport = () => {
        setPhase('upload');
        setFile(null);
        setResults(null);
        setError('');
        if (fileRef.current) fileRef.current.value = '';
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="lg">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Importar Preços</h2>

                {/* Upload Phase */}
                {phase === 'upload' && (
                    <div className="space-y-4">
                        <div className="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={handleFileChange}
                                className="hidden"
                                id="price-file-input"
                            />
                            <label htmlFor="price-file-input" className="cursor-pointer">
                                <svg className="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <p className="text-sm text-gray-600">
                                    {file ? file.name : 'Clique para selecionar um arquivo'}
                                </p>
                                <p className="text-xs text-gray-400 mt-1">XLSX, XLS ou CSV (máx. 10MB)</p>
                            </label>
                        </div>

                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p className="text-xs text-blue-700 font-medium mb-1">Formato esperado da planilha:</p>
                            <p className="text-xs text-blue-600">
                                Coluna 1: <strong>referencia</strong> | Coluna 2: <strong>preco_venda</strong> | Coluna 3: <strong>custo</strong>
                            </p>
                            <p className="text-xs text-blue-500 mt-1">
                                Formatos aceitos: R$ 1.234,56 | 1234,56 | 1234.56
                            </p>
                        </div>

                        {error && (
                            <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                                <p className="text-sm text-red-700">{error}</p>
                            </div>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" onClick={handleClose}>Cancelar</Button>
                            <Button variant="primary" onClick={handleImport} disabled={!file}>Importar</Button>
                        </div>
                    </div>
                )}

                {/* Processing Phase */}
                {phase === 'processing' && (
                    <div className="py-8 text-center space-y-4">
                        <div className="animate-spin rounded-full h-10 w-10 border-2 border-indigo-600 border-t-transparent mx-auto" />
                        <p className="text-sm text-gray-600">Processando planilha...</p>
                        <p className="text-xs text-gray-400">Isso pode levar alguns minutos para arquivos grandes.</p>
                    </div>
                )}

                {/* Results Phase */}
                {phase === 'results' && results && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <ResultCard label="Atualizados" value={results.success} color="text-emerald-600" />
                            <ResultCard label="Sem alteração" value={results.unchanged} color="text-gray-500" />
                            <ResultCard label="Bloqueados" value={results.skipped_locked} color="text-amber-600" />
                            <ResultCard label="Não encontrados" value={results.not_found} color="text-red-500" />
                            <ResultCard label="Rejeitados" value={results.rejected_count} color="text-red-600" />
                            <ResultCard label="Erros" value={results.error_count} color="text-red-700" />
                        </div>

                        {results.rejected_url && (
                            <a href={results.rejected_url}
                               className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                               download>
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Baixar linhas rejeitadas (XLSX)
                            </a>
                        )}

                        {results.errors?.length > 0 && (
                            <div className="p-3 bg-red-50 border border-red-200 rounded-md max-h-32 overflow-y-auto">
                                {results.errors.map((e, i) => (
                                    <p key={i} className="text-xs text-red-700">{e}</p>
                                ))}
                            </div>
                        )}

                        <div className="flex justify-end gap-2">
                            <Button variant="secondary" size="sm" onClick={handleNewImport}>Nova Importação</Button>
                            <Button variant="primary" size="sm" onClick={handleClose}>Fechar</Button>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function ResultCard({ label, value, color }) {
    return (
        <div className="bg-gray-50 rounded-lg p-3 text-center">
            <p className={`text-lg font-semibold ${color}`}>{(value || 0).toLocaleString('pt-BR')}</p>
            <p className="text-xs text-gray-500">{label}</p>
        </div>
    );
}
