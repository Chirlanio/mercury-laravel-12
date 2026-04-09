import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function ImportModal({ show, onClose, audit, onSuccess }) {
    const [file, setFile] = useState(null);
    const [round, setRound] = useState(1);
    const [areaId, setAreaId] = useState('');
    const [areas, setAreas] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [result, setResult] = useState(null);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    useEffect(() => {
        if (show && audit) {
            // Fetch areas for the selector
            fetch(route('stock-audits.areas', audit.id), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            })
                .then((res) => res.json())
                .then((json) => setAreas(json.areas || []))
                .catch(() => {});
        }
    }, [show, audit]);

    const handleFileChange = (e) => {
        const selected = e.target.files[0];
        if (selected) {
            setFile(selected);
            setResult(null);
            setErrors({});
        }
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!file) {
            setErrors({ file: 'Selecione um arquivo para importar.' });
            return;
        }

        setProcessing(true);
        setErrors({});
        setResult(null);

        const formData = new FormData();
        formData.append('file', file);
        formData.append('round', round);
        if (areaId) {
            formData.append('area_id', areaId);
        }

        try {
            const res = await fetch(route('stock-audits.import', audit.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: formData,
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao importar arquivo.' });
                return;
            }

            setResult(json);
            setFile(null);
            // Reset file input
            const fileInput = document.getElementById('import-file-input');
            if (fileInput) fileInput.value = '';
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleClose = () => {
        setFile(null);
        setRound(1);
        setAreaId('');
        setErrors({});
        setResult(null);
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="lg">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Importar Contagem</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6 space-y-5">
                    {errors.general && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                            {errors.general}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* File Input */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Arquivo (CSV/TXT) <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="import-file-input"
                                type="file"
                                accept=".csv,.txt"
                                onChange={handleFileChange}
                                className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            {file && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Arquivo selecionado: {file.name} ({(file.size / 1024).toFixed(1)} KB)
                                </p>
                            )}
                            {errors.file && <p className="mt-1 text-sm text-red-600">{errors.file}</p>}
                        </div>

                        {/* Round Selector */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Contagem (Round)
                            </label>
                            <div className="flex gap-3">
                                {[1, 2, 3].map((r) => (
                                    <label
                                        key={r}
                                        className={`flex-1 flex items-center justify-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors ${
                                            round === r
                                                ? 'border-indigo-300 bg-indigo-50 text-indigo-700'
                                                : 'border-gray-200 hover:bg-gray-50 text-gray-700'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="round"
                                            value={r}
                                            checked={round === r}
                                            onChange={() => setRound(r)}
                                            className="text-indigo-600 focus:ring-indigo-500"
                                        />
                                        <span className="text-sm font-medium">{r}a Contagem</span>
                                    </label>
                                ))}
                            </div>
                            {errors.round && <p className="mt-1 text-sm text-red-600">{errors.round}</p>}
                        </div>

                        {/* Area Selector */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Area (opcional)
                            </label>
                            <select
                                value={areaId}
                                onChange={(e) => setAreaId(e.target.value)}
                                className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                <option value="">Todas as areas</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>{area.name}</option>
                                ))}
                            </select>
                            {errors.area_id && <p className="mt-1 text-sm text-red-600">{errors.area_id}</p>}
                        </div>

                        {/* Upload button */}
                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                disabled={processing || !file}
                                loading={processing}
                            >
                                {processing ? 'Importando...' : 'Importar Arquivo'}
                            </Button>
                        </div>
                    </form>

                    {/* Results */}
                    {result && (
                        <div className="border-t border-gray-200 pt-5 space-y-3">
                            <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider">
                                Resultado da Importacao
                            </h3>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div className="bg-blue-50 rounded-lg p-3 text-center">
                                    <div className="text-xl font-bold text-blue-600">
                                        {(result.total ?? 0).toLocaleString()}
                                    </div>
                                    <div className="text-xs text-gray-500">Total</div>
                                </div>
                                <div className="bg-green-50 rounded-lg p-3 text-center">
                                    <div className="text-xl font-bold text-green-600">
                                        {(result.success ?? 0).toLocaleString()}
                                    </div>
                                    <div className="text-xs text-gray-500">Sucesso</div>
                                </div>
                                <div className="bg-yellow-50 rounded-lg p-3 text-center">
                                    <div className="text-xl font-bold text-yellow-600">
                                        {(result.skipped ?? 0).toLocaleString()}
                                    </div>
                                    <div className="text-xs text-gray-500">Ignorados</div>
                                </div>
                                <div className="bg-red-50 rounded-lg p-3 text-center">
                                    <div className="text-xl font-bold text-red-600">
                                        {(result.errors_count ?? result.errors?.length ?? 0).toLocaleString()}
                                    </div>
                                    <div className="text-xs text-gray-500">Erros</div>
                                </div>
                            </div>

                            {result.errors && result.errors.length > 0 && (
                                <div className="bg-red-50 border border-red-200 rounded-lg p-3 max-h-40 overflow-y-auto">
                                    <p className="text-sm font-medium text-red-700 mb-2">Erros encontrados:</p>
                                    <ul className="text-xs text-red-600 space-y-1">
                                        {result.errors.map((err, idx) => (
                                            <li key={idx}>
                                                {typeof err === 'string' ? err : `Linha ${err.line}: ${err.message}`}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {result.message && (
                                <div className="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">
                                    {result.message}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <Button variant="secondary" onClick={handleClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
