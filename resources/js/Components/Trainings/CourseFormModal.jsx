import { useState, useEffect } from 'react';
import { BookOpenIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';

export default function CourseFormModal({ show, onClose, onSuccess, courseId = null, facilitators = [], subjects = [] }) {
    const isEditing = !!courseId;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [data, setData] = useState({
        title: '', description: '', subject_id: '', facilitator_id: '',
        visibility: 'private', requires_sequential: false,
        certificate_on_completion: false, certificate_template_id: '',
        estimated_duration_minutes: '',
    });

    useEffect(() => {
        if (show && isEditing) {
            fetch(route('training-courses.show', courseId))
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
                });
        } else if (show && !isEditing) {
            setData({
                title: '', description: '', subject_id: '', facilitator_id: '',
                visibility: 'private', requires_sequential: false,
                certificate_on_completion: false, certificate_template_id: '',
                estimated_duration_minutes: '',
            });
            setErrors({});
        }
    }, [show, courseId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const url = isEditing ? route('training-courses.update', courseId) : route('training-courses.store');
        const method = isEditing ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method,
                body: JSON.stringify(data),
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const result = await response.json();
            if (response.ok) {
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
                            <InputLabel htmlFor="facilitator_id" value="Facilitador" />
                            <select id="facilitator_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.facilitator_id} onChange={e => setField('facilitator_id', e.target.value)}>
                                <option value="">Nenhum</option>
                                {facilitators.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="subject_id" value="Assunto" />
                            <select id="subject_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.subject_id} onChange={e => setField('subject_id', e.target.value)}>
                                <option value="">Nenhum</option>
                                {subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>
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
            </StandardModal.Section>
        </StandardModal>
    );
}
