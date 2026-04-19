import { useEffect, useState } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';
import {
    ArrowPathIcon,
    ArrowDownTrayIcon,
    PrinterIcon,
} from '@heroicons/react/24/outline';

const fmtMoney = (val) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

const fmtNumber = (val, decimals = 3) =>
    new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(val || 0);

export default function InvoiceDetailModal({ show, onClose, storeCode, invoiceNumber, movementDate }) {
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (show && storeCode && invoiceNumber && movementDate) {
            fetchInvoice();
        }
        if (!show) {
            setData(null);
            setError(null);
        }
    }, [show, storeCode, invoiceNumber, movementDate]);

    const fetchInvoice = async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await axios.get(`/movements/invoice/${storeCode}/${invoiceNumber}/${movementDate}`);
            setData(res.data);
        } catch (e) {
            setError(e.response?.data?.message || 'Erro ao carregar nota fiscal.');
        } finally {
            setLoading(false);
        }
    };

    const downloadXlsx = () => {
        window.location.href = `/movements/invoice/${storeCode}/${invoiceNumber}/${movementDate}/xlsx`;
    };

    const downloadPdf = () => {
        window.location.href = `/movements/invoice/${storeCode}/${invoiceNumber}/${movementDate}/pdf`;
    };

    const header = data?.header;
    const items = data?.items || [];
    const totals = data?.totals;

    const headerBadges = data
        ? [{ text: `${totals?.items ?? 0} ${totals?.items === 1 ? 'item' : 'itens'}` }]
        : [];

    const headerActions = data && (
        <div className="flex gap-2">
            <Button variant="outline" size="xs" onClick={downloadXlsx} icon={ArrowDownTrayIcon}
                className="text-white border-white/30 hover:bg-white/10">XLSX</Button>
            <Button variant="outline" size="xs" onClick={downloadPdf} icon={PrinterIcon}
                className="text-white border-white/30 hover:bg-white/10">PDF</Button>
        </div>
    );

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={invoiceNumber ? `Nota Fiscal ${invoiceNumber}` : 'Nota Fiscal'}
            subtitle={storeCode ? `Loja ${storeCode}` : null}
            headerColor="bg-indigo-700"
            headerBadges={headerBadges}
            headerActions={headerActions}
            loading={loading}
            errorMessage={error}
            maxWidth="6xl"
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {error && !loading && (
                <div className="mt-2">
                    <Button variant="outline" size="sm" icon={ArrowPathIcon} onClick={fetchInvoice}>
                        Tentar novamente
                    </Button>
                </div>
            )}

            {data && !loading && (
                <>
                    <StandardModal.Section title="Dados da Nota">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <StandardModal.Field label="Número da NF" value={header.invoice_number} />
                            <StandardModal.Field
                                label="Loja"
                                value={`${header.store_code}${header.store_name ? ' · '+header.store_name : ''}`}
                            />
                            <StandardModal.Field
                                label="Data / Hora"
                                value={`${header.movement_date
                                    ? new Date(header.movement_date+'T00:00:00').toLocaleDateString('pt-BR')
                                    : '-'}${header.movement_time ? ' · '+header.movement_time : ''}`}
                            />
                            <StandardModal.Field
                                label="Sincronizado em"
                                value={header.synced_at
                                    ? new Date(header.synced_at).toLocaleString('pt-BR')
                                    : '-'}
                            />
                            <StandardModal.Field label="CPF Cliente" value={header.cpf_customer || '-'} />
                            <StandardModal.Field label="CPF Consultor" value={header.cpf_consultant || '-'} />
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Totais">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <StandardModal.InfoCard label="Itens" value={totals.items} highlight />
                            <StandardModal.InfoCard label="Quantidade" value={fmtNumber(totals.quantity)} highlight />
                            <StandardModal.InfoCard label="Valor Realizado" value={fmtMoney(totals.realized_value)} highlight />
                            <StandardModal.InfoCard
                                label="Valor Líquido"
                                value={fmtMoney(totals.net_value)}
                                colorClass={totals.net_value < 0 ? 'bg-red-50' : 'bg-emerald-50'}
                            />
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title={`Itens (${items.length})`}>
                        <div className="overflow-x-auto -mx-4 -mb-4 max-h-[420px] overflow-y-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th className="px-2 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Data</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Hora</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Tipo</th>
                                        <th className="px-2 py-2 text-center text-[10px] font-medium text-gray-500 uppercase">E/S</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Ref/Tam</th>
                                        <th className="px-2 py-2 text-left text-[10px] font-medium text-gray-500 uppercase">Barcode</th>
                                        <th className="px-2 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Qtde</th>
                                        <th className="px-2 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Preço</th>
                                        <th className="px-2 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Desc.</th>
                                        <th className="px-2 py-2 text-right text-[10px] font-medium text-gray-500 uppercase">Líquido</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-100">
                                    {items.map((item) => (
                                        <tr key={item.id} className="hover:bg-gray-50">
                                            <td className="px-2 py-1.5 text-xs text-gray-900 whitespace-nowrap">{item.movement_date}</td>
                                            <td className="px-2 py-1.5 text-xs text-gray-500 whitespace-nowrap">{item.movement_time || '-'}</td>
                                            <td className="px-2 py-1.5 text-xs text-gray-600 whitespace-nowrap">{item.movement_type}</td>
                                            <td className="px-2 py-1.5 text-center">
                                                <StatusBadge variant={item.entry_exit === 'E' ? 'emerald' : 'warning'} size="sm">
                                                    {item.entry_exit === 'E' ? 'E' : 'S'}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-2 py-1.5 text-xs text-gray-900 whitespace-nowrap truncate max-w-[140px]" title={item.ref_size}>{item.ref_size || '-'}</td>
                                            <td className="px-2 py-1.5 text-xs text-gray-500 whitespace-nowrap font-mono">{item.barcode || '-'}</td>
                                            <td className="px-2 py-1.5 text-xs text-gray-900 text-right whitespace-nowrap font-mono">{fmtNumber(item.quantity)}</td>
                                            <td className="px-2 py-1.5 text-xs text-gray-900 text-right whitespace-nowrap font-mono">{fmtMoney(item.sale_price)}</td>
                                            <td className="px-2 py-1.5 text-xs text-right whitespace-nowrap font-mono text-gray-500">
                                                {item.discount_value > 0 ? fmtMoney(item.discount_value) : '-'}
                                            </td>
                                            <td className={`px-2 py-1.5 text-xs text-right whitespace-nowrap font-mono font-medium ${item.net_value < 0 ? 'text-red-600' : 'text-gray-900'}`}>
                                                {fmtMoney(item.net_value)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="bg-indigo-50 border-t-2 border-indigo-300 sticky bottom-0">
                                    <tr>
                                        <td colSpan={6} className="px-2 py-2 text-xs text-right font-bold text-indigo-700 uppercase">Totais</td>
                                        <td className="px-2 py-2 text-xs text-right font-mono font-bold text-indigo-900">{fmtNumber(totals.quantity)}</td>
                                        <td></td>
                                        <td className="px-2 py-2 text-xs text-right font-mono font-bold text-indigo-900">
                                            {totals.discount_value > 0 ? fmtMoney(totals.discount_value) : '-'}
                                        </td>
                                        <td className={`px-2 py-2 text-xs text-right font-mono font-bold ${totals.net_value < 0 ? 'text-red-600' : 'text-indigo-900'}`}>
                                            {fmtMoney(totals.net_value)}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
