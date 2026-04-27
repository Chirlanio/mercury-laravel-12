import { useState, useRef, useEffect, useMemo } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button, { getButtonClasses } from '@/Components/Button';
import {
    ArrowUpTrayIcon, ArrowPathIcon, ArrowDownTrayIcon,
    FolderIcon, PlayIcon, PauseIcon, ExclamationTriangleIcon,
    CheckCircleIcon, XCircleIcon, PhotoIcon,
} from '@heroicons/react/24/outline';

const MAX_FILES_PER_SESSION = 1000;
const BATCH_SIZE = 10;
const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp'];
const STORAGE_KEY = 'mercury:product-bulk-image-upload';

/* -------------------- session persistence (localStorage) -------------------- */

function loadSession() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        // sessões com mais de 24h são descartadas
        if (Date.now() - parsed.createdAt > 24 * 60 * 60 * 1000) {
            localStorage.removeItem(STORAGE_KEY);
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
}

function saveSession(state) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch {
        // localStorage cheio — silenciosamente ignora
    }
}

function clearSession() {
    try { localStorage.removeItem(STORAGE_KEY); } catch {}
}

/* -------------------- helpers -------------------- */

const isAllowedExt = (name) => {
    const ext = name.split('.').pop()?.toLowerCase() ?? '';
    return ALLOWED_EXT.includes(ext);
};

const formatDuration = (ms) => {
    if (!Number.isFinite(ms) || ms < 0) return '—';
    const s = Math.floor(ms / 1000);
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    const rs = s % 60;
    if (m < 60) return `${m}min ${rs}s`;
    const h = Math.floor(m / 60);
    const rm = m % 60;
    return `${h}h ${rm}min`;
};

const downloadCsv = (rows, filename) => {
    if (!rows.length) return;
    const headers = Object.keys(rows[0]);
    const escape = (v) => {
        const s = v == null ? '' : String(v);
        return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
    };
    const csv = [
        headers.join(';'),
        ...rows.map(r => headers.map(h => escape(r[h])).join(';')),
    ].join('\n');
    const blob = new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
};

/* -------------------- componente -------------------- */

export default function ProductBulkImageUploadModal({ show, onClose, onCompleted }) {
    const [phase, setPhase] = useState('select'); // select | preview | uploading | done
    const [files, setFiles] = useState([]); // File[]
    const [preview, setPreview] = useState(null);
    const [conflictDecision, setConflictDecision] = useState('skip'); // replace | skip
    const [skipNotFound] = useState(true); // sempre pulamos não-encontrados
    const [results, setResults] = useState([]);
    const [progress, setProgress] = useState({ current: 0, total: 0 });
    const [paused, setPaused] = useState(false);
    const [error, setError] = useState('');
    const [previousSession, setPreviousSession] = useState(null);
    const [dragHover, setDragHover] = useState(false);

    const fileInputRef = useRef(null);
    const dirInputRef = useRef(null);
    const pausedRef = useRef(false);
    const cancelledRef = useRef(false);
    const startTimeRef = useRef(null);

    // ao abrir, detecta sessão anterior
    useEffect(() => {
        if (show) {
            const saved = loadSession();
            if (saved && saved.completed && Object.keys(saved.completed).length > 0) {
                setPreviousSession(saved);
            } else {
                setPreviousSession(null);
            }
        }
    }, [show]);

    const eta = useMemo(() => {
        if (phase !== 'uploading' || !startTimeRef.current || progress.current === 0) return null;
        const elapsed = Date.now() - startTimeRef.current;
        const perItem = elapsed / progress.current;
        const remaining = (progress.total - progress.current) * perItem;
        return remaining;
    }, [progress, phase]);

    /* --------------- seleção --------------- */

    const handleFiles = (rawFiles) => {
        const arr = Array.from(rawFiles).filter(f => isAllowedExt(f.name));
        if (arr.length === 0) {
            setError('Nenhum arquivo válido (use jpg, jpeg, png ou webp).');
            return;
        }
        if (arr.length > MAX_FILES_PER_SESSION) {
            setError(`Máximo de ${MAX_FILES_PER_SESSION} arquivos por sessão. Você selecionou ${arr.length.toLocaleString('pt-BR')}. Pra cargas maiores, use o comando artisan.`);
            return;
        }
        setError('');
        setFiles(arr);
    };

    const handleFileInputChange = (e) => handleFiles(e.target.files);

    const handleDrop = (e) => {
        e.preventDefault();
        setDragHover(false);
        const list = [];
        const items = e.dataTransfer.items;
        if (items && items[0]?.webkitGetAsEntry) {
            // suporta drag de pasta
            const promises = [];
            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry();
                if (entry) promises.push(walkEntry(entry, list));
            }
            Promise.all(promises).then(() => handleFiles(list));
        } else {
            handleFiles(e.dataTransfer.files);
        }
    };

    const walkEntry = (entry, out) => new Promise((resolve) => {
        if (entry.isFile) {
            entry.file((file) => { out.push(file); resolve(); });
        } else if (entry.isDirectory) {
            const reader = entry.createReader();
            const read = () => reader.readEntries((entries) => {
                if (entries.length === 0) { resolve(); return; }
                Promise.all(entries.map(e => walkEntry(e, out))).then(read);
            });
            read();
        } else { resolve(); }
    });

    /* --------------- preview --------------- */

    const requestPreview = async () => {
        if (files.length === 0) return;
        setError('');
        try {
            const filenames = files.map(f => f.name);
            const { data } = await axios.post('/products/images/preview', { filenames });
            setPreview(data);
            // se há conflitos, default skip; caso contrário, replace é irrelevante
            setConflictDecision('skip');
            setPhase('preview');
        } catch (err) {
            setError(err.response?.data?.message ?? 'Falha ao analisar arquivos.');
        }
    };

    /* --------------- upload em lotes --------------- */

    const startUpload = async (resumeWith = null) => {
        const completedMap = resumeWith?.completed ?? {};
        // determina lista a enviar — pula nomes já concluídos da sessão anterior
        const queue = files.filter(f => !completedMap[f.name]);
        // remove not_found e invalid: já sabemos que não vão passar — registra como resultado e não envia
        const notFoundNames = new Set((preview?.not_found ?? []).map(x => x.filename));
        const invalidNames = new Set((preview?.invalid ?? []).map(x => x.filename));
        const actualQueue = [];
        const preResults = [];
        for (const f of queue) {
            if (notFoundNames.has(f.name)) {
                preResults.push({ filename: f.name, status: 'not_found', message: 'Sem produto correspondente.', reference: null });
            } else if (invalidNames.has(f.name)) {
                preResults.push({ filename: f.name, status: 'invalid', message: 'Extensão não suportada.', reference: null });
            } else {
                actualQueue.push(f);
            }
        }

        const initialResults = [
            ...Object.values(completedMap),
            ...preResults,
        ];
        setResults(initialResults);
        setProgress({ current: initialResults.length, total: files.length });
        setPhase('uploading');
        setPaused(false);
        pausedRef.current = false;
        cancelledRef.current = false;
        startTimeRef.current = Date.now();

        const session = {
            createdAt: Date.now(),
            decision: conflictDecision,
            completed: { ...completedMap },
        };
        for (const r of preResults) session.completed[r.filename] = r;
        saveSession(session);

        for (let i = 0; i < actualQueue.length; i += BATCH_SIZE) {
            // pause loop
            while (pausedRef.current && !cancelledRef.current) {
                // eslint-disable-next-line no-await-in-loop
                await new Promise(res => setTimeout(res, 300));
            }
            if (cancelledRef.current) break;

            const batch = actualQueue.slice(i, i + BATCH_SIZE);
            const formData = new FormData();
            batch.forEach(f => formData.append('files[]', f, f.name));
            formData.append('on_conflict', conflictDecision);

            try {
                // eslint-disable-next-line no-await-in-loop
                const { data } = await axios.post('/products/images/upload-batch', formData, {
                    timeout: 0,
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                const batchResults = data.results ?? [];
                setResults(prev => {
                    const next = [...prev, ...batchResults];
                    setProgress({ current: next.length, total: files.length });
                    for (const r of batchResults) session.completed[r.filename] = r;
                    saveSession(session);
                    return next;
                });
            } catch (err) {
                // marca o lote inteiro como erro mas continua
                const batchErrors = batch.map(f => ({
                    filename: f.name,
                    status: 'error',
                    message: err.response?.data?.message ?? 'Falha na requisição.',
                    reference: null,
                }));
                setResults(prev => {
                    const next = [...prev, ...batchErrors];
                    setProgress({ current: next.length, total: files.length });
                    for (const r of batchErrors) session.completed[r.filename] = r;
                    saveSession(session);
                    return next;
                });
            }
        }

        if (!cancelledRef.current) {
            setPhase('done');
            clearSession();
            onCompleted?.();
        }
    };

    const handlePauseToggle = () => {
        const next = !pausedRef.current;
        pausedRef.current = next;
        setPaused(next);
    };

    const handleCancel = () => {
        cancelledRef.current = true;
        pausedRef.current = false;
    };

    /* --------------- close / reset --------------- */

    const reset = () => {
        setPhase('select');
        setFiles([]);
        setPreview(null);
        setConflictDecision('skip');
        setResults([]);
        setProgress({ current: 0, total: 0 });
        setPaused(false);
        setError('');
        if (fileInputRef.current) fileInputRef.current.value = '';
        if (dirInputRef.current) dirInputRef.current.value = '';
    };

    const handleClose = () => {
        if (phase === 'uploading') {
            handleCancel();
        }
        reset();
        onClose?.();
    };

    const summary = useMemo(() => {
        const counts = { uploaded: 0, replaced: 0, skipped: 0, not_found: 0, invalid: 0, errors: 0 };
        for (const r of results) {
            if (counts[r.status] !== undefined) counts[r.status]++;
            else if (r.status === 'error') counts.errors++;
        }
        return counts;
    }, [results]);

    /* --------------- footer --------------- */

    const footer = (() => {
        if (phase === 'select') {
            return (
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="outline" onClick={handleClose}>Cancelar</Button>
                    <Button variant="primary" icon={ArrowUpTrayIcon}
                        disabled={files.length === 0}
                        onClick={requestPreview}>
                        Analisar {files.length > 0 ? `(${files.length.toLocaleString('pt-BR')})` : ''}
                    </Button>
                </StandardModal.Footer>
            );
        }
        if (phase === 'preview') {
            const willUpload = (preview?.counts?.matched ?? 0)
                + (conflictDecision === 'replace' ? (preview?.counts?.conflicts ?? 0) : 0);
            return (
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="outline" onClick={() => { reset(); }}>Voltar</Button>
                    <Button variant="primary" icon={ArrowUpTrayIcon}
                        disabled={willUpload === 0}
                        onClick={() => startUpload(null)}>
                        Iniciar upload ({willUpload.toLocaleString('pt-BR')})
                    </Button>
                </StandardModal.Footer>
            );
        }
        if (phase === 'uploading') {
            return (
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="outline" onClick={handlePauseToggle}
                        icon={paused ? PlayIcon : PauseIcon}>
                        {paused ? 'Retomar' : 'Pausar'}
                    </Button>
                    <Button variant="danger-soft" onClick={handleCancel}>Cancelar</Button>
                </StandardModal.Footer>
            );
        }
        // done
        return (
            <StandardModal.Footer>
                <div className="flex-1" />
                <Button variant="outline" icon={ArrowPathIcon} onClick={() => { reset(); }}>
                    Nova importação
                </Button>
                <Button variant="primary" onClick={handleClose}>Fechar</Button>
            </StandardModal.Footer>
        );
    })();

    /* --------------- render --------------- */

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Importar Imagens de Produtos"
            subtitle="Identifica o produto pela referência no nome do arquivo (ex: REF-001.jpg)"
            headerColor="bg-indigo-600"
            headerIcon={<PhotoIcon className="h-5 w-5" />}
            maxWidth="2xl"
            errorMessage={error}
            footer={footer}
        >
            {/* ===== SELECT ===== */}
            {phase === 'select' && (
                <>
                    {previousSession && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-3">
                            <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-amber-900">
                                    Sessão anterior pendente
                                </p>
                                <p className="text-xs text-amber-700 mt-0.5">
                                    {Object.keys(previousSession.completed).length.toLocaleString('pt-BR')} arquivos
                                    já processados. Selecione a mesma pasta pra continuar de onde parou —
                                    arquivos já enviados serão pulados automaticamente.
                                </p>
                                <button
                                    onClick={() => { clearSession(); setPreviousSession(null); }}
                                    className="text-xs text-amber-800 underline mt-1">
                                    Descartar e começar limpo
                                </button>
                            </div>
                        </div>
                    )}

                    <div
                        onDragOver={(e) => { e.preventDefault(); setDragHover(true); }}
                        onDragLeave={() => setDragHover(false)}
                        onDrop={handleDrop}
                        className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors
                            ${dragHover ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50'}`}>
                        <FolderIcon className="mx-auto h-10 w-10 text-gray-400 mb-2" />
                        <p className="text-sm text-gray-700 font-medium">
                            Arraste uma pasta inteira ou arquivos aqui
                        </p>
                        <p className="text-xs text-gray-500 mt-1 mb-3">
                            JPG, JPEG, PNG ou WebP — máx. 5MB por arquivo, {MAX_FILES_PER_SESSION.toLocaleString('pt-BR')} arquivos por sessão
                        </p>
                        <div className="flex flex-col sm:flex-row gap-2 justify-center">
                            <input ref={dirInputRef} type="file"
                                webkitdirectory=""
                                directory=""
                                multiple
                                onChange={handleFileInputChange}
                                className="hidden" id="bulk-dir-input" />
                            <label htmlFor="bulk-dir-input"
                                className={`${getButtonClasses({ variant: 'outline' })} cursor-pointer`}>
                                <FolderIcon className="w-4 h-4 mr-2" />
                                Selecionar pasta
                            </label>
                            <input ref={fileInputRef} type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                multiple
                                onChange={handleFileInputChange}
                                className="hidden" id="bulk-files-input" />
                            <label htmlFor="bulk-files-input"
                                className={`${getButtonClasses({ variant: 'outline' })} cursor-pointer`}>
                                <ArrowUpTrayIcon className="w-4 h-4 mr-2" />
                                Selecionar arquivos
                            </label>
                        </div>
                        {files.length > 0 && (
                            <p className="text-sm text-indigo-700 font-medium mt-3">
                                {files.length.toLocaleString('pt-BR')} arquivo(s) selecionado(s)
                            </p>
                        )}
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 space-y-1">
                        <p className="font-medium">Como funciona</p>
                        <p>• O nome do arquivo (sem extensão) deve ser igual à <strong>referência</strong> do produto.</p>
                        <p>• Imagens são redimensionadas pra no máx. 1200×1200 e otimizadas automaticamente.</p>
                        <p>• Pra carga inicial com 70k+ imagens, use o comando artisan no servidor:
                            <code className="block bg-blue-100 px-2 py-1 rounded mt-1 font-mono text-[11px]">
                                php artisan products:import-images PASTA --tenant=ID --skip-existing
                            </code>
                        </p>
                    </div>
                </>
            )}

            {/* ===== PREVIEW ===== */}
            {phase === 'preview' && preview && (
                <>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <StandardModal.MiniField label="Sem imagem (novos)" value={preview.counts.matched.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Com imagem (conflito)" value={preview.counts.conflicts.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Não encontrados" value={preview.counts.not_found.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Inválidos" value={preview.counts.invalid.toLocaleString('pt-BR')} />
                    </div>

                    {preview.counts.conflicts > 0 && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-2">
                            <div className="flex items-start gap-2">
                                <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-amber-900">
                                        {preview.counts.conflicts.toLocaleString('pt-BR')} produto(s) já têm imagem
                                    </p>
                                    <p className="text-xs text-amber-700 mt-0.5">
                                        Como tratar?
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 ml-7">
                                <label className="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" name="conflict" value="skip"
                                        checked={conflictDecision === 'skip'}
                                        onChange={(e) => setConflictDecision(e.target.value)} />
                                    <span>Ignorar todos (manter imagem atual)</span>
                                </label>
                                <label className="flex items-center gap-2 text-sm cursor-pointer">
                                    <input type="radio" name="conflict" value="replace"
                                        checked={conflictDecision === 'replace'}
                                        onChange={(e) => setConflictDecision(e.target.value)} />
                                    <span>Substituir todos</span>
                                </label>
                            </div>
                        </div>
                    )}

                    {preview.counts.not_found > 0 && (
                        <details className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <summary className="text-sm font-medium text-gray-800 cursor-pointer">
                                Ver não encontrados ({preview.counts.not_found.toLocaleString('pt-BR')})
                            </summary>
                            <div className="mt-2 max-h-32 overflow-y-auto text-xs text-gray-600 font-mono space-y-0.5">
                                {preview.not_found.slice(0, 200).map((x, i) => (
                                    <div key={i}>{x.filename} <span className="text-gray-400">→ ref: {x.reference}</span></div>
                                ))}
                                {preview.not_found.length > 200 && (
                                    <div className="text-gray-400 italic">... +{(preview.not_found.length - 200).toLocaleString('pt-BR')} mais</div>
                                )}
                            </div>
                        </details>
                    )}

                    {preview.counts.invalid > 0 && (
                        <details className="bg-gray-50 border border-gray-200 rounded-lg p-3">
                            <summary className="text-sm font-medium text-gray-800 cursor-pointer">
                                Ver inválidos ({preview.counts.invalid.toLocaleString('pt-BR')})
                            </summary>
                            <div className="mt-2 max-h-32 overflow-y-auto text-xs text-gray-600 font-mono space-y-0.5">
                                {preview.invalid.slice(0, 200).map((x, i) => (
                                    <div key={i}>{x.filename} — {x.reason}</div>
                                ))}
                            </div>
                        </details>
                    )}
                </>
            )}

            {/* ===== UPLOADING ===== */}
            {phase === 'uploading' && (
                <>
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="font-medium text-gray-800">
                                {progress.current.toLocaleString('pt-BR')} / {progress.total.toLocaleString('pt-BR')}
                            </span>
                            <span className="text-gray-500">
                                {progress.total > 0 ? Math.round((progress.current / progress.total) * 100) : 0}%
                                {eta != null && ` · ETA ${formatDuration(eta)}`}
                            </span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div className="bg-indigo-600 h-full transition-all duration-300"
                                style={{ width: `${progress.total > 0 ? (progress.current / progress.total) * 100 : 0}%` }} />
                        </div>
                        {paused && (
                            <p className="text-xs text-amber-700 font-medium flex items-center gap-1">
                                <PauseIcon className="h-3.5 w-3.5" /> Pausado
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-3 sm:grid-cols-6 gap-2 text-center">
                        <CountTile label="Enviadas" value={summary.uploaded} color="green" />
                        <CountTile label="Substituídas" value={summary.replaced} color="blue" />
                        <CountTile label="Ignoradas" value={summary.skipped} color="gray" />
                        <CountTile label="Não enc." value={summary.not_found} color="gray" />
                        <CountTile label="Inválidos" value={summary.invalid} color="amber" />
                        <CountTile label="Erros" value={summary.errors} color="red" />
                    </div>

                    <div className="bg-gray-50 border border-gray-200 rounded-lg p-2 max-h-48 overflow-y-auto text-xs font-mono space-y-0.5">
                        {results.slice(-30).reverse().map((r, i) => (
                            <ResultLine key={`${r.filename}-${i}`} result={r} />
                        ))}
                    </div>

                    <p className="text-xs text-gray-500">
                        Mantenha esta aba aberta. Se fechar acidentalmente, o progresso fica salvo —
                        re-abra o modal e selecione a mesma pasta pra continuar.
                    </p>
                </>
            )}

            {/* ===== DONE ===== */}
            {phase === 'done' && (
                <>
                    <div className="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-3">
                        <CheckCircleIcon className="h-6 w-6 text-green-600 flex-shrink-0" />
                        <div>
                            <p className="text-sm font-medium text-green-900">Importação concluída</p>
                            <p className="text-xs text-green-700">
                                {progress.total.toLocaleString('pt-BR')} arquivos processados em {formatDuration(Date.now() - startTimeRef.current)}.
                            </p>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <StandardModal.MiniField label="Enviadas" value={summary.uploaded.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Substituídas" value={summary.replaced.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Ignoradas" value={summary.skipped.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Não encontradas" value={summary.not_found.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Inválidas" value={summary.invalid.toLocaleString('pt-BR')} />
                        <StandardModal.MiniField label="Erros" value={summary.errors.toLocaleString('pt-BR')} />
                    </div>

                    {(summary.not_found + summary.invalid + summary.errors) > 0 && (
                        <Button variant="outline" icon={ArrowDownTrayIcon}
                            onClick={() => downloadCsv(
                                results.filter(r => ['not_found', 'invalid', 'error'].includes(r.status))
                                    .map(r => ({
                                        arquivo: r.filename,
                                        referencia: r.reference ?? '',
                                        status: r.status,
                                        mensagem: r.message ?? '',
                                    })),
                                `imagens-erros-${new Date().toISOString().slice(0, 10)}.csv`,
                            )}>
                            Baixar CSV de ocorrências
                        </Button>
                    )}
                </>
            )}
        </StandardModal>
    );
}

function CountTile({ label, value, color }) {
    const palette = {
        green: 'bg-green-50 text-green-800 border-green-200',
        blue: 'bg-blue-50 text-blue-800 border-blue-200',
        gray: 'bg-gray-50 text-gray-700 border-gray-200',
        amber: 'bg-amber-50 text-amber-800 border-amber-200',
        red: 'bg-red-50 text-red-800 border-red-200',
    }[color] ?? 'bg-gray-50 text-gray-700 border-gray-200';
    return (
        <div className={`border rounded p-1.5 ${palette}`}>
            <div className="text-base font-bold tabular-nums">{value.toLocaleString('pt-BR')}</div>
            <div className="text-[10px] uppercase tracking-wide">{label}</div>
        </div>
    );
}

function ResultLine({ result }) {
    const icon = {
        uploaded: <CheckCircleIcon className="h-3.5 w-3.5 text-green-600 flex-shrink-0" />,
        replaced: <CheckCircleIcon className="h-3.5 w-3.5 text-blue-600 flex-shrink-0" />,
        skipped: <span className="h-3.5 w-3.5 text-gray-400 flex-shrink-0">·</span>,
        not_found: <XCircleIcon className="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />,
        invalid: <ExclamationTriangleIcon className="h-3.5 w-3.5 text-amber-500 flex-shrink-0" />,
        error: <XCircleIcon className="h-3.5 w-3.5 text-red-500 flex-shrink-0" />,
    }[result.status];
    return (
        <div className="flex items-center gap-1.5 text-gray-700">
            {icon}
            <span className="truncate">{result.filename}</span>
            {result.message && <span className="text-gray-400 truncate">— {result.message}</span>}
        </div>
    );
}
