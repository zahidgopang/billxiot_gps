@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-forms.css') }}">
    <link rel="stylesheet" href="{{ asset('css/permission-management.css') }}">
    <link rel="stylesheet" href="{{ asset('css/country-code-selector.css') }}">
    <style>
        body.user-panel.apple-hig .sub-account-form .sa-form-section {
            background: #fff;
            border: 1px solid var(--apple-border);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            overflow: visible;
        }

        body.user-panel.apple-hig .sub-account-form .sa-form-section__title {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 16px;
            color: var(--apple-text);
        }

        body.user-panel.apple-hig .sub-account-form .sa-form-section__hint {
            font-size: 13px;
            color: var(--apple-muted);
            margin: -8px 0 16px;
        }

        body.user-panel.apple-hig .sub-account-form .sa-field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        body.user-panel.apple-hig .sub-account-form .sa-field--full {
            grid-column: 1 / -1;
        }

        body.user-panel.apple-hig .sub-account-form .sa-field .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--apple-text);
            margin-bottom: 0.35rem;
        }

        body.user-panel.apple-hig .sub-account-form .form-control,
        body.user-panel.apple-hig .sub-account-form .form-select {
            display: block;
            width: 100%;
            min-height: 42px;
            border-radius: 12px;
            border: 1px solid var(--apple-border);
            background: #fff;
            color: var(--apple-text);
            padding: 0.5rem 0.75rem;
        }

        body.user-panel.apple-hig .sub-account-form .form-control:focus,
        body.user-panel.apple-hig .sub-account-form .form-select:focus {
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.12);
            outline: none;
        }

        body.user-panel.apple-hig .sub-account-form .sa-device-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        body.user-panel.apple-hig .sub-account-form .sa-device-option {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 0;
            padding: 12px;
            border: 1px solid var(--apple-border);
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
        }

        body.user-panel.apple-hig .sub-account-form .sa-device-option:hover {
            border-color: #c7c7cc;
            background: var(--apple-hover);
        }

        body.user-panel.apple-hig .sub-account-form .sa-device-option__body {
            min-width: 0;
        }

        body.user-panel.apple-hig .sub-account-form .perm-tabs {
            border-bottom-color: var(--apple-border);
        }

        body.user-panel.apple-hig .sub-account-form .tab-content .form-check.border {
            border-color: var(--apple-border) !important;
            border-radius: 12px;
            background: #fff;
        }

        body.user-panel.apple-hig .sub-account-form .phone-input-container {
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }

        body.user-panel.apple-hig .sub-account-form .admin-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--apple-text);
            margin-bottom: 0.35rem;
        }

        @media (max-width: 767.98px) {
            body.user-panel.apple-hig .sub-account-form .sa-field-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush
