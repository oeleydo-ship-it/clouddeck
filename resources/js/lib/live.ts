import { useEffect } from 'react';
import { router } from '@inertiajs/react';

export function useLiveReload({
    active,
    channels = [],
    events = [],
    only,
    interval = 5000,
}: {
    active: boolean;
    channels?: string[];
    events?: string[];
    only?: string[];
    interval?: number;
}) {
    const channelKey = channels.join(',');
    const eventKey = events.join(',');
    const onlyKey = (only || []).join(',');

    useEffect(() => {
        if (! active) return;

        const reload = () => router.reload({ only });
        const echoes = channels.map((name) => {
            const channel = window.Echo?.private(name);
            events.forEach((event) => channel?.listen(event, reload));
            return channel;
        });
        const timer = setInterval(reload, interval);

        return () => {
            clearInterval(timer);
            echoes.forEach((channel) => events.forEach((event) => channel?.stopListening(event)));
        };
    }, [active, channelKey, eventKey, onlyKey, interval]);
}
