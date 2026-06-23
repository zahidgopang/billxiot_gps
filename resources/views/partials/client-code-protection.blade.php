@if(client_hardening_enabled())
    <script>window.__CLIENT_HARDENING__ = true;</script>
    <script src="{{ protected_js('client-protection.js') }}" defer></script>
@endif
