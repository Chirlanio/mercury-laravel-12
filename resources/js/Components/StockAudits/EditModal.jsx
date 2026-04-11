import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import { AuditFormFields } from '@/Components/StockAudits/CreateModal';
import { PencilSquareIcon } from '@heroicons/react/24/outline';

export default function EditModal({ show, onClose, audit, stores = [], onSuccess }) {
    const [vendors, setVendors] = useState([]);
    const [auditCycles, setAuditCycles] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [loadingOptions, setLoadingOptions] = useState(false);

    const { data, setData, put, processing, errors, reset } = useForm({
        store_id: '', audit_type: 'total', vendor_id: '', audit_cycle_id: '',
        manager_responsible_id: '', stockist_id: '', random_sample_size: 10,
        requires_second_count: true, requires_third_count: false, notes: '',
    });

    useEffect(() => {
        if (!show) return;
        setLoadingOptions(true);
        fetch(route('stock-audits.create-options'), {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        })
            .then(r => r.json())
            .then(json => { setVendors(json.vendors || []); setAuditCycles(json.audit_cycles || []); setEmployees(json.employees || []); })
            .catch(() => {})
            .finally(() => setLoadingOptions(false));
    }, [show]);

    useEffect(() => {
        if (audit && show) {
            setData({
                store_id: audit.store_id || '', audit_type: audit.audit_type || 'total',
                vendor_id: audit.vendor_id || '', audit_cycle_id: audit.audit_cycle_id || '',
                manager_responsible_id: audit.manager_responsible_id || '', stockist_id: audit.stockist_id || '',
                random_sample_size: audit.random_sample_size || 10,
                requires_second_count: audit.requires_second_count ?? true,
                requires_third_count: audit.requires_third_count ?? false, notes: audit.notes || '',
            });
        }
    }, [audit, show]);

    useEffect(() => {
        if (data.audit_type === 'total') setData('requires_second_count', true);
    }, [data.audit_type]);

    const handleSubmit = () => {
        if (!audit) return;
        put(route('stock-audits.update', audit.id), { onSuccess: () => { reset(); onSuccess?.(); } });
    };

    const handleClose = () => { reset(); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose}
            title={`Editar Auditoria #${audit?.id}`}
            headerColor="bg-yellow-600" headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={<StandardModal.Footer onCancel={handleClose} onSubmit="submit"
                submitLabel="Salvar Alterações" submitColor="bg-yellow-600 hover:bg-yellow-700" processing={processing} />}>

            <AuditFormFields data={data} setData={setData} errors={errors} stores={stores}
                vendors={vendors} auditCycles={auditCycles} employees={employees} loadingOptions={loadingOptions} />
        </StandardModal>
    );
}
