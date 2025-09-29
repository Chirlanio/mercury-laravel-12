import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function EmployeeCreateModal({ show, onClose, onSuccess, positions = [], stores = [] }) {
    // Debug: Log the received props
    console.log('EmployeeCreateModal received props:', { positions, stores, positionsLength: positions.length, storesLength: stores.length });

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
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

    const handleSubmit = (e) => {
        e.preventDefault();

        // Para upload de arquivo, usar forceFormData: true
        post('/employees', {
            forceFormData: true,
            onSuccess: () => {
                reset();
                onClose();
                if (onSuccess) onSuccess();
            },
            onError: (errors) => {
                console.error('Erro ao criar funcionário:', errors);
            }
        });
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onClose();
    };

    const formatCPF = (value) => {
        // Remove tudo que não é número
        const cleanValue = value.replace(/\D/g, '');

        // Aplica a máscara
        if (cleanValue.length <= 11) {
            return cleanValue
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})/, '$1-$2');
        }

        return value;
    };

    const handleCPFChange = (e) => {
        const formatted = formatCPF(e.target.value);
        setData('cpf', formatted);
    };

    return (
        <Modal show={show} onClose={handleClose} title="Cadastrar Funcionário" maxWidth="2xl">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Informações Pessoais */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Informações Pessoais
                    </h4>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                Nome Completo *
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="short_name" className="block text-sm font-medium text-gray-700 mb-1">
                                Nome Abreviado
                            </label>
                            <input
                                type="text"
                                id="short_name"
                                value={data.short_name}
                                onChange={(e) => setData('short_name', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.short_name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            />
                            {errors.short_name && <p className="mt-1 text-sm text-red-600">{errors.short_name}</p>}
                        </div>

                        <div>
                            <label htmlFor="cpf" className="block text-sm font-medium text-gray-700 mb-1">
                                CPF *
                            </label>
                            <input
                                type="text"
                                id="cpf"
                                value={data.cpf}
                                onChange={handleCPFChange}
                                placeholder="000.000.000-00"
                                maxLength="14"
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.cpf ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.cpf && <p className="mt-1 text-sm text-red-600">{errors.cpf}</p>}
                        </div>

                        <div>
                            <label htmlFor="birth_date" className="block text-sm font-medium text-gray-700 mb-1">
                                Data de Nascimento
                            </label>
                            <input
                                type="date"
                                id="birth_date"
                                value={data.birth_date}
                                onChange={(e) => setData('birth_date', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.birth_date ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            />
                            {errors.birth_date && <p className="mt-1 text-sm text-red-600">{errors.birth_date}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4">
                        <div>
                            <label htmlFor="profile_image" className="block text-sm font-medium text-gray-700 mb-1">
                                Foto do Perfil
                            </label>
                            <div className="flex items-center space-x-4">
                                <input
                                    type="file"
                                    id="profile_image"
                                    accept="image/jpeg,image/png,image/jpg,image/gif"
                                    onChange={(e) => setData('profile_image', e.target.files[0])}
                                    className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                        errors.profile_image ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                    }`}
                                />
                                {data.profile_image && (
                                    <div className="flex-shrink-0">
                                        <img
                                            src={URL.createObjectURL(data.profile_image)}
                                            alt="Preview"
                                            className="h-12 w-12 rounded-full object-cover border-2 border-gray-200"
                                        />
                                    </div>
                                )}
                            </div>
                            {errors.profile_image && <p className="mt-1 text-sm text-red-600">{errors.profile_image}</p>}
                            <p className="mt-1 text-xs text-gray-500">
                                Formatos aceitos: JPEG, PNG, JPG, GIF. Tamanho máximo: 2MB
                            </p>
                        </div>
                    </div>
                </div>

                {/* Informações Profissionais */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Informações Profissionais
                    </h4>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="admission_date" className="block text-sm font-medium text-gray-700 mb-1">
                                Data de Admissão *
                            </label>
                            <input
                                type="date"
                                id="admission_date"
                                value={data.admission_date}
                                onChange={(e) => setData('admission_date', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.admission_date ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.admission_date && <p className="mt-1 text-sm text-red-600">{errors.admission_date}</p>}
                        </div>

                        <div>
                            <label htmlFor="position_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Cargo *
                            </label>
                            <select
                                id="position_id"
                                value={data.position_id}
                                onChange={(e) => setData('position_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.position_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            >
                                <option value="">Selecione um cargo</option>
                                {positions.map((position) => (
                                    <option key={position.id} value={position.id}>
                                        {position.name} {position.level && `(${position.level})`}
                                    </option>
                                ))}
                            </select>
                            {errors.position_id && <p className="mt-1 text-sm text-red-600">{errors.position_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="level" className="block text-sm font-medium text-gray-700 mb-1">
                                Nível
                            </label>
                            <select
                                id="level"
                                value={data.level}
                                onChange={(e) => setData('level', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.level ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione um nível</option>
                                {levels.map((level) => (
                                    <option key={level.value} value={level.value}>
                                        {level.label}
                                    </option>
                                ))}
                            </select>
                            {errors.level && <p className="mt-1 text-sm text-red-600">{errors.level}</p>}
                        </div>

                        <div>
                            <label htmlFor="store_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Loja *
                            </label>
                            <select
                                id="store_id"
                                value={data.store_id}
                                onChange={(e) => setData('store_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.store_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione uma loja</option>
                                {stores.map((store) => (
                                    <option key={store.id} value={store.code}>
                                        {store.code} - {store.name}
                                    </option>
                                ))}
                            </select>
                            {errors.store_id && <p className="mt-1 text-sm text-red-600">{errors.store_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="site_coupon" className="block text-sm font-medium text-gray-700 mb-1">
                                Cupom Site
                            </label>
                            <input
                                type="text"
                                id="site_coupon"
                                value={data.site_coupon}
                                onChange={(e) => setData('site_coupon', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.site_coupon ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            />
                            {errors.site_coupon && <p className="mt-1 text-sm text-red-600">{errors.site_coupon}</p>}
                        </div>
                    </div>
                </div>

                {/* Informações Complementares */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Informações Complementares
                    </h4>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="dismissal_date" className="block text-sm font-medium text-gray-700 mb-1">
                                Data de Demissão
                            </label>
                            <input
                                type="date"
                                id="dismissal_date"
                                value={data.dismissal_date}
                                onChange={(e) => setData('dismissal_date', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.dismissal_date ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            />
                            {errors.dismissal_date && <p className="mt-1 text-sm text-red-600">{errors.dismissal_date}</p>}
                            <p className="mt-1 text-xs text-gray-500">
                                Deixe em branco se o funcionário estiver ativo
                            </p>
                        </div>

                        <div>
                            <label htmlFor="education_level_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Escolaridade
                            </label>
                            <select
                                id="education_level_id"
                                value={data.education_level_id}
                                onChange={(e) => setData('education_level_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.education_level_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione a escolaridade</option>
                                <option value="1">Ensino Fundamental</option>
                                <option value="2">Ensino Médio</option>
                                <option value="3">Ensino Superior</option>
                                <option value="4">Pós-graduação</option>
                                <option value="5">Mestrado</option>
                                <option value="6">Doutorado</option>
                            </select>
                            {errors.education_level_id && <p className="mt-1 text-sm text-red-600">{errors.education_level_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="gender_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Gênero
                            </label>
                            <select
                                id="gender_id"
                                value={data.gender_id}
                                onChange={(e) => setData('gender_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.gender_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione o gênero</option>
                                <option value="1">Masculino</option>
                                <option value="2">Feminino</option>
                                <option value="3">Outro</option>
                                <option value="4">Prefere não informar</option>
                            </select>
                            {errors.gender_id && <p className="mt-1 text-sm text-red-600">{errors.gender_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="area_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Área
                            </label>
                            <select
                                id="area_id"
                                value={data.area_id}
                                onChange={(e) => setData('area_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.area_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione a área</option>
                                <option value="1">Vendas</option>
                                <option value="2">Administrativo</option>
                                <option value="3">Logística</option>
                                <option value="4">Marketing</option>
                                <option value="5">TI</option>
                                <option value="6">Financeiro</option>
                                <option value="7">RH</option>
                            </select>
                            {errors.area_id && <p className="mt-1 text-sm text-red-600">{errors.area_id}</p>}
                        </div>
                    </div>
                </div>

                {/* Características Especiais */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Características Especiais
                    </h4>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                id="is_pcd"
                                checked={data.is_pcd}
                                onChange={(e) => setData('is_pcd', e.target.checked)}
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_pcd" className="ml-2 block text-sm text-gray-900">
                                Pessoa com Deficiência (PcD)
                            </label>
                        </div>

                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                id="is_apprentice"
                                checked={data.is_apprentice}
                                onChange={(e) => setData('is_apprentice', e.target.checked)}
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_apprentice" className="ml-2 block text-sm text-gray-900">
                                Aprendiz
                            </label>
                        </div>
                    </div>
                </div>

                {/* Ações */}
                <div className="flex justify-end space-x-3 pt-4 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>

                    <Button
                        type="submit"
                        variant="primary"
                        loading={processing}
                        icon={processing ? null : ({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        )}
                    >
                        {processing ? 'Salvando...' : 'Cadastrar Funcionário'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
