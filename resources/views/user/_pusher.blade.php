<script>
    window.Pusher = Pusher;

    (function () {
        const echoEnabled = @json((bool) config('broadcasting.echo_enabled', true));
        const key = @json((string) config('broadcasting.connections.reverb.key'));
        const host = @json((string) config('broadcasting.connections.reverb.options.host'));
        const port = @json((int) config('broadcasting.connections.reverb.options.port', 443));
        const scheme = @json((string) config('broadcasting.connections.reverb.options.scheme', 'https'));
        const forceTLS = scheme === 'https';
        const transports = forceTLS ? ['wss'] : ['ws'];

        if (!echoEnabled || !key || !host) {
            console.info('[realtime] WebSocket disabled — map updates use polling.');
            window.Echo = null;
            return;
        }

        try {
            const echo = new Echo({
                broadcaster: 'pusher',
                key: key,
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: forceTLS,
                encrypted: forceTLS,
                enabledTransports: transports,
                disableStats: true,
                cluster: '',
                authEndpoint: @json(url('/broadcasting/auth')),
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                },
            });

            window.Echo = echo;

            const connection = echo.connector?.pusher?.connection;
            if (connection) {
                let warned = false;
                const warnOnce = (detail) => {
                    if (warned) return;
                    warned = true;
                    console.warn(
                        '[realtime] WebSocket unavailable (' + (detail || 'connection failed') + '). '
                        + 'Maps still update via polling. Start Reverb: php artisan reverb:start',
                    );
                };
                connection.bind('error', (err) => warnOnce(err?.error?.data?.message || err?.type));
                connection.bind('failed', () => warnOnce('failed'));
            }
        } catch (e) {
            console.warn('[realtime] Echo init failed — polling only', e);
            window.Echo = null;
        }
    })();
</script>
