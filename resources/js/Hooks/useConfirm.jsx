import { useState } from 'react';
import ConfirmDialog from '@/Components/ConfirmDialog';

export function useConfirm() {
    const [confirmState, setConfirmState] = useState({
        show: false,
        title: 'Confirmação',
        message: 'Tem certeza que deseja continuar?',
        confirmText: 'Confirmar',
        cancelText: 'Cancelar',
        type: 'warning',
        onConfirm: () => {},
    });

    const confirm = ({
        title = 'Confirmação',
        message = 'Tem certeza que deseja continuar?',
        confirmText = 'Confirmar',
        cancelText = 'Cancelar',
        type = 'warning',
    }) => {
        return new Promise((resolve) => {
            setConfirmState({
                show: true,
                title,
                message,
                confirmText,
                cancelText,
                type,
                onConfirm: () => {
                    resolve(true);
                    setConfirmState(prev => ({ ...prev, show: false }));
                },
            });
        });
    };

    const ConfirmDialogComponent = () => (
        <ConfirmDialog
            show={confirmState.show}
            onClose={() => setConfirmState(prev => ({ ...prev, show: false }))}
            onConfirm={confirmState.onConfirm}
            title={confirmState.title}
            message={confirmState.message}
            confirmText={confirmState.confirmText}
            cancelText={confirmState.cancelText}
            type={confirmState.type}
        />
    );

    return { confirm, ConfirmDialogComponent };
}
