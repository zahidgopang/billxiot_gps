<script>
    (function () {
        const echoEnabled = @json((bool) config('broadcasting.echo_enabled', true));
        const key = @json((string) config('broadcasting.connections.reverb.key'));
        const configuredHost = @json((string) config('broadcasting.connections.reverb.options.host'));
        const configuredPort = @json((int) config('broadcasting.connections.reverb.options.port', 443));
        const configuredScheme = @json((string) config('broadcasting.connections.reverb.options.scheme', 'https'));
        const isLocal = @json(config('app.env') === 'local');
        const debugRealtime = new URLSearchParams(window.location.search).has('realtime_debug')
            || window.localStorage?.getItem('realtime_debug') === '1';

        function normalizeHost(raw) {
            if (!raw) return '';
            let host = String(raw).trim();
            if (host.includes('://')) {
                try {
                    host = new URL(host).hostname;
                } catch (_) {
                    host = host.replace(/^https?:\/\//i, '');
                }
            }
            host = host.replace(/^https?:\/\//i, '').replace(/[/:].*$/, '').replace(/\/+$/, '');
            return host;
        }

        let host = normalizeHost(configuredHost) || window.location.hostname || 'localhost';
        let port = configuredPort;
        let scheme = configuredScheme;

        if (window.location.hostname) {
            host = window.location.hostname;
        }

        if (window.location.protocol === 'https:') {
            scheme = 'https';
            if (port === 80) {
                port = 443;
            }
        } else if (isLocal && scheme === 'https') {
            scheme = 'http';
            if (port === 443) {
                port = 8080;
            }
        }

        const forceTLS = scheme === 'https';
        const transports = forceTLS ? ['wss'] : ['ws'];
        const wsScheme = forceTLS ? 'wss' : 'ws';
        const portSuffix = (forceTLS && port === 443) || (!forceTLS && port === 80) ? '' : `:${port}`;
        const expectedWsUrl = key ? `${wsScheme}://${host}${portSuffix}/app/${key}?protocol=7&client=js` : '';

        window.__reverbDebug = () => ({
            state: window.Echo?.connector?.pusher?.connection?.state ?? null,
            echoLoaded: typeof window.Echo !== 'undefined' && window.Echo !== null,
            pusherLoaded: typeof Pusher !== 'undefined',
            echoClassLoaded: typeof Echo !== 'undefined',
            expectedWsUrl,
            echoConfig: { key: key || null, host, port, scheme, forceTLS, transports },
            pusherConfig: window.Echo?.connector?.pusher?.config ?? null,
            socketId: window.Echo?.connector?.pusher?.connection?.socket_id ?? null,
            lastInitError: window.__reverbInitError ?? null,
        });

        if (!echoEnabled) {
            console.warn('[realtime] Echo disabled (REVERB_CLIENT_ENABLED=false). Maps use polling.');
            window.Echo = null;
            return;
        }

        if (!key) {
            console.error('[realtime] REVERB_APP_KEY missing — Echo not started.');
            window.Echo = null;
            return;
        }

        if (!host) {
            console.error('[realtime] REVERB_HOST missing — Echo not started.');
            window.Echo = null;
            return;
        }

        if (typeof Pusher === 'undefined') {
            window.__reverbInitError = 'pusher.min.js not loaded';
            console.error('[realtime] pusher.min.js not loaded. Check /js/vendor/pusher.min.js');
            window.Echo = null;
            return;
        }

        if (typeof Echo === 'undefined') {
            window.__reverbInitError = 'echo.iife.js not loaded';
            console.error('[realtime] echo.iife.js not loaded. Check /js/vendor/echo.iife.js');
            window.Echo = null;
            return;
        }

        window.Pusher = Pusher;

        try {
            if (debugRealtime) {
                window.Pusher.logToConsole = true;
                window.Pusher.log = (message) => console.debug('[pusher-js]', message);
            }

            const echo = new Echo({
                broadcaster: 'pusher',
                key: key,
                cluster: 'mt1',
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: forceTLS,
                encrypted: forceTLS,
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                authEndpoint: @json(url('/broadcasting/auth')),
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                },
            });

            window.Echo = echo;
            window.__reverbInitError = null;

            if (debugRealtime) {
                console.info('[realtime] Echo/Reverb config', {
                    key,
                    host,
                    port,
                    scheme,
                    forceTLS,
                    transports,
                    expectedWsUrl,
                    pusherConfig: echo.connector?.pusher?.config,
                });
            }

            const connection = echo.connector?.pusher?.connection;
            if (connection) {
                let warned = false;
                const warnOnce = (detail) => {
                    if (warned) return;
                    warned = true;
                    console.warn(
                        '[realtime] WebSocket unavailable (' + (detail || 'connection failed') + '). '
                        + 'Maps still update via polling. Run: reverb-start.bat',
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
                connection.bind('failed', () => {
                    warnOnce('failed');
                    console.error('[realtime] WebSocket failed. Run window.__reverbDebug()');
                });
            }
        } catch (e) {
            window.__reverbInitError = e?.message || String(e);
            console.error('[realtime] Echo init failed — polling only', e);
            window.Echo = null;
        }
    })();
</script>
