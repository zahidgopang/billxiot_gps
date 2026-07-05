<script>
window.SESSION_EXPIRED_CONFIG = {
    loginUrl: @json(route('login')),
    message: @json(__('app.auth.please_login_again')),
    storageKey: 'login_flash',
};
</script>
<script src="{{ asset('js/session-expired-handler.js') }}?v={{ filemtime(public_path('js/session-expired-handler.js')) }}"></script>
