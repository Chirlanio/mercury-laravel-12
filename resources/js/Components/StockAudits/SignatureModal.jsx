import { useState, useRef, useEffect, useCallback } from 'react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import { PencilIcon } from '@heroicons/react/24/outline';

const SIGNATURE_ROLES = [
    { value: 'gerente', label: 'Gerente' },
    { value: 'auditor', label: 'Auditor' },
    { value: 'supervisor', label: 'Supervisor' },
    { value: 'estoquista', label: 'Estoquista' },
];

export default function SignatureModal({ show, onClose, audit, onSuccess }) {
    const [role, setRole] = useState('gerente');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const managerCanvasRef = useRef(null);
    const auditorCanvasRef = useRef(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

    const clearCanvas = useCallback((ref) => {
        const canvas = ref.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }, []);

    const isCanvasBlank = useCallback((ref) => {
        const canvas = ref.current;
        if (!canvas) return true;
        const pixelData = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        for (let i = 0; i < pixelData.length; i += 4) {
            if (pixelData[i] !== 255 || pixelData[i + 1] !== 255 || pixelData[i + 2] !== 255) return false;
        }
        return true;
    }, []);

    const handleSubmit = async () => {
        setErrors({});
        const managerBlank = isCanvasBlank(managerCanvasRef);
        const auditorBlank = isCanvasBlank(auditorCanvasRef);
        if (managerBlank && auditorBlank) { setErrors({ general: 'Pelo menos uma assinatura deve ser preenchida.' }); return; }

        setProcessing(true);
        const signatures = [];
        if (!managerBlank) signatures.push({ role: 'gerente', signature_data: managerCanvasRef.current.toDataURL('image/png') });
        if (!auditorBlank) signatures.push({ role: 'auditor', signature_data: auditorCanvasRef.current.toDataURL('image/png') });

        try {
            const res = await fetch(route('stock-audits.sign', audit.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ signatures, role }),
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message || 'Erro ao salvar assinaturas.' }); return; }
            clearCanvas(managerCanvasRef); clearCanvas(auditorCanvasRef); onSuccess?.();
        } catch { setErrors({ general: 'Erro de conexão.' }); } finally { setProcessing(false); }
    };

    const handleClose = () => { setErrors({}); setRole('gerente'); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Assinaturas da Auditoria"
            headerColor="bg-indigo-600" headerIcon={<PencilIcon className="h-5 w-5" />}
            maxWidth="2xl" onSubmit={handleSubmit} errorMessage={errors.general}
            footer={<StandardModal.Footer onCancel={handleClose} onSubmit="submit"
                submitLabel="Salvar Assinaturas" processing={processing} />}>

            <div>
                <InputLabel value="Função do Assinante" />
                <select value={role} onChange={(e) => setRole(e.target.value)}
                    className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    {SIGNATURE_ROLES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                </select>
            </div>

            <div className="space-y-5">
                <SignaturePad label="Assinatura do Gerente" canvasRef={managerCanvasRef} onClear={() => clearCanvas(managerCanvasRef)} />
                <SignaturePad label="Assinatura do Auditor" canvasRef={auditorCanvasRef} onClear={() => clearCanvas(auditorCanvasRef)} />
            </div>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-xs text-blue-700">
                    As assinaturas serão salvas como parte do registro da auditoria.
                    Pelo menos uma assinatura deve ser preenchida para enviar.
                </p>
            </div>
        </StandardModal>
    );
}

function SignaturePad({ label, canvasRef, onClear }) {
    const [isDrawing, setIsDrawing] = useState(false);

    const getCoords = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    };

    const startDrawing = (e) => {
        e.preventDefault();
        const ctx = canvasRef.current.getContext('2d');
        const coords = getCoords(e, canvasRef.current);
        ctx.beginPath(); ctx.moveTo(coords.x, coords.y); setIsDrawing(true);
    };

    const draw = (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const ctx = canvasRef.current.getContext('2d');
        const coords = getCoords(e, canvasRef.current);
        ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.strokeStyle = '#1f2937';
        ctx.lineTo(coords.x, coords.y); ctx.stroke();
    };

    const stopDrawing = (e) => { if (e) e.preventDefault(); setIsDrawing(false); };

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
                <button type="button" onClick={onClear} className="text-xs text-red-500 hover:text-red-700">Limpar</button>
            </div>
            <canvas ref={canvasRef} width={400} height={150}
                className="w-full border border-gray-300 rounded-lg cursor-crosshair bg-white touch-none"
                onMouseDown={startDrawing} onMouseMove={draw} onMouseUp={stopDrawing} onMouseLeave={stopDrawing}
                onTouchStart={startDrawing} onTouchMove={draw} onTouchEnd={stopDrawing} />
            <p className="mt-1 text-xs text-gray-400">Assine com o mouse ou toque na área acima</p>
        </div>
    );
}
