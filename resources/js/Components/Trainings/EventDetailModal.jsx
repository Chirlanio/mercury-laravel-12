import { useState, useEffect } from 'react';
import {
    CalendarDaysIcon,
    ClockIcon,
    MapPinIcon,
    UserGroupIcon,
    StarIcon,
    DocumentTextIcon,
    PencilIcon,
    PrinterIcon,
} from '@heroicons/react/24/outline';
import { QRCodeSVG } from 'qrcode.react';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';
import { router } from '@inertiajs/react';

const STATUS_VARIANTS = {
    draft: 'gray',
    published: 'info',
    in_progress: 'warning',
    completed: 'success',
    cancelled: 'danger',
};

export default function EventDetailModal({ show, onClose, trainingId, canEdit, onEdit }) {
    const [training, setTraining] = useState(null);
    const [qrCodes, setQrCodes] = useState(null);
    const [loading, setLoading] = useState(true);
    const [processingCerts, setProcessingCerts] = useState(false);

    useEffect(() => {
        if (show && trainingId) {
            setLoading(true);
            fetch(route('trainings.show', trainingId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(res => res.ok ? res.json() : Promise.reject())
                .then(data => {
                    setTraining(data.training);
                    setQrCodes(data.qrCodes);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, trainingId]);

    const handleTransition = (newStatus) => {
        router.post(route('trainings.transition', trainingId), { status: newStatus }, {
            preserveScroll: true,
            onSuccess: () => {
                // Reload detail
                fetch(route('trainings.show', trainingId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(res => res.ok ? res.json() : Promise.reject())
                    .then(data => {
                        setTraining(data.training);
                        setQrCodes(data.qrCodes);
                    });
            },
        });
    };

    const handleGenerateCertificates = () => {
        setProcessingCerts(true);
        fetch(route('trainings.certificates.generate', trainingId), { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content } })
            .then(res => res.json())
            .then(data => {
                setProcessingCerts(false);
                alert(data.message);
            })
            .catch(() => setProcessingCerts(false));
    };

    const handlePrintQRCodes = () => {
        const printArea = document.getElementById('qr-codes-print-area');
        if (!printArea) return;

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        const svgs = printArea.querySelectorAll('svg');
        const cards = printArea.querySelectorAll(':scope > div');

        let attendanceLabel = '';
        let attendanceSvg = '';
        let evaluationLabel = '';
        let evaluationSvg = '';

        if (cards[0]) {
            attendanceLabel = cards[0].querySelector('span')?.textContent || 'Presença';
            attendanceSvg = cards[0].querySelector('svg')?.outerHTML || '';
        }
        if (cards[1]) {
            evaluationLabel = cards[1].querySelector('span')?.textContent || 'Avaliação';
            evaluationSvg = cards[1].querySelector('svg')?.outerHTML || '';
        }

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>QR Codes - ${training?.title || 'Treinamento'}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 40px; text-align: center; }
                    h1 { font-size: 22px; color: #1f2937; margin-bottom: 4px; }
                    .subtitle { font-size: 14px; color: #6b7280; margin-bottom: 32px; }
                    .qr-container { display: flex; justify-content: center; gap: 60px; }
                    .qr-card { display: flex; flex-direction: column; align-items: center; padding: 24px; border: 2px solid #e5e7eb; border-radius: 12px; }
                    .qr-card svg { width: 200px; height: 200px; }
                    .qr-label { font-size: 16px; font-weight: 700; margin-bottom: 12px; }
                    .qr-label.green { color: #16a34a; }
                    .qr-label.blue { color: #2563eb; }
                    .qr-hint { font-size: 11px; color: #9ca3af; margin-top: 12px; }
                    .footer { margin-top: 40px; font-size: 11px; color: #9ca3af; }
                    @media print { body { padding: 20px; } }
                </style>
            </head>
            <body>
                <h1>${training?.title || 'Treinamento'}</h1>
                <p class="subtitle">${training?.event_date_formatted || ''} — ${training?.facilitator?.name || ''}</p>
                <div class="qr-container">
                    <div class="qr-card">
                        <span class="qr-label green">${attendanceLabel}</span>
                        ${attendanceSvg}
                        <span class="qr-hint">Escaneie para registrar presença</span>
                    </div>
                    <div class="qr-card">
                        <span class="qr-label blue">${evaluationLabel}</span>
                        ${evaluationSvg}
                        <span class="qr-hint">Escaneie para avaliar o treinamento</span>
                    </div>
                </div>
                <p class="footer">Grupo Meia Sola — Mercury</p>
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.onload = () => {
            printWindow.print();
        };
    };

    const headerActions = canEdit && training?.valid_transitions?.length > 0 ? (
        <div className="flex items-center gap-2">
            {Object.entries(training.transition_labels || {}).map(([status, label]) => (
                <Button key={status} variant="light" size="xs" onClick={() => handleTransition(status)}>
                    {label}
                </Button>
            ))}
        </div>
    ) : null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={training?.title || 'Detalhes do Treinamento'}
            subtitle={training?.event_date_formatted}
            headerColor="bg-indigo-600"
            headerIcon={<CalendarDaysIcon className="h-5 w-5" />}
            headerBadges={training ? [
                { text: training.status_label, className: 'bg-white/20 text-white' },
            ] : []}
            headerActions={headerActions}
            maxWidth="4xl"
            loading={loading}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    cancelLabel="Fechar"
                    extraButtons={canEdit ? [
                        <Button key="edit" variant="primary" size="sm" icon={PencilIcon} onClick={onEdit}>
                            Editar
                        </Button>
                    ] : []}
                />
            }
        >
            {training && (
                <>
                    {/* Info Geral */}
                    <StandardModal.Section title="Informações Gerais">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Data" value={training.event_date_formatted} icon={CalendarDaysIcon} />
                            <StandardModal.Field label="Horário" value={`${training.start_time} - ${training.end_time}`} icon={ClockIcon} />
                            <StandardModal.Field label="Duração" value={training.duration_hours} />
                            <StandardModal.Field label="Local" value={training.location || 'Não informado'} icon={MapPinIcon} />
                            <StandardModal.Field label="Vagas" value={training.max_participants ? `${training.participant_count}/${training.max_participants}` : `${training.participant_count} (ilimitado)`} icon={UserGroupIcon} />
                            <StandardModal.Field label="Avaliação" value={training.average_rating ? `${training.average_rating}/5.0` : 'Sem avaliações'} icon={StarIcon} />
                        </div>
                    </StandardModal.Section>

                    {/* Facilitador & Assunto */}
                    <StandardModal.Section title="Facilitador & Assunto">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.Field label="Facilitador" value={training.facilitator?.name || '-'} />
                            <StandardModal.Field label="Assunto" value={training.subject?.name || '-'} />
                        </div>
                        {training.description && (
                            <div className="mt-3">
                                <p className="text-xs font-medium text-gray-500 mb-1">Descrição</p>
                                <div className="text-sm text-gray-700 prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: training.description }} />
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* QR Codes */}
                    {qrCodes && (
                        <StandardModal.Section title="QR Codes">
                            <div id="qr-codes-print-area" className="grid grid-cols-2 gap-6">
                                <div className="flex flex-col items-center gap-2 p-4 bg-green-50 rounded-lg border border-green-200">
                                    <span className="text-sm font-semibold text-green-800">Presença</span>
                                    <QRCodeSVG value={qrCodes.attendance.url} size={160} fgColor="#16a34a" bgColor="transparent" />
                                    <a href={qrCodes.attendance.url} target="_blank" rel="noopener"
                                        className="text-xs text-blue-600 hover:underline break-all text-center mt-1 print-hide">
                                        {qrCodes.attendance.url}
                                    </a>
                                </div>
                                <div className="flex flex-col items-center gap-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <span className="text-sm font-semibold text-blue-800">Avaliação</span>
                                    <QRCodeSVG value={qrCodes.evaluation.url} size={160} fgColor="#2563eb" bgColor="transparent" />
                                    <a href={qrCodes.evaluation.url} target="_blank" rel="noopener"
                                        className="text-xs text-blue-600 hover:underline break-all text-center mt-1 print-hide">
                                        {qrCodes.evaluation.url}
                                    </a>
                                </div>
                            </div>
                            <div className="flex justify-center mt-4">
                                <Button variant="outline" size="sm" icon={PrinterIcon} onClick={() => handlePrintQRCodes()}>
                                    Imprimir QR Codes
                                </Button>
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Participantes */}
                    <StandardModal.Section title={`Participantes (${training.participants?.length ?? 0})`}>
                        {training.participants?.length > 0 ? (
                            <div className="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                {training.participants.map(p => (
                                    <div key={p.id} className="flex items-center justify-between py-2">
                                        <div>
                                            <span className="text-sm font-medium text-gray-900">{p.name}</span>
                                            {p.is_late && <span className="ml-2 text-xs text-orange-500">(Atrasado)</span>}
                                        </div>
                                        <div className="flex items-center gap-3 text-xs text-gray-500">
                                            {p.attendance_time && <span>{p.attendance_time}</span>}
                                            {p.has_evaluated && p.evaluation && (
                                                <div className="flex items-center gap-1">
                                                    <StarIcon className="w-3 h-3 text-yellow-400" />
                                                    <span>{p.evaluation.rating}</span>
                                                </div>
                                            )}
                                            {p.certificate_generated && (
                                                <DocumentTextIcon className="w-4 h-4 text-green-500" title="Certificado gerado" />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">Nenhum participante registrado.</p>
                        )}
                    </StandardModal.Section>

                    {/* Avaliações */}
                    {training.evaluation_enabled && training.evaluation_summary && (
                        <StandardModal.Section title="Avaliações">
                            <div className="flex items-end justify-center gap-4 mb-3">
                                {[1, 2, 3, 4, 5].map(star => {
                                    const count = training.evaluation_summary.distribution[star] || 0;
                                    return (
                                        <div key={star} className="flex flex-col items-center">
                                            <span className="text-lg font-bold text-gray-900">{count}</span>
                                            <div className="flex items-center gap-0.5 mt-1">
                                                <StarIcon className="w-4 h-4 text-yellow-400" />
                                                <span className="text-xs font-medium text-gray-600">{star}</span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="text-center text-sm text-gray-500">
                                {training.evaluation_summary.count} avaliações — Média: {training.evaluation_summary.average || '-'}
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Certificados */}
                    {canEdit && ['in_progress', 'completed'].includes(training.status) && (
                        <StandardModal.Section title="Certificados">
                            <Button
                                variant="success"
                                size="sm"
                                icon={DocumentTextIcon}
                                loading={processingCerts}
                                onClick={handleGenerateCertificates}
                            >
                                Gerar Certificados
                            </Button>
                        </StandardModal.Section>
                    )}

                    {/* Audit */}
                    <StandardModal.Section title="Registro">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.MiniField label="Criado por" value={training.created_by || '-'} />
                            <StandardModal.MiniField label="Criado em" value={training.created_at} />
                            <StandardModal.MiniField label="Atualizado por" value={training.updated_by || '-'} />
                            <StandardModal.MiniField label="Atualizado em" value={training.updated_at || '-'} />
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
