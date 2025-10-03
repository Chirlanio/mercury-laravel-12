import Modal from './Modal';
import Button from './Button';

export default function DocumentViewerModal({ show, onClose, documentUrl, documentType, eventType }) {
    const isPDF = documentUrl?.toLowerCase().endsWith('.pdf');
    const isImage = documentUrl?.match(/\.(jpg|jpeg|png)$/i);

    const handleDownload = () => {
        const link = document.createElement('a');
        link.href = documentUrl;
        link.download = `documento_${eventType}_${Date.now()}`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handlePrint = () => {
        const printWindow = window.open(documentUrl, '_blank');
        if (printWindow) {
            printWindow.onload = () => {
                printWindow.print();
            };
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="6xl">
            <div className="p-6">
                {/* Header */}
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">
                            Visualizar Documento
                        </h2>
                        {eventType && (
                            <p className="text-sm text-gray-600 mt-1">
                                {eventType}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handlePrint}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                            )}
                        >
                            Imprimir
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleDownload}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            )}
                        >
                            Baixar
                        </Button>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Document Viewer */}
                <div className="bg-gray-100 rounded-lg overflow-hidden" style={{ minHeight: '70vh' }}>
                    {isPDF ? (
                        <iframe
                            src={documentUrl}
                            className="w-full h-full"
                            style={{ minHeight: '70vh' }}
                            title="Visualizador de PDF"
                        />
                    ) : isImage ? (
                        <div className="flex items-center justify-center p-4" style={{ minHeight: '70vh' }}>
                            <img
                                src={documentUrl}
                                alt="Documento"
                                className="max-w-full max-h-full object-contain rounded shadow-lg"
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col items-center justify-center p-12" style={{ minHeight: '70vh' }}>
                            <svg className="w-24 h-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                Pré-visualização não disponível
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Este tipo de arquivo não pode ser visualizado diretamente no navegador.
                            </p>
                            <Button
                                variant="primary"
                                onClick={handleDownload}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                )}
                            >
                                Baixar Documento
                            </Button>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex justify-end pt-4 border-t mt-4">
                    <Button
                        variant="outline"
                        onClick={onClose}
                    >
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
