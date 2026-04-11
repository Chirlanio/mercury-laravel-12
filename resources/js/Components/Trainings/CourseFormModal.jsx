import { useState, useEffect } from 'react';
import { BookOpenIcon, PlusIcon, TrashIcon, CheckIcon } from '@heroicons/react/24/outline';
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
            },
            body: JSON.stringify(formData),
        })
            .then(res => res.ok ? res.json() : Promise.reject(res))
            .then(data => { setSaving(false); onCreated(data); })
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
                            <TextInput className="block w-full text-sm" placeholder={f.label}
                                value={formData[f.name]} onChange={e => setFormData(prev => ({ ...prev, [f.name]: e.target.value }))} />
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

const RULE_TYPES = [
    { value: 'store', label: 'Loja' },
    { value: 'role', label: 'Perfil' },
    { value: 'user', label: 'Usuário (ID)' },
];

const ROLE_OPTIONS = [
    { value: 'super_admin', label: 'Super Admin' },
    { value: 'admin', label: 'Administrador' },
    { value: 'support', label: 'Suporte' },
    { value: 'user', label: 'Usuário' },
];

export default function CourseFormModal({ show, onClose, onSuccess, courseId = null, facilitators: initialFacilitators = [], subjects: initialSubjects = [], stores = [], templates: initialTemplates = [] }) {
    const isEditing = !!courseId;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [facilitators, setFacilitators] = useState(initialFacilitators);
    const [subjects, setSubjects] = useState(initialSubjects);
    const [showNewFacilitator, setShowNewFacilitator] = useState(false);
    const [showNewSubject, setShowNewSubject] = useState(false);
    const [templates, setTemplates] = useState(initialTemplates);
    const [data, setData] = useState({
        title: '', description: '', subject_id: '', facilitator_id: '',
        visibility: 'private', requires_sequential: false,
        certificate_on_completion: false, certificate_template_id: '',
        estimated_duration_minutes: '',
    });
    const [visibilityRules, setVisibilityRules] = useState([]);
    const [newRuleType, setNewRuleType] = useState('store');
    const [newRuleValue, setNewRuleValue] = useState('');

    useEffect(() => {
        setFacilitators(initialFacilitators);
        setSubjects(initialSubjects);
        setTemplates(initialTemplates);
    }, [initialFacilitators, initialSubjects, initialTemplates]);

    useEffect(() => {
        if (show && isEditing) {
            fetch(route('training-courses.show', courseId), {
                headers: { 'Accept': 'application/json' },
            })
                .then(res => res.json())
                .then(result => {
                    const c = result.course;
                    setData({
                        title: c.title || '', description: c.description || '',
                        subject_id: c.subject?.id || '', facilitator_id: c.facilitator?.id || '',
                        visibility: c.visibility || 'private',
                        requires_sequential: c.requires_sequential || false,
                        certificate_on_completion: c.certificate_on_completion || false,
                        certificate_template_id: c.certificate_template?.id || '',
                        estimated_duration_minutes: c.estimated_duration_minutes || '',
                    });
                    setVisibilityRules(c.visibility_rules || []);
                    if (result.templates) setTemplates(result.templates);
                });
        } else if (show && !isEditing) {
            setData({
                title: '', description: '', subject_id: '', facilitator_id: '',
                visibility: 'private', requires_sequential: false,
                certificate_on_completion: false, certificate_template_id: '',
                estimated_duration_minutes: '',
            });
            setVisibilityRules([]);
            setErrors({});
            setShowNewFacilitator(false);
            setShowNewSubject(false);

            // Carregar dados de suporte se não vieram via props
            if (templates.length === 0 || facilitators.length === 0) {
                fetch(route('training-courses.index') + '?per_page=1', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(res => res.json())
                    .then(result => {
                        if (result.templates?.length) setTemplates(result.templates);
                        if (result.facilitators?.length && facilitators.length === 0) setFacilitators(result.facilitators);
                        if (result.subjects?.length && subjects.length === 0) setSubjects(result.subjects);
                    })
                    .catch(() => {});
            }
        }
    }, [show, courseId]);

    const [thumbnail, setThumbnail] = useState(null);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const url = isEditing ? route('training-courses.update', courseId) : route('training-courses.store');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        let body, headers;
        if (thumbnail) {
            // FormData quando há upload
            const formData = new FormData();
            Object.entries(data).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    formData.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v);
                }
            });
            formData.append('thumbnail', thumbnail);
            if (isEditing) formData.append('_method', 'PUT');
            body = formData;
            headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };
        } else {
            body = JSON.stringify(data);
            headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf };
        }

        try {
            const response = await fetch(url, {
                method: thumbnail && isEditing ? 'POST' : (isEditing ? 'PUT' : 'POST'),
                body,
                headers,
            });
            const result = await response.json();
            if (response.ok) {
                // Save visibility rules if private
                const savedCourseId = courseId || result.course?.id;
                if (data.visibility === 'private' && savedCourseId) {
                    await fetch(route('training-courses.visibility', savedCourseId), {
                        method: 'POST',
                        body: JSON.stringify({ rules: visibilityRules }),
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                        },
                    });
                }
                setProcessing(false);
                onSuccess?.();
            } else if (response.status === 422 && result.errors) {
                setProcessing(false);
                setErrors(result.errors);
            } else {
                setProcessing(false);
                setErrors({ _general: [result.error || result.message || 'Erro ao salvar.'] });
            }
        } catch {
            setProcessing(false);
            setErrors({ _general: ['Erro de conexão.'] });
        }
    };

    const setField = (field, value) => setData(prev => ({ ...prev, [field]: value }));

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
            title={isEditing ? 'Editar Curso' : 'Novo Curso'}
            headerColor="bg-green-600"
            headerIcon={<BookOpenIcon className="h-5 w-5" />}
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
            <StandardModal.Section title="Dados Gerais">
                <div className="space-y-4">
                    <div>
                        <InputLabel htmlFor="title" value="Título *" />
                        <TextInput id="title" className="mt-1 block w-full" value={data.title}
                            onChange={e => setField('title', e.target.value)} required />
                        <InputError message={errors.title} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="description" value="Descrição" />
                        <textarea id="description" rows={3}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.description} onChange={e => setField('description', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <div className="flex items-center justify-between">
                                <InputLabel htmlFor="facilitator_id" value="Facilitador" />
                                {!showNewFacilitator && (
                                    <button type="button" onClick={() => setShowNewFacilitator(true)}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5">
                                        <PlusIcon className="w-3.5 h-3.5" /> Novo
                                    </button>
                                )}
                            </div>
                            <select id="facilitator_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.facilitator_id} onChange={e => setField('facilitator_id', e.target.value)}>
                                <option value="">Nenhum</option>
                                {facilitators.map(f => <option key={f.id} value={f.id}>{f.name}{f.external ? ' (Externo)' : ''}</option>)}
                            </select>
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
                        <div>
                            <div className="flex items-center justify-between">
                                <InputLabel htmlFor="subject_id" value="Assunto" />
                                {!showNewSubject && (
                                    <button type="button" onClick={() => setShowNewSubject(true)}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-0.5">
                                        <PlusIcon className="w-3.5 h-3.5" /> Novo
                                    </button>
                                )}
                            </div>
                            <select id="subject_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.subject_id} onChange={e => setField('subject_id', e.target.value)}>
                                <option value="">Nenhum</option>
                                {subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
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
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Configuração">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="visibility" value="Visibilidade *" />
                        <select id="visibility"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.visibility} onChange={e => setField('visibility', e.target.value)}>
                            <option value="private">Privado</option>
                            <option value="public">Público</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel htmlFor="estimated_duration_minutes" value="Duração estimada (min)" />
                        <TextInput id="estimated_duration_minutes" type="number" className="mt-1 block w-full"
                            value={data.estimated_duration_minutes}
                            onChange={e => setField('estimated_duration_minutes', e.target.value)}
                            min={1} placeholder="Opcional" />
                    </div>
                </div>
                <div className="mt-4">
                    <InputLabel value="Thumbnail (opcional)" />
                    <input type="file" accept="image/*"
                        className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        onChange={e => setThumbnail(e.target.files[0] || null)} />
                    <InputError message={errors.thumbnail} className="mt-1" />
                </div>
                <div className="flex items-center gap-6 mt-4">
                    <label className="flex items-center gap-2">
                        <Checkbox checked={data.requires_sequential}
                            onChange={e => setField('requires_sequential', e.target.checked)} />
                        <span className="text-sm text-gray-700">Conteúdos em ordem sequencial</span>
                    </label>
                    <label className="flex items-center gap-2">
                        <Checkbox checked={data.certificate_on_completion}
                            onChange={e => setField('certificate_on_completion', e.target.checked)} />
                        <span className="text-sm text-gray-700">Gerar certificado ao concluir</span>
                    </label>
                </div>
                {data.certificate_on_completion && templates.length > 0 && (
                    <div className="mt-4">
                        <InputLabel htmlFor="certificate_template_id" value="Modelo do Certificado" />
                        <select id="certificate_template_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.certificate_template_id} onChange={e => setField('certificate_template_id', e.target.value)}>
                            <option value="">Padrão do sistema</option>
                            {templates.map(t => (
                                <option key={t.id} value={t.id}>{t.name}{t.is_default ? ' (Padrão)' : ''}</option>
                            ))}
                        </select>
                    </div>
                )}
            </StandardModal.Section>

            {data.visibility === 'private' && (
                <StandardModal.Section title="Regras de Visibilidade">
                    <p className="text-xs text-gray-500 mb-3">
                        Defina quem pode acessar este curso. Sem regras, apenas administradores terão acesso.
                    </p>

                    {/* Regras existentes */}
                    {visibilityRules.length > 0 && (
                        <div className="space-y-2 mb-3">
                            {visibilityRules.map((rule, i) => (
                                <div key={i} className="flex items-center justify-between bg-gray-50 rounded-md px-3 py-2">
                                    <div className="text-sm">
                                        <span className="font-medium text-gray-700">
                                            {RULE_TYPES.find(t => t.value === rule.target_type)?.label || rule.target_type}:
                                        </span>{' '}
                                        <span className="text-gray-600">
                                            {rule.target_type === 'role'
                                                ? (ROLE_OPTIONS.find(r => r.value === rule.target_id)?.label || rule.target_id)
                                                : rule.target_type === 'store'
                                                    ? (stores.find(s => s.code === rule.target_id)?.name || rule.target_id)
                                                    : rule.target_id}
                                        </span>
                                    </div>
                                    <button type="button" onClick={() => setVisibilityRules(prev => prev.filter((_, idx) => idx !== i))}
                                        className="text-red-400 hover:text-red-600">
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Adicionar nova regra */}
                    <div className="flex items-end gap-2">
                        <div className="flex-1">
                            <InputLabel value="Tipo" />
                            <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={newRuleType} onChange={e => { setNewRuleType(e.target.value); setNewRuleValue(''); }}>
                                {RULE_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        <div className="flex-1">
                            <InputLabel value="Valor" />
                            {newRuleType === 'store' ? (
                                <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={newRuleValue} onChange={e => setNewRuleValue(e.target.value)}>
                                    <option value="">Selecione...</option>
                                    {stores.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
                                </select>
                            ) : newRuleType === 'role' ? (
                                <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={newRuleValue} onChange={e => setNewRuleValue(e.target.value)}>
                                    <option value="">Selecione...</option>
                                    {ROLE_OPTIONS.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                                </select>
                            ) : (
                                <TextInput className="mt-1 block w-full" value={newRuleValue}
                                    onChange={e => setNewRuleValue(e.target.value)} placeholder="ID do usuário" />
                            )}
                        </div>
                        <Button variant="outline" size="sm" icon={PlusIcon}
                            onClick={() => {
                                if (!newRuleValue) return;
                                const exists = visibilityRules.some(r => r.target_type === newRuleType && r.target_id === newRuleValue);
                                if (exists) return;
                                setVisibilityRules(prev => [...prev, { target_type: newRuleType, target_id: newRuleValue }]);
                                setNewRuleValue('');
                            }}>
                            Adicionar
                        </Button>
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
