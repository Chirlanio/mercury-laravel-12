import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { ArrowUpTrayIcon } from '@heroicons/react/24/outline';

export default function ImportModal({ show, onClose, audit, onSuccess }) {
    const [file, setFile] = useState(null);
    const [round, setRound] = useState(1);
    const [areaId, setAreaId] = useState('');
    const [areas, setAreas] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [result, setResult] = useState(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

    useEffect(() => {
        if (show && audit) {
            fetch(route('stock-audits.areas', audit.id), { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() } })
                .then(r => r.json()).then(json => setAreas(json.areas || [])).catch(() => {});
        }
    }, [show, audit]);

    const handleSubmit = async () => {
        if (!file) { setErrors({ file: 'Selecione um arquivo.' }); return; }
        setProcessing(true); setErrors({}); setResult(null);
        const formData = new FormData();
        formData.append('file', file); formData.append('round', round);
        if (areaId) formData.append('area_id', areaId);
        try {
            const res = await fetch(route('stock-audits.import', audit.id), {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }, body: formData,
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message }); return; }
            setResult(json); setFile(null);
            const input = document.getElementById('sa-import-file'); if (input) input.value = '';
            onSuccess?.();
        } catch { setErrors({ general: 'Erro de conexão.' }); } finally { setProcessing(false); }
    };

    const handleClose = () => { setFile(null); setRound(1); setAreaId(''); setErrors({}); setResult(null); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Importar Contagem"
            headerColor="bg-indigo-600" headerIcon={<ArrowUpTrayIcon className="h-5 w-5" />}
            maxWidth="lg" onSubmit={handleSubmit} errorMessage={errors.general}
            footer={
                <StandardModal.Footer onCancel={handleClose}>
                    <div className="flex-1" />
                    <Button variant="outline" onClick={handleClose}>Fechar</Button>
                    <Button variant="primary" onClick={handleSubmit} disabled={processing || !file}
                        loading={processing} icon={ArrowUpTrayIcon}>
                        Importar Arquivo
                    </Button>
                </StandardModal.Footer>
            }>

            {/* Arquivo */}
            <div>
                <InputLabel value="Arquivo (CSV/TXT) *" />
                <input id="sa-import-file" type="file" accept=".csv,.txt" onChange={(e) => { setFile(e.target.files[0]); setResult(null); setErrors({}); }}
                    className="mt-1 w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                {file && <p className="mt-1 text-xs text-gray-500">{file.name} ({(file.size / 1024).toFixed(1)} KB)</p>}
                <InputError message={errors.file} className="mt-1" />
            </div>

            {/* Round */}
            <div>
                <InputLabel value="Contagem (Round)" />
                <div className="flex gap-3 mt-1">
                    {[1, 2, 3].map(r => (
                        <label key={r} className={`flex-1 flex items-center justify-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors ${
                            round === r ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-gray-200 hover:bg-gray-50 text-gray-700'
                        }`}>
                            <input type="radio" name="round" value={r} checked={round === r}
                                onChange={() => setRound(r)} className="text-indigo-600 focus:ring-indigo-500" />
                            <span className="text-sm font-medium">{r}a Contagem</span>
                        </label>
                    ))}
                </div>
            </div>

            {/* Área */}
            <div>
                <InputLabel value="Área (opcional)" />
                <select value={areaId} onChange={(e) => setAreaId(e.target.value)}
                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Todas as áreas</option>
                    {areas.map(a => <option key={a.id} value={a.id}>{a.name}</option>)}
                </select>
            </div>

            {/* Resultado */}
            {result && (
                <StandardModal.Section title="Resultado da Importação">
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 -mx-4 -mb-4 px-4 pb-4">
                        <StandardModal.InfoCard label="Total" value={(result.total ?? 0).toLocaleString()} colorClass="bg-blue-50" />
                        <StandardModal.InfoCard label="Sucesso" value={(result.success ?? 0).toLocaleString()} colorClass="bg-green-50" />
                        <StandardModal.InfoCard label="Ignorados" value={(result.skipped ?? 0).toLocaleString()} colorClass="bg-yellow-50" />
                        <StandardModal.InfoCard label="Erros" value={(result.errors_count ?? result.errors?.length ?? 0).toLocaleString()} colorClass="bg-red-50" />
                    </div>
                    {result.errors?.length > 0 && (
                        <div className="mt-3 bg-red-50 border border-red-200 rounded-lg p-3 max-h-40 overflow-y-auto">
                            <ul className="text-xs text-red-600 space-y-1">
                                {result.errors.map((err, i) => <li key={i}>{typeof err === 'string' ? err : `Linha ${err.line}: ${err.message}`}</li>)}
                            </ul>
                        </div>
                    )}
                    {result.message && (
                        <div className="mt-3 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-700">{result.message}</div>
                    )}
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
