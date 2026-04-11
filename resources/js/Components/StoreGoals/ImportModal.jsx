import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { ArrowUpTrayIcon } from '@heroicons/react/24/outline';

export default function ImportModal({ show, onClose, onSuccess }) {
    const [file, setFile] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show) { setFile(null); setErrors({}); }
    }, [show]);

    const handleSubmit = () => {
        if (!file) { setErrors({ file: 'Selecione um arquivo.' }); return; }

        setProcessing(true);
        const formData = new FormData();
        formData.append('file', file);

        router.post('/store-goals/import', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); onSuccess(); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Importar Metas"
            headerColor="bg-indigo-600"
            headerIcon={<ArrowUpTrayIcon className="h-5 w-5" />}
            maxWidth="lg"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel={processing ? 'Importando...' : 'Importar'}
                    processing={processing} disabled={!file} />
            }
        >
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-2">
                <p className="text-sm font-medium text-blue-900">Formato esperado do arquivo:</p>
                <p className="text-xs text-blue-700">CSV ou Excel com as colunas (na primeira linha):</p>
                <div className="bg-white rounded p-2 font-mono text-xs text-gray-700">
                    codigo_loja;mes;ano;meta;dias_uteis;feriados
                </div>
                <p className="text-xs text-blue-600">Aceita formatos: .csv, .xlsx, .xls (separador: ; ou ,)</p>
                <p className="text-xs text-blue-600">Valores monetários aceitos: 150000.00, 150.000,00, 150000</p>
                <p className="text-xs text-blue-600">Metas existentes (mesma loja/mês/ano) serão atualizadas.</p>
            </div>

            <div>
                <InputLabel value="Arquivo *" />
                <input
                    type="file"
                    accept=".csv,.xlsx,.xls,.txt"
                    onChange={(e) => { setFile(e.target.files[0]); setErrors({}); }}
                    className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                />
                <InputError message={errors.file} className="mt-1" />
            </div>
        </StandardModal>
    );
}
