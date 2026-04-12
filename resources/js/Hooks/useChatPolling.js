import { useState, useEffect, useRef, useCallback } from 'react';

/**
 * Adaptive polling for chat unread counts.
 * - 5s when actively chatting (page focused)
 * - 30s when idle (page focused but no activity)
 * - 60s when tab is hidden
 * Falls back to polling when Echo is not available.
 */
export default function useChatPolling(enabled = true) {
    const [unreadCounts, setUnreadCounts] = useState({ conversations: 0, broadcasts: 0, total: 0 });
    const lastActivityRef = useRef(Date.now());
    const visibleRef = useRef(!document.hidden);

    const fetchCounts = useCallback(() => {
        if (!enabled) return;
        fetch(route('chat.unread-counts'), { headers: { 'Accept': 'application/json' } })
            .then(res => res.ok ? res.json() : null)
            .then(data => { if (data) setUnreadCounts(data); })
            .catch(() => {});
    }, [enabled]);

    // Track visibility
    useEffect(() => {
        const handler = () => { visibleRef.current = !document.hidden; };
        document.addEventListener('visibilitychange', handler);
        return () => document.removeEventListener('visibilitychange', handler);
    }, []);

    // Track activity
    useEffect(() => {
        const handler = () => { lastActivityRef.current = Date.now(); };
        window.addEventListener('mousemove', handler, { passive: true });
        window.addEventListener('keydown', handler, { passive: true });
        return () => {
            window.removeEventListener('mousemove', handler);
            window.removeEventListener('keydown', handler);
        };
    }, []);

    // Adaptive polling
    useEffect(() => {
        if (!enabled) return;

        fetchCounts(); // Initial fetch

        const tick = () => {
            fetchCounts();
            const isHidden = !visibleRef.current;
            const idleMs = Date.now() - lastActivityRef.current;
            const isIdle = idleMs > 120000; // 2 minutes

            const interval = isHidden ? 60000 : isIdle ? 30000 : 5000;
            timeoutId = setTimeout(tick, interval);
        };

        let timeoutId = setTimeout(tick, 5000);
        return () => clearTimeout(timeoutId);
    }, [enabled, fetchCounts]);

    return unreadCounts;
}
