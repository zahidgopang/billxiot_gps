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
        const debugRealtime = new URLSearchParams(window.location.search).has('realtime_debug')
            || window.localStorage?.getItem('realtime_debug') === '1';

        if (!echoEnabled || !key || !host) {
            console.info('[realtime] WebSocket disabled — map updates use polling.');
            window.Echo = null;
            return;
        }

        try {
            if (debugRealtime && window.Pusher) {
                window.Pusher.logToConsole = true;
                window.Pusher.log = (message) => console.debug('[pusher-js]', message);
            }

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
                // Pusher JS may prefix the WebSocket URL with httpPath. Reverb
                // expects /app/{key}, not /pusher/app/{key}.
                httpPath: '',
                authEndpoint: @json(url('/broadcasting/auth')),
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                },
            });

            window.Echo = echo;

            const connection = echo.connector?.pusher?.connection;
            const pusherConfig = echo.connector?.pusher?.config;
            if (debugRealtime) {
                const wsScheme = forceTLS ? 'wss' : 'ws';
                const expectedWsUrl = `${wsScheme}://${host}:${port}/app/${key}?protocol=7&client=js`;
                console.info('[realtime] Echo/Reverb config', {
                    key,
                    host,
                    port,
                    scheme,
                    forceTLS,
                    transports,
                    expectedWsUrl,
                    pusherConfig,
                    note: 'httpPath must be empty for Reverb so Pusher JS connects to /app/{key}.',
                });
                window.__reverbDebug = () => ({
                    state: connection?.state,
                    expectedWsUrl,
                    echoConfig: { key, host, port, scheme, forceTLS, transports },
                    pusherConfig: echo.connector?.pusher?.config,
                });
            }
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
                if (debugRealtime) {
                    const logEvent = (event) => (payload) => console.warn('[realtime]', event, payload || '');
                    connection.bind('state_change', (states) => console.info('[realtime] state_change', states));
                    connection.bind('connecting', logEvent('connecting'));
                    connection.bind('connected', logEvent('connected'));
                    connection.bind('unavailable', logEvent('unavailable'));
                    connection.bind('disconnected', logEvent('disconnected'));
                    connection.bind('error', logEvent('error'));
                    connection.bind('failed', logEvent('failed'));
                }
                connection.bind('error', (err) => warnOnce(err?.error?.data?.message || err?.type));
                connection.bind('failed', () => warnOnce('failed'));
            }
        } catch (e) {
            console.warn('[realtime] Echo init failed — polling only', e);
            window.Echo = null;
        }
    })();
</script>
