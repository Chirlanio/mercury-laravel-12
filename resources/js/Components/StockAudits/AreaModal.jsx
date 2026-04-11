import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { MapPinIcon, TrashIcon, PlusIcon } from '@heroicons/react/24/outline';

export default function AreaModal({ show, onClose, audit, onSuccess }) {
    const [areas, setAreas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [name, setName] = useState('');
    const [sortOrder, setSortOrder] = useState(0);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const fetchAreas = async () => {
        if (!audit) return;
        setLoading(true);
        try {
            const res = await fetch(route('stock-audits.areas', audit.id), { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() } });
            setAreas((await res.json()).areas || []);
        } catch {} finally { setLoading(false); }
    };

    useEffect(() => { if (show && audit) fetchAreas(); }, [show, audit]);

    const resetForm = () => { setName(''); setSortOrder(0); setErrors({}); };

    const handleAdd = async () => {
        if (!name.trim()) { setErrors({ name: 'O nome da área é obrigatório.' }); return; }
        setProcessing(true); setErrors({});
        try {
            const res = await fetch(route('stock-audits.areas', audit.id), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ action: 'add', name: name.trim(), sort_order: parseInt(sortOrder) || 0 }),
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message }); return; }
            resetForm(); fetchAreas(); onSuccess?.();
        } catch { setErrors({ general: 'Erro de conexão.' }); } finally { setProcessing(false); }
    };

    const handleRemove = async (areaId) => {
        try {
            const res = await fetch(route('stock-audits.areas', audit.id), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ action: 'remove', area_id: areaId }),
            });
            if (res.ok) { fetchAreas(); onSuccess?.(); }
        } catch {}
    };

    const handleClose = () => { resetForm(); setAreas([]); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Áreas da Auditoria"
            headerColor="bg-indigo-600" headerIcon={<MapPinIcon className="h-5 w-5" />}
            errorMessage={errors.general} maxWidth="lg"
            footer={<StandardModal.Footer onCancel={handleClose} cancelLabel="Fechar" />}>

            {/* Áreas Cadastradas */}
            <StandardModal.Section title="Áreas Cadastradas">
                {loading ? (
                    <div className="flex items-center justify-center py-6">
                        <div className="animate-spin rounded-full h-6 w-6 border-2 border-indigo-600 border-t-transparent" />
                    </div>
                ) : areas.length === 0 ? (
                    <p className="text-center text-gray-500 py-4">Nenhuma área cadastrada.</p>
                ) : (
                    <div className="space-y-2 -mx-4 -mb-4 px-4 pb-4">
                        {areas.map((area) => (
                            <div key={area.id} className="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-100">
                                <div className="flex items-center gap-3">
                                    <span className="text-xs text-gray-400 font-mono w-6 text-right">#{area.sort_order ?? 0}</span>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{area.name}</p>
                                        <StatusBadge variant="indigo" size="sm">{area.items_count ?? 0} itens</StatusBadge>
                                    </div>
                                </div>
                                <button onClick={() => handleRemove(area.id)} className="text-red-500 hover:text-red-700 p-1">
                                    <TrashIcon className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </StandardModal.Section>

            {/* Adicionar Área */}
            <StandardModal.Section title="Adicionar Área">
                <div className="space-y-3 -mx-4 -mb-4 px-4 pb-4">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2">
                            <InputLabel value="Nome da Área *" />
                            <TextInput className="mt-1 w-full" value={name} onChange={(e) => setName(e.target.value)}
                                placeholder="Ex: Salão de Vendas, Estoque, Vitrine..." />
                            <InputError message={errors.name} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Ordem" />
                            <TextInput type="number" min={0} className="mt-1 w-full" value={sortOrder}
                                onChange={(e) => setSortOrder(e.target.value)} />
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button variant="primary" size="sm" icon={PlusIcon} onClick={handleAdd} loading={processing}>
                            Adicionar Área
                        </Button>
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
