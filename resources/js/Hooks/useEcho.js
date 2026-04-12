import { useEffect, useRef } from 'react';

/**
 * Subscribe to an Echo private channel with automatic cleanup.
 *
 * @param {string} channel - Channel name (e.g., 'conversation.123')
 * @param {string} event - Event name (e.g., '.message.new')
 * @param {Function} callback - Handler function
 * @param {boolean} enabled - Whether to subscribe (default true)
 */
export default function useEcho(channel, event, callback, enabled = true) {
    const callbackRef = useRef(callback);
    callbackRef.current = callback;

    useEffect(() => {
        if (!enabled || !window.Echo || !channel) return;

        const echoChannel = window.Echo.private(channel);
        echoChannel.listen(event, (data) => callbackRef.current(data));

        return () => {
            echoChannel.stopListening(event);
            window.Echo.leave(`private-${channel}`);
        };
    }, [channel, event, enabled]);
}

/**
 * Subscribe to a user's private channel for personal notifications.
 *
 * @param {number} userId
 * @param {string} event
 * @param {Function} callback
 */
export function useUserChannel(userId, event, callback) {
    return useEcho(userId ? `user.${userId}` : null, event, callback, !!userId);
}
