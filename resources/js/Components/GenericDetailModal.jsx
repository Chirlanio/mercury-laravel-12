import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

/**
 * Modal Genérico para Visualização de Detalhes
 *
 * @param {boolean} show - Controla se o modal está visível
 * @param {function} onClose - Função chamada ao fechar o modal
 * @param {string} title - Título do modal
 * @param {string|number|null} resourceId - ID do recurso a ser carregado
 * @param {string} fetchUrl - URL para buscar os dados (sem o ID)
 * @param {object|null} data - Dados já carregados (alternativa ao fetchUrl)
 * @param {array} sections - Array de seções para exibição
 * @param {array} actions - Array de ações disponíveis
 * @param {string} maxWidth - Largura máxima do modal
 * @param {object} header - Configuração do cabeçalho personalizado
 * @param {function} renderHeader - Função customizada para renderizar cabeçalho
 * @param {string} emptyMessage - Mensagem quando não há dados
 */
export default function GenericDetailModal({
    show,
    onClose,
    title,
    resourceId = null,
    fetchUrl = null,
    data: externalData = null,
    sections = [],
    actions = [],
    maxWidth = '85vw',
    header = null,
    renderHeader = null,
    emptyMessage = 'Nenhum dado disponível',
}) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (show) {
            if (externalData) {
                setData(externalData);
            } else if (resourceId && fetchUrl) {
                fetchData();
            }
        } else {
            // Reset state when modal closes
            if (!externalData) {
                setData(null);
            }
            setError(null);
        }
    }, [show, resourceId, externalData]);

    const fetchData = async () => {
        setLoading(true);
        setError(null);

        try {
            const url = fetchUrl.includes('{id}')
                ? fetchUrl.replace('{id}', resourceId)
                : `${fetchUrl}/${resourceId}`;

            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });

            if (!response.ok) {
                throw new Error('Erro ao carregar dados');
            }

            const result = await response.json();
            setData(result.data || result);
        } catch (err) {
            setError('Erro ao carregar informações');
            console.error('Erro ao buscar dados:', err);
        } finally {
            setLoading(false);
        }
    };

    const renderFieldValue = (field, data) => {
        if (!data) return '-';

        const value = field.path ? getNestedValue(data, field.path) : data[field.name];

        if (value === null || value === undefined || value === '') {
            return field.emptyText || 'Não informado';
        }

        if (field.render) {
            return field.render(value, data);
        }

        if (field.type === 'badge') {
            const badgeConfig = field.getBadgeConfig ? field.getBadgeConfig(value, data) : {};
            return (
                <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${badgeConfig.className || 'bg-gray-100 text-gray-800'}`}>
                    {badgeConfig.label || value}
                </span>
            );
        }

        if (field.type === 'boolean') {
            return value ? (field.trueText || 'Sim') : (field.falseText || 'Não');
        }

        if (field.type === 'date' && value) {
            return new Date(value).toLocaleDateString('pt-BR');
        }

        if (field.type === 'datetime' && value) {
            return new Date(value).toLocaleString('pt-BR');
        }

        if (field.type === 'currency' && value) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
        }

        if (field.type === 'list' && Array.isArray(value)) {
            return (
                <ul className="list-disc list-inside">
                    {value.map((item, index) => (
                        <li key={index}>{item}</li>
                    ))}
                </ul>
            );
        }

        return value;
    };

    const getNestedValue = (obj, path) => {
        return path.split('.').reduce((acc, part) => acc && acc[part], obj);
    };

    if (loading) {
        return (
            <Modal show={show} onClose={onClose} title={title} maxWidth={maxWidth}>
                <div className="flex items-center justify-center" style={{ minHeight: '400px' }}>
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>
            </Modal>
        );
    }

    if (error) {
        return (
            <Modal show={show} onClose={onClose} title="Erro" maxWidth={maxWidth}>
                <div className="text-center py-8" style={{ minHeight: '400px' }}>
                    <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Erro</h3>
                    <p className="text-gray-500 mb-4">{error}</p>
                    <div className="flex justify-center space-x-3">
                        <Button onClick={fetchData} variant="primary">
                            Tentar novamente
                        </Button>
                        <Button onClick={onClose} variant="outline">
                            Fechar
                        </Button>
                    </div>
                </div>
            </Modal>
        );
    }

    return (
        <Modal show={show} onClose={onClose} title={title} maxWidth={maxWidth}>
            {data ? (
                <div className="space-y-6">
                    {/* Cabeçalho personalizado */}
                    {renderHeader ? (
                        renderHeader(data)
                    ) : header && (
                        <div className="flex items-center space-x-4 pb-6 border-b">
                            {header.avatar && (
                                <div className="flex-shrink-0">
                                    {header.avatar(data)}
                                </div>
                            )}
                            <div className="flex-1">
                                {header.title && (
                                    <h3 className="text-xl font-semibold text-gray-900">
                                        {typeof header.title === 'function' ? header.title(data) : data[header.title]}
                                    </h3>
                                )}
                                {header.subtitle && (
                                    <p className="text-gray-600">
                                        {typeof header.subtitle === 'function' ? header.subtitle(data) : data[header.subtitle]}
                                    </p>
                                )}
                                {header.badges && (
                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                        {header.badges(data)}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Seções de informações */}
                    {sections.length > 0 && (
                        <div className={`grid grid-cols-1 ${sections.length > 1 ? 'md:grid-cols-2' : ''} gap-6`}>
                            {sections.map((section, sectionIndex) => (
                                <div key={sectionIndex} className={`bg-gray-50 p-4 rounded-lg ${section.fullWidth ? 'col-span-full' : ''}`}>
                                    {section.title && (
                                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                                            {section.title}
                                        </h4>
                                    )}

                                    {section.render ? (
                                        section.render(data)
                                    ) : (
                                        <div className="space-y-2 text-sm">
                                            {section.fields && section.fields.map((field, fieldIndex) => (
                                                <div key={fieldIndex} className={field.fullWidth ? 'col-span-full' : ''}>
                                                    <span className="font-medium text-gray-600">{field.label}:</span>
                                                    <span className="ml-2 text-gray-900">
                                                        {renderFieldValue(field, data)}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Ações disponíveis */}
                    {actions.length > 0 && (
                        <div className="bg-blue-50 p-4 rounded-lg">
                            <h4 className="text-sm font-medium text-blue-900 mb-3">
                                Ações Disponíveis
                            </h4>
                            <div className="flex flex-wrap gap-3">
                                {actions.map((action, index) => (
                                    <Button
                                        key={index}
                                        onClick={() => action.onClick(data)}
                                        variant={action.variant || 'primary'}
                                        icon={action.icon}
                                        disabled={action.disabled ? action.disabled(data) : false}
                                    >
                                        {action.label}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Botão fechar */}
                    <div className="flex justify-end pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>
                            Fechar
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="text-center py-12">
                    <p className="text-gray-500">{emptyMessage}</p>
                </div>
            )}
        </Modal>
    );
}
