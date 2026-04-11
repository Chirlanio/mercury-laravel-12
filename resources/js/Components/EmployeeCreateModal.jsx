import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import { maskCpf } from '@/Hooks/useMasks';
import { UserPlusIcon } from '@heroicons/react/24/outline';

export default function EmployeeCreateModal({ show, onClose, onSuccess, positions = [], stores = [], educationLevels = [] }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { data, setData, errors, reset, clearErrors, setError } = useForm({
        name: '',
        short_name: '',
        cpf: '',
        admission_date: '',
        birth_date: '',
        dismissal_date: '',
        position_id: '',
        level: '',
        store_id: '',
        site_coupon: '',
        education_level_id: '',
        gender_id: '',
        area_id: '',
        is_pcd: false,
        is_apprentice: false,
        profile_image: null,
    });

    const levels = [
        { value: 'Junior', label: 'Júnior' },
        { value: 'Pleno', label: 'Pleno' },
        { value: 'Senior', label: 'Sênior' },
    ];

    const handleSubmit = () => {
        const cleanedData = {
            ...data,
            cpf: data.cpf ? data.cpf.replace(/\D/g, '') : '',
        };

        if (!cleanedData.profile_image) {
            delete cleanedData.profile_image;
        }

        setIsSubmitting(true);

        router.post('/employees', cleanedData, {
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                setIsSubmitting(false);
                reset();
                clearErrors();
                if (onSuccess) onSuccess();
            },
            onError: (errors) => {
                setIsSubmitting(false);
                setError(errors);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onClose();
    };

    const handleImageChange = (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 5 * 1024 * 1024) {
            setError('profile_image', 'O arquivo deve ter no máximo 5MB.');
            e.target.value = '';
            return;
        }

        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(img.src);
            if (img.width < 50 || img.height < 50) {
                setError('profile_image', `Imagem muito pequena (${img.width}x${img.height}). Mínimo: 50x50 pixels.`);
                e.target.value = '';
            } else if (img.width > 2000 || img.height > 2000) {
                setError('profile_image', `Imagem muito grande (${img.width}x${img.height}). Máximo: 2000x2000 pixels.`);
                e.target.value = '';
            } else {
                clearErrors('profile_image');
                setData('profile_image', file);
            }
        };
        img.onerror = () => {
            URL.revokeObjectURL(img.src);
            setError('profile_image', 'Arquivo não é uma imagem válida.');
            e.target.value = '';
        };
        img.src = URL.createObjectURL(file);
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Cadastrar Funcionário"
            subtitle="Criação de novo registro de colaborador"
            headerColor="bg-indigo-600"
            headerIcon={<UserPlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Cadastrar Funcionário"
                    processing={isSubmitting}
                />
            }
        >
            <StandardModal.Section title="Informações Pessoais">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="create-name" value="Nome Completo *" />
                        <TextInput
                            id="create-name"
                            className="mt-1 w-full"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-short_name" value="Nome Abreviado" />
                        <TextInput
                            id="create-short_name"
                            className="mt-1 w-full"
                            value={data.short_name}
                            onChange={(e) => setData('short_name', e.target.value)}
                        />
                        <InputError message={errors.short_name} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-cpf" value="CPF *" />
                        <TextInput
                            id="create-cpf"
                            className="mt-1 w-full"
                            value={data.cpf}
                            onChange={(e) => setData('cpf', maskCpf(e.target.value))}
                            placeholder="000.000.000-00"
                            maxLength="14"
                            required
                        />
                        <InputError message={errors.cpf} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-birth_date" value="Data de Nascimento" />
                        <TextInput
                            id="create-birth_date"
                            type="date"
                            className="mt-1 w-full"
                            value={data.birth_date}
                            onChange={(e) => setData('birth_date', e.target.value)}
                        />
                        <InputError message={errors.birth_date} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-gender_id" value="Gênero" />
                        <select
                            id="create-gender_id"
                            value={data.gender_id}
                            onChange={(e) => setData('gender_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione o gênero</option>
                            <option value="1">Masculino</option>
                            <option value="2">Feminino</option>
                            <option value="3">Outro</option>
                            <option value="4">Prefere não informar</option>
                        </select>
                        <InputError message={errors.gender_id} className="mt-1" />
                    </div>

                    <div className="md:col-span-full">
                        <InputLabel htmlFor="create-profile_image" value="Foto do Perfil" />
                        <div className="flex items-center space-x-4 mt-1">
                            <input
                                type="file"
                                id="create-profile_image"
                                accept="image/jpeg,image/png,image/jpg,image/gif"
                                onChange={handleImageChange}
                                className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            {data.profile_image && (
                                <div className="flex-shrink-0">
                                    <img
                                        src={URL.createObjectURL(data.profile_image)}
                                        alt="Preview"
                                        className="h-10 w-10 rounded-full object-cover ring-2 ring-indigo-100"
                                    />
                                </div>
                            )}
                        </div>
                        <InputError message={errors.profile_image} className="mt-1" />
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Informações Profissionais">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <InputLabel htmlFor="create-admission_date" value="Data de Admissão *" />
                        <TextInput
                            id="create-admission_date"
                            type="date"
                            className="mt-1 w-full"
                            value={data.admission_date}
                            onChange={(e) => setData('admission_date', e.target.value)}
                            required
                        />
                        <InputError message={errors.admission_date} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-position_id" value="Cargo *" />
                        <select
                            id="create-position_id"
                            value={data.position_id}
                            onChange={(e) => setData('position_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            required
                        >
                            <option value="">Selecione um cargo</option>
                            {positions.map((position) => (
                                <option key={position.id} value={position.id}>
                                    {position.name} {position.level && `(${position.level})`}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.position_id} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-level" value="Nível" />
                        <select
                            id="create-level"
                            value={data.level}
                            onChange={(e) => setData('level', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione um nível</option>
                            {levels.map((level) => (
                                <option key={level.value} value={level.value}>{level.label}</option>
                            ))}
                        </select>
                        <InputError message={errors.level} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-store_id" value="Loja *" />
                        <select
                            id="create-store_id"
                            value={data.store_id}
                            onChange={(e) => setData('store_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione uma loja</option>
                            {stores.map((store) => (
                                <option key={store.id} value={store.code}>
                                    {store.code} - {store.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.store_id} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-site_coupon" value="Cupom Site" />
                        <TextInput
                            id="create-site_coupon"
                            className="mt-1 w-full"
                            value={data.site_coupon}
                            onChange={(e) => setData('site_coupon', e.target.value)}
                        />
                        <InputError message={errors.site_coupon} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-area_id" value="Área" />
                        <select
                            id="create-area_id"
                            value={data.area_id}
                            onChange={(e) => setData('area_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione a área</option>
                            <option value="1">Vendas</option>
                            <option value="2">Administrativo</option>
                            <option value="3">Logística</option>
                            <option value="4">Marketing</option>
                            <option value="5">TI</option>
                            <option value="6">Financeiro</option>
                            <option value="7">RH</option>
                            <option value="8">Operacional</option>
                            <option value="9">Comercial</option>
                            <option value="10">Segurança</option>
                            <option value="11">Limpeza</option>
                            <option value="12">Geral</option>
                            <option value="13">Produção</option>
                            <option value="14">Qualidade</option>
                            <option value="15">Compras</option>
                        </select>
                        <InputError message={errors.area_id} className="mt-1" />
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Outras Informações">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="create-education_level_id" value="Escolaridade" />
                        <select
                            id="create-education_level_id"
                            value={data.education_level_id}
                            onChange={(e) => setData('education_level_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecione a escolaridade</option>
                            {educationLevels.map((level) => (
                                <option key={level.id} value={level.id}>{level.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.education_level_id} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-dismissal_date" value="Data de Demissão" />
                        <TextInput
                            id="create-dismissal_date"
                            type="date"
                            className="mt-1 w-full"
                            value={data.dismissal_date}
                            onChange={(e) => setData('dismissal_date', e.target.value)}
                        />
                        <InputError message={errors.dismissal_date} className="mt-1" />
                    </div>
                </div>

                <div className="mt-4 flex gap-6">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="create-is_pcd"
                            checked={data.is_pcd}
                            onChange={(e) => setData('is_pcd', e.target.checked)}
                        />
                        <InputLabel htmlFor="create-is_pcd" value="PcD" />
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="create-is_apprentice"
                            checked={data.is_apprentice}
                            onChange={(e) => setData('is_apprentice', e.target.checked)}
                        />
                        <InputLabel htmlFor="create-is_apprentice" value="Aprendiz" />
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
