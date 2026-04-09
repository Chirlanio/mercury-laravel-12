import { useState, useRef, useEffect, useCallback } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const SIGNATURE_ROLES = [
    { value: 'gerente', label: 'Gerente' },
    { value: 'auditor', label: 'Auditor' },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'estoquista', label: 'Estoquista' },
];

function SignaturePad({ label, canvasRef, onClear }) {
    const [isDrawing, setIsDrawing] = useState(false);

    const getCoords = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top,
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        };
    };

    const startDrawing = (e) => {
        e.preventDefault();
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        const coords = getCoords(e, canvas);
        ctx.beginPath();
        ctx.moveTo(coords.x, coords.y);
        setIsDrawing(true);
    };

    const draw = (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const canvas = canvasRef.current;
        const ctx = canvas.getContext('2d');
        const coords = getCoords(e, canvas);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#1f2937';
        ctx.lineTo(coords.x, coords.y);
        ctx.stroke();
    };

    const stopDrawing = (e) => {
        if (e) e.preventDefault();
        setIsDrawing(false);
    };

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }, [canvasRef]);

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <label className="text-sm font-medium text-gray-700">{label}</label>
                <button
                    type="button"
                    onClick={onClear}
                    className="text-xs text-red-500 hover:text-red-700 transition"
                >
                    Limpar
                </button>
            </div>
            <canvas
                ref={canvasRef}
                width={400}
                height={150}
                className="w-full border border-gray-300 rounded-lg cursor-crosshair bg-white touch-none"
                onMouseDown={startDrawing}
                onMouseMove={draw}
                onMouseUp={stopDrawing}
                onMouseLeave={stopDrawing}
                onTouchStart={startDrawing}
                onTouchMove={draw}
                onTouchEnd={stopDrawing}
            />
            <p className="mt-1 text-xs text-gray-400">Assine com o mouse ou toque na area acima</p>
        </div>
    );
}

export default function SignatureModal({ show, onClose, audit, onSuccess }) {
    const [role, setRole] = useState('gerente');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const managerCanvasRef = useRef(null);
    const auditorCanvasRef = useRef(null);

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const clearCanvas = useCallback((canvasRef) => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }, []);

    const isCanvasBlank = useCallback((canvasRef) => {
        const canvas = canvasRef.current;
        if (!canvas) return true;

        const ctx = canvas.getContext('2d');
        const pixelData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;

        // Check if all pixels are white (255, 255, 255, 255)
        for (let i = 0; i < pixelData.length; i += 4) {
            if (pixelData[i] !== 255 || pixelData[i + 1] !== 255 || pixelData[i + 2] !== 255) {
                return false;
            }
        }
        return true;
    }, []);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        const managerBlank = isCanvasBlank(managerCanvasRef);
        const auditorBlank = isCanvasBlank(auditorCanvasRef);

        if (managerBlank && auditorBlank) {
            setErrors({ general: 'Pelo menos uma assinatura deve ser preenchida.' });
            return;
        }

        setProcessing(true);

        const signatures = [];

        if (!managerBlank) {
            signatures.push({
                role: 'gerente',
                signature_data: managerCanvasRef.current.toDataURL('image/png'),
            });
        }

        if (!auditorBlank) {
            signatures.push({
                role: 'auditor',
                signature_data: auditorCanvasRef.current.toDataURL('image/png'),
            });
        }

        try {
            const res = await fetch(route('stock-audits.sign', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    signatures,
                    role,
                }),
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao salvar assinaturas.' });
                return;
            }

            clearCanvas(managerCanvasRef);
            clearCanvas(auditorCanvasRef);
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleClose = () => {
        setErrors({});
        setRole('gerente');
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Assinaturas da Auditoria</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
                    <div className="flex-1 overflow-y-auto p-6 space-y-6">
                        {errors.general && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                                {errors.general}
                            </div>
                        )}

                        {/* Role selector */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Funcao do Assinante
                            </label>
                            <select
                                value={role}
                                onChange={(e) => setRole(e.target.value)}
                                className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            >
                                {SIGNATURE_ROLES.map((r) => (
                                    <option key={r.value} value={r.value}>{r.label}</option>
                                ))}
                            </select>
                        </div>

                        {/* Signature Pads */}
                        <div className="space-y-5">
                            <SignaturePad
                                label="Assinatura do Gerente"
                                canvasRef={managerCanvasRef}
                                onClear={() => clearCanvas(managerCanvasRef)}
                            />

                            <SignaturePad
                                label="Assinatura do Auditor"
                                canvasRef={auditorCanvasRef}
                                onClear={() => clearCanvas(auditorCanvasRef)}
                            />
                        </div>

                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p className="text-xs text-blue-700">
                                As assinaturas serao salvas como parte do registro da auditoria.
                                Pelo menos uma assinatura deve ser preenchida para enviar.
                            </p>
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                        <Button type="button" variant="secondary" onClick={handleClose}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="primary" disabled={processing} loading={processing}>
                            {processing ? 'Salvando...' : 'Salvar Assinaturas'}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
