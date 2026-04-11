import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import { ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import Button from '@/Components/Button';

/**
 * Modal de confirmacao de exclusao reutilizavel.
 *
 * Padrao visual consistente para todas as acoes destrutivas do sistema.
 * Exibe nome do item, detalhes opcionais, aviso de permanencia e botoes de acao.
 *
 * @param {boolean} show - Controla visibilidade do modal
 * @param {function} onClose - Callback ao fechar (cancelar)
 * @param {function} onConfirm - Callback ao confirmar exclusao
 * @param {string} itemType - Tipo do item (ex: "funcionário", "venda", "produto")
 * @param {string} itemName - Nome/identificador do item a ser excluido
 * @param {Array<{label: string, value: string}>} details - Detalhes opcionais do item
 * @param {string} warningMessage - Mensagem de aviso customizada (opcional)
 * @param {string} confirmLabel - Texto do botao de confirmacao (default: "Excluir")
 * @param {boolean} processing - Estado de processamento (desabilita botoes, mostra loading)
 *
 * @example
 * // Uso basico
 * const [deleteTarget, setDeleteTarget] = useState(null);
 *
 * <DeleteConfirmModal
 *     show={deleteTarget !== null}
 *     onClose={() => setDeleteTarget(null)}
 *     onConfirm={handleDelete}
 *     itemType="funcionário"
 *     itemName={deleteTarget?.name}
 * />
 *
 * @example
 * // Com detalhes e processing
 * <DeleteConfirmModal
 *     show={deleteTarget !== null}
 *     onClose={() => setDeleteTarget(null)}
 *     onConfirm={handleDelete}
 *     itemType="venda"
 *     itemName={`Venda #${deleteTarget?.id}`}
 *     details={[
 *         { label: 'Funcionário', value: deleteTarget?.employee_name },
 *         { label: 'Valor', value: deleteTarget?.total },
 *     ]}
 *     processing={deleting}
 * />
 */
export default function DeleteConfirmModal({
    show = false,
    onClose,
    onConfirm,
    itemType = 'item',
    itemName = '',
    details = [],
    warningMessage,
    confirmLabel = 'Excluir',
    processing = false,
}) {
    const handleConfirm = () => {
        if (!processing) onConfirm();
    };

    return (
        <Transition show={show} leave="duration-200">
            <Dialog as="div" className="fixed inset-0 z-[60] overflow-y-auto" onClose={onClose}>
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-500/75 transition-opacity" />
                </TransitionChild>

                <div className="fixed inset-0 z-[61] flex items-center justify-center p-4">
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 translate-y-4 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-4 sm:scale-95"
                    >
                        <DialogPanel className="relative w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
                            {/* Header vermelho */}
                            <div className="bg-red-600 px-6 py-4 flex items-center gap-3">
                                <ExclamationTriangleIcon className="h-6 w-6 text-white shrink-0" />
                                <h3 className="text-lg font-semibold text-white">Confirmar Exclusão</h3>
                            </div>

                            {/* Body */}
                            <div className="px-6 py-5 space-y-4">
                                <p className="text-sm text-gray-700">
                                    Tem certeza que deseja excluir {itemType && `${getArticle(itemType)} `}
                                    <strong className="text-gray-900">{itemName || itemType}</strong>?
                                </p>

                                {/* Detalhes do item */}
                                {details.length > 0 && (
                                    <div className="bg-gray-50 rounded-lg p-3 space-y-1.5">
                                        {details.filter(d => d.value).map((detail, i) => (
                                            <div key={i} className="flex justify-between text-sm">
                                                <span className="text-gray-500">{detail.label}</span>
                                                <span className="font-medium text-gray-900">{detail.value}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Aviso */}
                                <div className="flex items-start gap-2 bg-red-50 border border-red-100 rounded-lg p-3">
                                    <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                    <p className="text-sm text-red-700">
                                        {warningMessage || 'Esta ação não pode ser desfeita. Todos os dados relacionados serão permanentemente excluídos.'}
                                    </p>
                                </div>
                            </div>

                            {/* Footer */}
                            <div className="flex justify-end gap-3 px-6 py-4 bg-gray-50 border-t">
                                <Button
                                    variant="outline"
                                    onClick={onClose}
                                    disabled={processing}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="danger"
                                    onClick={handleConfirm}
                                    loading={processing}
                                >
                                    {confirmLabel}
                                </Button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}

/**
 * Retorna artigo definido correto para o tipo do item (heuristica simples).
 * Ex: "funcionário" → "o", "venda" → "a", "item" → "o"
 */
function getArticle(itemType) {
    const feminineEndings = ['a', 'ão', 'ade', 'ção', 'são'];
    const lower = itemType.toLowerCase();
    const isFeminine = feminineEndings.some(e => lower.endsWith(e));
    return isFeminine ? 'a' : 'o';
}
