<script>
    window.Pusher = Pusher;

    (function () {
        const key = @json((string) config('broadcasting.connections.pusher.key'));
        const cluster = @json((string) (config('broadcasting.connections.pusher.options.cluster') ?: 'mt1'));

        if (!key) {
            console.info('[realtime] Pusher key missing — disabling Echo (polling only).');
            window.Echo = null;
            return;
        }

        try {
            window.Echo = new Echo({
                broadcaster: "pusher",
                key: key,
                cluster: cluster,
                forceTLS: true,
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
