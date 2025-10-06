import { useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

/**
 * Modal Genérico para Formulários (Criar e Editar)
 *
 * @param {boolean} show - Controla se o modal está visível
 * @param {function} onClose - Função chamada ao fechar o modal
 * @param {function} onSuccess - Função chamada após sucesso
 * @param {string} title - Título do modal
 * @param {string} mode - 'create' ou 'edit'
 * @param {object|null} initialData - Dados iniciais para modo de edição
 * @param {array} sections - Array de seções do formulário
 * @param {string} submitUrl - URL para submissão do formulário
 * @param {string} submitMethod - Método HTTP ('post', 'put', 'patch')
 * @param {string} submitButtonText - Texto do botão de submissão
 * @param {string} submitButtonIcon - Ícone do botão de submissão
 * @param {string} maxWidth - Largura máxima do modal
 * @param {function} transformData - Função para transformar dados antes de enviar
 * @param {boolean} preserveState - Se deve preservar o estado após submissão
 * @param {boolean} preserveScroll - Se deve preservar o scroll após submissão
 */
export default function GenericFormModal({
    show,
    onClose,
    onSuccess,
    title,
    mode = 'create', // 'create' ou 'edit'
    initialData = null,
    sections = [],
    submitUrl,
    submitMethod = 'post',
    submitButtonText = 'Salvar',
    submitButtonIcon = null,
    maxWidth = '85vw',
    transformData = null,
    preserveState = false,
    preserveScroll = false,
}) {
    // Extrair campos de todas as seções para criar o objeto de dados inicial
    const getInitialFormData = () => {
        const formData = {};
        sections.forEach(section => {
            section.fields.forEach(field => {
                formData[field.name] = field.defaultValue || (field.type === 'checkbox' ? false : '');
            });
        });
        return formData;
    };

    const { data, setData, errors, reset, clearErrors, setError, processing } = useForm(getInitialFormData());

    // Preencher formulário em modo de edição
    useEffect(() => {
        if (mode === 'edit' && initialData && show) {
            const updatedData = {};
            sections.forEach(section => {
                section.fields.forEach(field => {
                    if (field.formatValue && initialData[field.name]) {
                        updatedData[field.name] = field.formatValue(initialData[field.name]);
                    } else {
                        updatedData[field.name] = initialData[field.name] || field.defaultValue || (field.type === 'checkbox' ? false : '');
                    }
                });
            });
            setData(updatedData);
        }
    }, [mode, initialData, show]);

    const handleSubmit = (e) => {
        e.preventDefault();

        // Transformar dados se necessário
        const submitData = transformData ? transformData(data) : data;

        // Se for edição, adicionar _method se necessário
        if (mode === 'edit' && submitMethod.toLowerCase() === 'put') {
            submitData._method = 'PUT';
        }

        router[submitMethod.toLowerCase()](submitUrl, submitData, {
            preserveState,
            preserveScroll,
            onSuccess: () => {
                reset();
                clearErrors();
                if (onSuccess) {
                    onSuccess();
                }
            },
            onError: (errors) => {
                setError(errors);
            },
        });
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onClose();
    };

    const handleFieldChange = (fieldName, value, field) => {
        if (field.onChange) {
            value = field.onChange(value, data);
        }
        setData(fieldName, value);

        // Limpar erro do campo quando ele for modificado
        if (errors[fieldName]) {
            clearErrors(fieldName);
        }
    };

    const renderField = (field) => {
        const inputClasses = `w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
            errors[field.name] ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
        }`;

        switch (field.type) {
            case 'text':
            case 'email':
            case 'number':
            case 'date':
            case 'time':
                return (
                    <input
                        type={field.type}
                        id={field.name}
                        value={data[field.name] || ''}
                        onChange={(e) => handleFieldChange(field.name, e.target.value, field)}
                        className={inputClasses}
                        required={field.required}
                        placeholder={field.placeholder}
                        disabled={field.disabled || processing}
                        {...(field.props || {})}
                    />
                );

            case 'textarea':
                return (
                    <textarea
                        id={field.name}
                        value={data[field.name] || ''}
                        onChange={(e) => handleFieldChange(field.name, e.target.value, field)}
                        className={inputClasses}
                        required={field.required}
                        placeholder={field.placeholder}
                        disabled={field.disabled || processing}
                        rows={field.rows || 4}
                        {...(field.props || {})}
                    />
                );

            case 'select':
                return (
                    <select
                        id={field.name}
                        value={data[field.name] || ''}
                        onChange={(e) => handleFieldChange(field.name, e.target.value, field)}
                        className={inputClasses}
                        required={field.required}
                        disabled={field.disabled || processing}
                        {...(field.props || {})}
                    >
                        {field.placeholder && <option value="">{field.placeholder}</option>}
                        {field.options && field.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                );

            case 'checkbox':
                return (
                    <div className="flex items-center">
                        <input
                            type="checkbox"
                            id={field.name}
                            checked={data[field.name] || false}
                            onChange={(e) => handleFieldChange(field.name, e.target.checked, field)}
                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            disabled={field.disabled || processing}
                            {...(field.props || {})}
                        />
                        <label htmlFor={field.name} className="ml-2 block text-sm text-gray-900">
                            {field.label}
                        </label>
                    </div>
                );

            case 'file':
                return (
                    <div className="space-y-2">
                        <input
                            type="file"
                            id={field.name}
                            onChange={(e) => {
                                const file = e.target.files[0];
                                if (file && field.validate) {
                                    const validationError = field.validate(file);
                                    if (validationError) {
                                        setError(field.name, validationError);
                                        e.target.value = '';
                                        return;
                                    }
                                }
                                handleFieldChange(field.name, file, field);
                            }}
                            className={inputClasses}
                            accept={field.accept}
                            disabled={field.disabled || processing}
                            {...(field.props || {})}
                        />
                        {field.helperText && (
                            <p className="text-xs text-gray-500">{field.helperText}</p>
                        )}
                        {field.preview && data[field.name] && (
                            <div className="mt-2">
                                {field.preview(data[field.name], mode === 'edit' ? initialData : null)}
                            </div>
                        )}
                    </div>
                );

            case 'custom':
                return field.render ? field.render(data, setData, errors, field) : null;

            default:
                return null;
        }
    };

    return (
        <Modal show={show} onClose={handleClose} title={title} maxWidth={maxWidth}>
            <form onSubmit={handleSubmit} className="space-y-6">
                {sections.map((section, sectionIndex) => (
                    <div key={sectionIndex} className="bg-gray-50 p-4 rounded-lg">
                        {section.title && (
                            <h4 className="text-sm font-medium text-gray-900 mb-4">
                                {section.title}
                            </h4>
                        )}

                        <div className={`grid grid-cols-1 ${section.columns || 'md:grid-cols-2 lg:grid-cols-3'} gap-4`}>
                            {section.fields.map((field, fieldIndex) => (
                                <div key={fieldIndex} className={field.fullWidth ? 'col-span-full' : ''}>
                                    {field.type !== 'checkbox' && field.label && (
                                        <label htmlFor={field.name} className="block text-sm font-medium text-gray-700 mb-1">
                                            {field.label} {field.required && <span className="text-red-600">*</span>}
                                        </label>
                                    )}

                                    {renderField(field)}

                                    {errors[field.name] && (
                                        <p className="mt-1 text-sm text-red-600">{errors[field.name]}</p>
                                    )}

                                    {!errors[field.name] && field.helperText && field.type !== 'file' && (
                                        <p className="mt-1 text-xs text-gray-500">{field.helperText}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

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
                        icon={processing ? null : submitButtonIcon || (({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ))}
                    >
                        {processing ? 'Salvando...' : submitButtonText}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
