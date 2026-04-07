import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function ImportModal({ isOpen, onClose, onSuccess }) {
    const [file, setFile] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [results, setResults] = useState(null);

    useEffect(() => {
        if (isOpen) {
            setFile(null);
            setErrors({});
            setResults(null);
        }
    }, [isOpen]);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!file) {
            setErrors({ file: 'Selecione um arquivo.' });
            return;
        }

        setProcessing(true);
        const formData = new FormData();
        formData.append('file', file);

        router.post('/store-goals/import', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                setProcessing(false);
                onSuccess();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="lg">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Importar Metas</h2>

                <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p className="text-sm font-medium text-blue-900 mb-2">Formato esperado do arquivo:</p>
                    <p className="text-xs text-blue-700 mb-2">CSV ou Excel com as colunas (na primeira linha):</p>
                    <div className="bg-white rounded p-2 font-mono text-xs text-gray-700">
                        codigo_loja;mes;ano;meta;dias_uteis;feriados
                    </div>
                    <p className="text-xs text-blue-600 mt-2">
                        Aceita formatos: .csv, .xlsx, .xls (separador: ; ou ,)
                    </p>
                    <p className="text-xs text-blue-600">
                        Valores monetários aceitos: 150000.00, 150.000,00, 150000
                    </p>
                    <p className="text-xs text-blue-600">
                        Metas existentes (mesma loja/mês/ano) serão atualizadas.
                    </p>
                </div>

                <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-2">Arquivo</label>
                    <input
                        type="file"
                        accept=".csv,.xlsx,.xls,.txt"
                        onChange={(e) => {
                            setFile(e.target.files[0]);
                            setErrors({});
                        }}
                        className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    {errors.file && <p className="mt-1 text-sm text-red-600">{errors.file}</p>}
                </div>

                {results && (
                    <div className="mb-4 bg-gray-50 rounded-lg p-3">
                        <p className="text-sm font-medium text-gray-900">
                            Resultado: {results.created} criadas, {results.updated} atualizadas
                        </p>
                        {results.errors?.length > 0 && (
                            <div className="mt-2 max-h-32 overflow-y-auto">
                                {results.errors.map((err, i) => (
                                    <p key={i} className="text-xs text-red-600">{err}</p>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                <div className="flex justify-end gap-3">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="primary" type="submit" disabled={processing || !file}>
                        {processing ? 'Importando...' : 'Importar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
