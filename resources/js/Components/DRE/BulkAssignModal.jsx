import { useState } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/Button';
import ManagementLinePicker from '@/Components/DRE/ManagementLinePicker';

/**
 * Modal para atribuir N contas a uma mesma linha gerencial (+ CC opcional).
 *
 * Props:
 *   - show: boolean
 *   - onClose: () => void
 *   - selectedAccountIds: number[]
 *   - managementLines: array pro picker
 *   - costCenters: array [{id, code, name, label}]
 *   - defaultEffectiveFrom: 'YYYY-MM-DD'
 */
export default function BulkAssignModal({
    show,
    onClose,
    selectedAccountIds = [],
    managementLines = [],
    costCenters = [],
    defaultEffectiveFrom = new Date().toISOString().slice(0, 10),
}) {
    const [form, setForm] = useState({
        management_line_id: null,
        cost_center_id: null,
        effective_from: defaultEffectiveFrom,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        if (!form.management_line_id) {
            setErrors({ dre_management_line_id: 'Selecione a linha gerencial.' });
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            route('dre.mappings.bulk'),
            {
                account_ids: selectedAccountIds,
                dre_management_line_id: form.management_line_id,
                cost_center_id: form.cost_center_id,
                effective_from: form.effective_from,
            },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onError: (errs) => setErrors(errs),
                onSuccess: () => onClose(),
            }
        );
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Mapear em lote"
            subtitle={`${selectedAccountIds.length} conta(s) selecionada(s)`}
            headerColor="bg-indigo-600"
            maxWidth="md"
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={submit}
                    submitLabel="Aplicar mapeamento"
                    processing={processing}
                />
            }
        >
            <StandardModal.Section title="Destino">
                <div className="space-y-4">
                    <div>
                        <InputLabel htmlFor="bulk_line" value="Linha Gerencial *" />
                        <ManagementLinePicker
                            id="bulk_line"
                            lines={managementLines}
                            value={form.management_line_id}
                            onChange={(v) => setForm({ ...form, management_line_id: v })}
                            disabled={processing}
                        />
                        <InputError
                            className="mt-1"
                            message={errors.dre_management_line_id}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="bulk_cc" value="Centro de Custo (opcional)" />
                        <select
                            id="bulk_cc"
                            value={form.cost_center_id ?? ''}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    cost_center_id:
                                        e.target.value === '' ? null : Number(e.target.value),
                                })
                            }
                            disabled={processing}
                            className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                        >
                            <option value="">
                                (Sem CC — vale como coringa para todas)
                            </option>
                            {costCenters.map((cc) => (
                                <option key={cc.id} value={cc.id}>
                                    {cc.label || `${cc.code} — ${cc.name}`}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-1" message={errors.cost_center_id} />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="bulk_effective_from"
                            value="Vigente a partir de *"
                        />
                        <TextInput
                            id="bulk_effective_from"
                            type="date"
                            value={form.effective_from}
                            onChange={(e) =>
                                setForm({ ...form, effective_from: e.target.value })
                            }
                            disabled={processing}
                            className="w-full"
                        />
                        <InputError className="mt-1" message={errors.effective_from} />
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Resumo">
                <p className="text-sm text-gray-600">
                    Serão criados <strong>{selectedAccountIds.length}</strong> mapeamentos
                    vigentes a partir de <strong>{form.effective_from}</strong>. Contas
                    sintéticas presentes na seleção serão automaticamente ignoradas.
                </p>
            </StandardModal.Section>
        </StandardModal>
    );
}
