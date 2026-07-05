<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.auth.please_login_again') }}</title>
    <meta http-equiv="refresh" content="0;url={{ route('login') }}">
    <script>
        try {
            sessionStorage.setItem('login_flash', @json(__('app.auth.please_login_again')));
        } catch (e) {}
        window.location.replace(@json(route('login')));
    </script>
</head>
<body></body>
</html>
