import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { AcademicCapIcon, PlusIcon, CheckIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import Button from '@/Components/Button';

function InlineCreateForm({ label, fields, route: createRoute, onCreated, onCancel }) {
    const [formData, setFormData] = useState(
        fields.reduce((acc, f) => ({ ...acc, [f.name]: f.default ?? '' }), {})
    );
    const [saving, setSaving] = useState(false);

    const handleSave = () => {
        setSaving(true);
        fetch(createRoute, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(formData),
        })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => {
                setSaving(false);
                onCreated(data);
            })
            .catch(() => setSaving(false));
    };

    return (
        <div className="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-200">
            <p className="text-xs font-medium text-gray-600 mb-2">{label}</p>
            <div className="space-y-2">
                {fields.map(f => (
                    <div key={f.name}>
                        {f.type === 'checkbox' ? (
                            <label className="flex items-center gap-2">
                                <Checkbox checked={!!formData[f.name]} onChange={e => setFormData(prev => ({ ...prev, [f.name]: e.target.checked }))} />
                                <span className="text-xs text-gray-700">{f.label}</span>
                            </label>
                        ) : (
                            <TextInput
                                className="block w-full text-sm"
                                placeholder={f.label}
                                value={formData[f.name]}
                                onChange={e => setFormData(prev => ({ ...prev, [f.name]: e.target.value }))}
                            />
                        )}
                    </div>
                ))}
            </div>
            <div className="flex items-center gap-2 mt-2">
                <Button variant="primary" size="xs" icon={CheckIcon} onClick={handleSave} loading={saving}>Salvar</Button>
                <Button variant="light" size="xs" onClick={onCancel}>Cancelar</Button>
            </div>
        </div>
    );
}

export default function EventFormModal({ show, onClose, onSuccess, trainingId = null, facilitators: initialFacilitators = [], subjects: initialSubjects = [] }) {
    const isEditing = !!trainingId;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [templates, setTemplates] = useState([]);
    const [facilitators, setFacilitators] = useState(initialFacilitators);
    const [subjects, setSubjects] = useState(initialSubjects);
    const [showNewFacilitator, setShowNewFacilitator] = useState(false);
    const [showNewSubject, setShowNewSubject] = useState(false);
    const [data, setData] = useState({
        title: '',
        description: '',
        event_date: '',
        start_time: '',
        end_time: '',
        location: '',
        max_participants: '',
        facilitator_id: '',
        subject_id: '',
        certificate_template_id: '',
        allow_late_attendance: false,
        attendance_grace_minutes: 15,
        evaluation_enabled: true,
    });

    useEffect(() => {
        setFacilitators(initialFacilitators);
        setSubjects(initialSubjects);
    }, [initialFacilitators, initialSubjects]);

    useEffect(() => {
        if (show && isEditing) {
            fetch(route('trainings.edit', trainingId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(res => res.json())
                .then(result => {
                    const t = result.training;
                    setData({
                        title: t.title || '',
                        description: t.description || '',
                        event_date: t.event_date ? t.event_date.split('T')[0] : '',
                        start_time: t.start_time || '',
                        end_time: t.end_time || '',
                        location: t.location || '',
                        max_participants: t.max_participants || '',
                        facilitator_id: t.facilitator_id || '',
                        subject_id: t.subject_id || '',
                        certificate_template_id: t.certificate_template_id || '',
                        allow_late_attendance: t.allow_late_attendance || false,
                        attendance_grace_minutes: t.attendance_grace_minutes || 15,
                        evaluation_enabled: t.evaluation_enabled !== false,
                    });
                    setTemplates(result.templates || []);
                    if (result.facilitators) setFacilitators(result.facilitators);
                    if (result.subjects) setSubjects(result.subjects);
                });
        } else if (show && !isEditing) {
            setData({
                title: '', description: '', event_date: '', start_time: '', end_time: '',
                location: '', max_participants: '', facilitator_id: '', subject_id: '',
                certificate_template_id: '', allow_late_attendance: false,
                attendance_grace_minutes: 15, evaluation_enabled: true,
            });
            setErrors({});
            setShowNewFacilitator(false);
            setShowNewSubject(false);
        }
    }, [show, trainingId]);

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const routeName = isEditing ? 'trainings.update' : 'trainings.store';
        const method = isEditing ? 'put' : 'post';
        const routeParams = isEditing ? trainingId : undefined;

        router[method](route(routeName, routeParams), data, {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                onSuccess?.();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    const setField = (field, value) => {
        setData(prev => ({ ...prev, [field]: value }));
    };

    const handleFacilitatorCreated = (result) => {
        const f = result.facilitator;
        setFacilitators(prev => [...prev, { id: f.id, name: f.name, external: f.external }]);
        setField('facilitator_id', f.id);
        setShowNewFacilitator(false);
    };

    const handleSubjectCreated = (result) => {
        const s = result.subject;
        setSubjects(prev => [...prev, { id: s.id, name: s.name }]);
        setField('subject_id', s.id);
        setShowNewSubject(false);
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={isEditing ? 'Editar Treinamento' : 'Novo Treinamento'}
            headerColor="bg-indigo-600"
            headerIcon={<AcademicCapIcon className="h-5 w-5" />}
            maxWidth="3xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEditing ? 'Atualizar' : 'Criar'}
                    processing={processing}
                />
            }
        >
            {/* Dados Gerais */}
            <StandardModal.Section title="Dados Gerais">
                <div className="grid grid-cols-1 gap-4">
                    <div>
                        <InputLabel htmlFor="title" value="Título *" />
                        <TextInput
                            id="title"
                            className="mt-1 block w-full"
                            value={data.title}
                            onChange={e => setField('title', e.target.value)}
                            required
                        />
                        <InputError message={errors.title} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="description" value="Descrição" />
                        <textarea
                            id="description"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            rows={3}
                            value={data.description}
                            onChange={e => setField('description', e.target.value)}
                        />
                        <InputError message={errors.description} className="mt-1" />
                    </div>
                </div>
            </StandardModal.Section>

            {/* Data & Local */}
            <StandardModal.Section title="Data & Local">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <InputLabel htmlFor="event_date" value="Data *" />
                        <TextInput id="event_date" type="date" className="mt-1 block w-full"
                            value={data.event_date} onChange={e => setField('event_date', e.target.value)} required />
                        <InputError message={errors.event_date} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="start_time" value="Início *" />
                        <TextInput id="start_time" type="time" className="mt-1 block w-full"
                            value={data.start_time} onChange={e => setField('start_time', e.target.value)} required />
                        <InputError message={errors.start_time} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="end_time" value="Fim *" />
                        <TextInput id="end_time" type="time" className="mt-1 block w-full"
                            value={data.end_time} onChange={e => setField('end_time', e.target.value)} required />
                        <InputError message={errors.end_time} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="max_participants" value="Max. Participantes" />
                        <TextInput id="max_participants" type="number" className="mt-1 block w-full"
                            value={data.max_participants} onChange={e => setField('max_participants', e.target.value)}
                            min={1} placeholder="Ilimitado" />
                        <InputError message={errors.max_participants} className="mt-1" />
                    </div>
                </div>
                <div className="mt-4">
                    <InputLabel htmlFor="location" value="Local" />
                    <TextInput id="location" className="mt-1 block w-full" value={data.location}
                        onChange={e => setField('location', e.target.value)} placeholder="Sala de reuniões, auditório..." />
                    <InputError message={errors.location} className="mt-1" />
                </div>
            </StandardModal.Section>

            {/* Facilitador & Assunto */}
            <StandardModal.Section title="Facilitador & Assunto">
                <div className="grid grid-cols-2 gap-4">
                    {/* Facilitador */}
                    <div>
                        <div className="flex items-center justify-between">
                            <InputLabel htmlFor="facilitator_id" value="Facilitador *" />
                            {!showNewFacilitator && (
                                <button type="button" onClick={() => setShowNewFacilitator(true)}
                                    className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5">
                                    <PlusIcon className="w-3.5 h-3.5" /> Novo
                                </button>
                            )}
                        </div>
                        <select
                            id="facilitator_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.facilitator_id}
                            onChange={e => setField('facilitator_id', e.target.value)}
                            required
                        >
                            <option value="">Selecione...</option>
                            {facilitators.map(f => (
                                <option key={f.id} value={f.id}>{f.name}{f.external ? ' (Externo)' : ''}</option>
                            ))}
                        </select>
                        <InputError message={errors.facilitator_id} className="mt-1" />

                        {showNewFacilitator && (
                            <InlineCreateForm
                                label="Novo Facilitador"
                                route={route('trainings.facilitators.store')}
                                fields={[
                                    { name: 'name', label: 'Nome *' },
                                    { name: 'email', label: 'Email' },
                                    { name: 'phone', label: 'Telefone' },
                                    { name: 'external', label: 'Facilitador Externo', type: 'checkbox', default: false },
                                ]}
                                onCreated={handleFacilitatorCreated}
                                onCancel={() => setShowNewFacilitator(false)}
                            />
                        )}
                    </div>

                    {/* Assunto */}
                    <div>
                        <div className="flex items-center justify-between">
                            <InputLabel htmlFor="subject_id" value="Assunto *" />
                            {!showNewSubject && (
                                <button type="button" onClick={() => setShowNewSubject(true)}
                                    className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5">
                                    <PlusIcon className="w-3.5 h-3.5" /> Novo
                                </button>
                            )}
                        </div>
                        <select
                            id="subject_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.subject_id}
                            onChange={e => setField('subject_id', e.target.value)}
                            required
                        >
                            <option value="">Selecione...</option>
                            {subjects.map(s => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.subject_id} className="mt-1" />

                        {showNewSubject && (
                            <InlineCreateForm
                                label="Novo Assunto"
                                route={route('trainings.subjects.store')}
                                fields={[
                                    { name: 'name', label: 'Nome *' },
                                    { name: 'description', label: 'Descrição' },
                                ]}
                                onCreated={handleSubjectCreated}
                                onCancel={() => setShowNewSubject(false)}
                            />
                        )}
                    </div>
                </div>
            </StandardModal.Section>

            {/* Configuracao */}
            <StandardModal.Section title="Configuração">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="certificate_template_id" value="Template de Certificado" />
                        <select
                            id="certificate_template_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.certificate_template_id}
                            onChange={e => setField('certificate_template_id', e.target.value)}
                        >
                            <option value="">Padrão</option>
                            {templates.map(t => (
                                <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (Padrão)' : ''}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="attendance_grace_minutes" value="Tolerância (minutos)" />
                        <TextInput
                            id="attendance_grace_minutes"
                            type="number"
                            className="mt-1 block w-full"
                            value={data.attendance_grace_minutes}
                            onChange={e => setField('attendance_grace_minutes', parseInt(e.target.value) || 0)}
                            min={0}
                            max={120}
                        />
                    </div>
                </div>
                <div className="flex items-center gap-6 mt-4">
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={data.allow_late_attendance}
                            onChange={e => setField('allow_late_attendance', e.target.checked)}
                        />
                        <span className="text-sm text-gray-700">Permitir presença atrasada</span>
                    </label>
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={data.evaluation_enabled}
                            onChange={e => setField('evaluation_enabled', e.target.checked)}
                        />
                        <span className="text-sm text-gray-700">Habilitar avaliação</span>
                    </label>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
