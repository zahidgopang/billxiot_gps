<script>
    window.Pusher = Pusher;

    (function () {
        // Laravel Reverb (Pusher-protocol compatible). Driven by config/broadcasting.php -> reverb.
        const key = @json((string) config('broadcasting.connections.reverb.key'));
        const host = @json((string) config('broadcasting.connections.reverb.options.host'));
        const port = @json((int) config('broadcasting.connections.reverb.options.port', 443));
        const scheme = @json((string) config('broadcasting.connections.reverb.options.scheme', 'https'));
        const forceTLS = scheme === 'https';

        if (!key || !host) {
            console.info('[realtime] Reverb key/host missing — disabling Echo (polling only).');
            window.Echo = null;
            return;
        }

        try {
            window.Echo = new Echo({
                broadcaster: "pusher",
                key: key,
                wsHost: host,
                wsPort: port,
                wssPort: port,
                forceTLS: forceTLS,
                enabledTransports: ["ws", "wss"],
                disableStats: true,
                cluster: "",
                authEndpoint: @json(url('/broadcasting/auth')),
                auth: {
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                }
            });
        } catch (e) {
            console.warn('[realtime] Echo init failed — polling only', e);
            window.Echo = null;
        }
    })();
</script>
