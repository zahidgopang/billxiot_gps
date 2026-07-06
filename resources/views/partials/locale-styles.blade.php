<style>
    .lang-toggle {
        display: inline-flex;
        align-items: center;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: rgba(255, 255, 255, 0.06);
    }
    .lang-toggle__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 34px;
        padding: 0 10px;
        font-size: 0.75rem;
        font-weight: 700;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.75);
        transition: background 0.2s, color 0.2s;
    }
    .lang-toggle__btn:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.1);
    }
    .lang-toggle__btn--active {
        background: rgba(25, 118, 210, 0.45);
        color: #fff;
        pointer-events: none;
        cursor: default;
    }
    .lang-toggle__btn + .lang-toggle__btn {
        border-inline-start: 1px solid rgba(255, 255, 255, 0.12);
    }
    .admin-navbar .lang-toggle {
        border-color: var(--admin-border, #e2e8f0);
        background: var(--admin-bg, #f8fafc);
    }
    .admin-navbar .lang-toggle__btn {
        color: var(--admin-text-light, #64748b);
    }
    .admin-navbar .lang-toggle__btn--active {
        background: var(--admin-primary, #1976D2);
        color: #fff;
    }

    /* Apple HIG user panel — light top bar */
    body.user-panel.apple-hig .navbar .lang-toggle {
        border: 1px solid #C7C7CC;
        background: #FFFFFF;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    }
    body.user-panel.apple-hig .navbar .lang-toggle__btn {
        color: #3A3A3C;
        min-width: 42px;
        height: 36px;
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.03em;
    }
    body.user-panel.apple-hig .navbar .lang-toggle__btn:hover {
        color: #1D1D1F;
        background: #F2F2F7;
    }
    body.user-panel.apple-hig .navbar .lang-toggle__btn--active {
        background: #007AFF;
        color: #FFFFFF;
    }
    body.user-panel.apple-hig .navbar .lang-toggle__btn + .lang-toggle__btn {
        border-inline-start: 1px solid #C7C7CC;
    }
    body.user-panel.apple-hig .navbar .lang-toggle__btn--ar {
        font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;
        font-weight: 700;
    }
</style>
