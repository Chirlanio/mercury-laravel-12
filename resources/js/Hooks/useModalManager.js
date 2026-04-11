import { useState, useCallback, useMemo } from 'react';

/**
 * Hook para gerenciar o estado de múltiplos modais em uma página.
 *
 * @param {string[]} modalNames - Lista de nomes dos modais (ex: ['create', 'view', 'edit', 'delete'])
 * @returns {object} Estado e handlers dos modais
 *
 * @example
 * const { modals, openModal, closeModal, selected, setSelected } = useModalManager(['create', 'view', 'edit', 'delete']);
 *
 * // Abrir modal com item selecionado
 * openModal('view', employee);
 *
 * // Abrir modal sem item (ex: create)
 * openModal('create');
 *
 * // Fechar modal (limpa selected automaticamente)
 * closeModal('view');
 *
 * // Transição entre modais (ex: view -> edit)
 * openModal('edit', selected); // selected mantém o item do modal anterior
 *
 * // No JSX
 * <Modal show={modals.view} onClose={() => closeModal('view')}>
 *   <DetailModal item={selected} />
 * </Modal>
 */
export default function useModalManager(modalNames = []) {
    const initialState = useMemo(
        () => Object.fromEntries(modalNames.map((name) => [name, false])),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [modalNames.join(',')]
    );

    const [modals, setModals] = useState(initialState);
    const [selected, setSelected] = useState(null);

    const openModal = useCallback((name, item = null) => {
        if (item !== null) {
            setSelected(item);
        }
        setModals((prev) => ({ ...prev, [name]: true }));
    }, []);

    const closeModal = useCallback((name, clearSelected = true) => {
        setModals((prev) => ({ ...prev, [name]: false }));
        if (clearSelected) {
            setSelected(null);
        }
    }, []);

    const closeAll = useCallback(() => {
        setModals(initialState);
        setSelected(null);
    }, [initialState]);

    const switchModal = useCallback((fromName, toName, item = undefined) => {
        setModals((prev) => ({ ...prev, [fromName]: false, [toName]: true }));
        if (item !== undefined) {
            setSelected(item);
        }
    }, []);

    return {
        modals,
        selected,
        setSelected,
        openModal,
        closeModal,
        closeAll,
        switchModal,
    };
}
