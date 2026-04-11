import { Head } from '@inertiajs/react';
import { useState, useRef, useCallback, useEffect } from 'react';
import { router } from '@inertiajs/react';
import {
    CheckCircleIcon,
    PlayCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';

function getVideoId(url) {
    if (!url) return null;
    const ytMatch = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    return ytMatch ? ytMatch[1] : null;
}

function getEmbedUrl(url) {
    if (!url) return '';

    const ytId = getVideoId(url);
    if (ytId) return `https://www.youtube.com/embed/${ytId}`;

    const vimeoMatch = url.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;

    return url;
}

// Load YouTube IFrame API once
let ytApiLoaded = false;
let ytApiReady = false;
const ytApiCallbacks = [];

function loadYouTubeApi() {
    if (ytApiReady) return Promise.resolve();
    if (ytApiLoaded) return new Promise(resolve => ytApiCallbacks.push(resolve));

    ytApiLoaded = true;
    return new Promise(resolve => {
        ytApiCallbacks.push(resolve);
        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(tag);
        window.onYouTubeIframeAPIReady = () => {
            ytApiReady = true;
            ytApiCallbacks.forEach(cb => cb());
            ytApiCallbacks.length = 0;
        };
    });
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;

export default function WatchContent({ course, content, progress, courseContents }) {
    const [currentProgress, setCurrentProgress] = useState(progress?.progress_percent || 0);
    const [completed, setCompleted] = useState(progress?.status === 'completed');
    const [notification, setNotification] = useState(null);
    const videoRef = useRef(null);
    const saveTimerRef = useRef(null);
    const elapsedRef = useRef(0);
    const hasNativeMedia = ['video', 'audio'].includes(content.content_type) && content.file_url;
    const youtubeVideoId = content.content_type === 'video' && !content.file_url && content.external_url ? getVideoId(content.external_url) : null;
    const isIframeMedia = content.content_type === 'video' && !content.file_url && content.external_url && !youtubeVideoId;
    const isStaticContent = ['text', 'document', 'link'].includes(content.content_type);
    const ytPlayerRef = useRef(null);
    const ytContainerRef = useRef(null);

    // Local state for sidebar — updates when current content is completed
    const [localStatuses, setLocalStatuses] = useState(() => {
        const map = {};
        courseContents?.forEach(c => { map[c.id] = c.status; });
        return map;
    });

    useEffect(() => {
        if (completed) {
            setLocalStatuses(prev => ({ ...prev, [content.id]: 'completed' }));
        }
    }, [completed, content.id]);

    // Find current index and prev/next
    const currentIndex = courseContents?.findIndex(c => c.id === content.id) ?? -1;
    const prevContent = currentIndex > 0 ? courseContents[currentIndex - 1] : null;
    const nextContent = currentIndex < (courseContents?.length ?? 0) - 1 ? courseContents[currentIndex + 1] : null;

    // Save progress to backend
    const saveProgress = useCallback((percent, position) => {
        if (completed) return;
        fetch(route('training-contents.progress', content.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                course_id: course.id,
                progress_percent: Math.min(Math.round(percent), 100),
                position_seconds: position ?? null,
                time_spent: 30,
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.completed) setCompleted(true);
            setCurrentProgress(prev => Math.max(prev, Math.round(percent)));
        })
        .catch(() => {});
    }, [content.id, course.id, completed]);

    // Native video/audio: track via media events
    useEffect(() => {
        if (!hasNativeMedia || !videoRef.current) return;
        const media = videoRef.current;

        if (progress?.last_position_seconds) {
            media.currentTime = progress.last_position_seconds;
        }

        const handleTimeUpdate = () => {
            if (media.duration) {
                setCurrentProgress(Math.round((media.currentTime / media.duration) * 100));
            }
        };

        const handleEnded = () => {
            saveProgress(100, Math.floor(media.duration || 0));
        };

        media.addEventListener('timeupdate', handleTimeUpdate);
        media.addEventListener('ended', handleEnded);

        // Save partial progress every 30s
        const timer = setInterval(() => {
            if (media.duration && !media.paused) {
                const percent = (media.currentTime / media.duration) * 100;
                saveProgress(percent, Math.floor(media.currentTime));
            }
        }, 30000);

        return () => {
            media.removeEventListener('timeupdate', handleTimeUpdate);
            media.removeEventListener('ended', handleEnded);
            clearInterval(timer);
        };
    }, [hasNativeMedia, progress?.last_position_seconds, saveProgress]);

    // YouTube: tracking real via IFrame API
    useEffect(() => {
        if (!youtubeVideoId || completed) return;

        let timer = null;

        loadYouTubeApi().then(() => {
            if (!ytContainerRef.current) return;

            ytPlayerRef.current = new window.YT.Player(ytContainerRef.current, {
                videoId: youtubeVideoId,
                playerVars: { rel: 0, modestbranding: 1 },
                events: {
                    onReady: () => {
                        // Resume from last position
                        if (progress?.last_position_seconds) {
                            ytPlayerRef.current.seekTo(progress.last_position_seconds, true);
                        }
                    },
                },
            });

            // Poll progress every 3 seconds (YT API has no timeupdate event)
            timer = setInterval(() => {
                const player = ytPlayerRef.current;
                if (!player || typeof player.getCurrentTime !== 'function') return;

                const state = player.getPlayerState();
                // 1 = playing
                if (state !== 1) return;

                const current = player.getCurrentTime();
                const duration = player.getDuration();
                if (!duration) return;

                const percent = Math.round((current / duration) * 100);
                setCurrentProgress(percent);

                // Save every ~30s (every 10th poll)
                if (Math.floor(current) % 30 < 3) {
                    saveProgress(percent, Math.floor(current));
                }
            }, 3000);
        });

        return () => {
            clearInterval(timer);
            if (ytPlayerRef.current && typeof ytPlayerRef.current.destroy === 'function') {
                ytPlayerRef.current.destroy();
                ytPlayerRef.current = null;
            }
        };
    }, [youtubeVideoId, completed]);

    // Non-YouTube iframe (Vimeo, etc.): mark as started, rely on manual completion
    useEffect(() => {
        if (!isIframeMedia || completed) return;

        const startTimer = setTimeout(() => {
            if (currentProgress < 5) {
                setCurrentProgress(5);
                saveProgress(5, null);
            }
        }, 5000);

        return () => clearTimeout(startTimer);
    }, [isIframeMedia, completed]);

    // Static content (text, document, link): time-based progress
    useEffect(() => {
        if (!isStaticContent || completed) return;

        const duration = content.duration_seconds || 300; // fallback 5 min
        elapsedRef.current = Math.round((progress?.progress_percent || 0) / 100 * duration);

        const timer = setInterval(() => {
            elapsedRef.current += 10;
            const percent = Math.min((elapsedRef.current / duration) * 100, 100);
            setCurrentProgress(Math.round(percent));

            // Save every 30s
            if (elapsedRef.current % 30 === 0) {
                saveProgress(percent, null);
            }
        }, 10000);

        return () => clearInterval(timer);
    }, [isStaticContent, completed, content.duration_seconds, saveProgress]);

    const showNotification = (message, type = 'success') => {
        setNotification({ message, type });
        setTimeout(() => setNotification(null), 6000);
    };

    const handleMarkComplete = () => {
        fetch(route('training-contents.complete', content.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ course_id: course.id }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.completed) {
                setCompleted(true);
                setCurrentProgress(100);
                showNotification('Conteúdo marcado como concluído!');
            }
            if (data.course_completed) {
                showNotification('Parabéns! Você concluiu o curso! Seu certificado está sendo gerado.', 'celebration');
            }
        });
    };

    const navigateTo = (contentId) => {
        // Save current progress before navigating
        if (videoRef.current && videoRef.current.duration) {
            const percent = (videoRef.current.currentTime / videoRef.current.duration) * 100;
            saveProgress(Math.round(percent), Math.floor(videoRef.current.currentTime));
        }
        router.get(route('training-courses.watch', { trainingCourse: course.id, content: contentId }));
    };

    return (
        <>
            <Head title={content.title} />

            {/* Notificação */}
            {notification && (
                <div className="fixed top-6 left-1/2 -translate-x-1/2 z-50 animate-fade-in">
                    <div className={`flex items-center gap-3 px-6 py-4 rounded-xl shadow-lg ${
                        notification.type === 'celebration'
                            ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white'
                            : 'bg-green-600 text-white'
                    }`}>
                        {notification.type === 'celebration' ? (
                            <span className="text-2xl">&#127942;</span>
                        ) : (
                            <CheckCircleIcon className="w-6 h-6 flex-shrink-0" />
                        )}
                        <span className="text-sm font-medium">{notification.message}</span>
                        <button onClick={() => setNotification(null)} className="ml-2 text-white/70 hover:text-white">
                            <XMarkIcon className="w-4 h-4" />
                        </button>
                    </div>
                </div>
            )}
            <div className="py-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                        {/* Main content area */}
                        <div className="lg:col-span-3">
                            {/* Header */}
                            <div className="mb-4">
                                <button
                                    onClick={() => router.get(route('my-trainings.index'))}
                                    className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 mb-2"
                                >
                                    <ChevronLeftIcon className="w-4 h-4" />
                                    Meus Treinamentos
                                </button>
                                <p className="text-sm text-gray-500">{course.title}</p>
                                <h1 className="text-xl font-bold text-gray-900">{content.title}</h1>
                                {completed && (
                                    <div className="flex items-center gap-1 mt-1 text-green-600">
                                        <CheckCircleIcon className="w-5 h-5" />
                                        <span className="text-sm font-medium">Concluído</span>
                                    </div>
                                )}
                            </div>

                            {/* Player */}
                            <div className="bg-black rounded-lg overflow-hidden">
                                {content.content_type === 'video' && content.file_url && (
                                    <video ref={videoRef} controls className="w-full" src={content.file_url} />
                                )}
                                {youtubeVideoId && (
                                    <div className="aspect-video">
                                        <div ref={ytContainerRef} className="w-full h-full" />
                                    </div>
                                )}
                                {isIframeMedia && (
                                    <div className="aspect-video">
                                        <iframe src={getEmbedUrl(content.external_url)}
                                            className="w-full h-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowFullScreen />
                                    </div>
                                )}
                                {content.content_type === 'audio' && content.file_url && (
                                    <div className="p-8 bg-gray-900 flex items-center justify-center">
                                        <audio ref={videoRef} controls className="w-full" src={content.file_url} />
                                    </div>
                                )}
                                {content.content_type === 'document' && content.file_url && (
                                    <iframe src={content.file_url} className="w-full h-[600px]" />
                                )}
                                {content.content_type === 'text' && content.text_content && (
                                    <div className="bg-white p-6 prose prose-sm max-w-none max-h-[600px] overflow-y-auto"
                                        dangerouslySetInnerHTML={{ __html: content.text_content }} />
                                )}
                                {content.content_type === 'link' && content.external_url && (
                                    <div className="bg-white p-8 text-center">
                                        <a href={content.external_url} target="_blank" rel="noopener"
                                            className="text-indigo-600 hover:underline text-lg">
                                            Abrir recurso externo
                                        </a>
                                    </div>
                                )}
                                {/* Fallback: sem fonte disponível */}
                                {!content.file_url && !content.external_url && !content.text_content && (
                                    <div className="bg-gray-900 p-12 text-center">
                                        <PlayCircleIcon className="w-16 h-16 text-gray-600 mx-auto mb-4" />
                                        <p className="text-gray-400 text-sm">Conteúdo não disponível.</p>
                                        <p className="text-gray-500 text-xs mt-1">Nenhum arquivo ou URL foi configurado para este conteúdo.</p>
                                    </div>
                                )}
                            </div>

                            {/* Progress bar */}
                            <div className="mt-3 bg-white rounded-lg p-3 shadow-sm">
                                <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span>Progresso</span>
                                    <span className={completed ? 'text-green-600 font-medium' : ''}>
                                        {completed ? 'Concluído' : `${currentProgress}%`}
                                    </span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className={`h-2 rounded-full transition-all duration-500 ${completed ? 'bg-green-500' : 'bg-indigo-500'}`}
                                        style={{ width: `${completed ? 100 : currentProgress}%` }}
                                    />
                                </div>
                            </div>

                            {/* Navigation & Actions */}
                            <div className="flex items-center justify-between mt-4">
                                <div>
                                    {prevContent && (
                                        <Button variant="outline" size="sm" icon={ChevronLeftIcon}
                                            onClick={() => navigateTo(prevContent.id)}>
                                            Anterior
                                        </Button>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    {!completed && (
                                        <Button variant="success" size="sm" icon={CheckCircleIcon}
                                            onClick={handleMarkComplete}>
                                            Marcar como concluído
                                        </Button>
                                    )}
                                </div>
                                <div>
                                    {nextContent && (
                                        <Button variant="primary" size="sm"
                                            onClick={() => navigateTo(nextContent.id)}>
                                            Próxima <ChevronRightIcon className="w-4 h-4 ml-1 inline" />
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {content.description && (
                                <div className="mt-6 bg-white rounded-lg p-4 shadow-sm">
                                    <h3 className="text-sm font-medium text-gray-900 mb-2">Descrição</h3>
                                    <p className="text-sm text-gray-600">{content.description}</p>
                                </div>
                            )}
                        </div>

                        {/* Sidebar - Course contents */}
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-lg shadow-sm p-4 sticky top-6">
                                <h3 className="text-sm font-semibold text-gray-900 mb-3">Conteúdo do Curso</h3>
                                <div className="space-y-1">
                                    {courseContents?.map((c, i) => {
                                        const status = localStatuses[c.id] || c.status;
                                        const isCurrent = c.id === content.id;
                                        const isCompleted = status === 'completed';
                                        const progressPct = isCurrent && !isCompleted ? currentProgress : c.progress_percent;

                                        return (
                                            <button
                                                key={c.id}
                                                className={`w-full text-left px-3 py-2 rounded-md text-sm transition-colors ${
                                                    isCurrent
                                                        ? isCompleted
                                                            ? 'bg-green-50 text-green-700 font-medium'
                                                            : 'bg-indigo-50 text-indigo-700 font-medium'
                                                        : isCompleted
                                                            ? 'text-green-700 hover:bg-green-50'
                                                            : 'text-gray-600 hover:bg-gray-50'
                                                }`}
                                                onClick={() => !isCurrent && navigateTo(c.id)}
                                            >
                                                <div className="flex items-center gap-2">
                                                    {isCompleted
                                                        ? <CheckCircleIcon className="w-4 h-4 text-green-500 flex-shrink-0" />
                                                        : isCurrent
                                                            ? <PlayCircleIcon className="w-4 h-4 text-indigo-500 flex-shrink-0" />
                                                            : <span className="w-4 h-4 rounded-full border border-gray-300 flex-shrink-0" />
                                                    }
                                                    <span className="truncate">{i + 1}. {c.title}</span>
                                                </div>
                                                {progressPct > 0 && !isCompleted && (
                                                    <div className="pl-6 mt-1">
                                                        <div className="w-full bg-gray-200 rounded-full h-1">
                                                            <div className="bg-indigo-400 h-1 rounded-full transition-all" style={{ width: `${progressPct}%` }} />
                                                        </div>
                                                    </div>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
