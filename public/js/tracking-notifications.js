(function (global) {
    'use strict';
    const cfg = global.TRACKING_NOTIFICATIONS_CONFIG;
    if (!cfg) return;

    const i18n = cfg.i18n || {};
    const form = document.getElementById('gtNotifForm');
    const body = document.getElementById('gtNotifBody');
    const listEl = document.getElementById('gtNotifList');
    const emptyEl = document.getElementById('gtNotifEmpty');
    const pagerEl = document.getElementById('gtNotifPager');
    const pageLabel = document.getElementById('gtNotifPageLabel');
    const unreadBadge = document.getElementById('gtNotifUnreadBadge');
    const channels = ['web', 'push', 'email', 'whatsapp'];

    const state = {
        tab: 'inbox',
        page: 1,
        perPage: 25,
        total: 0,
        hasMore: false,
        unreadCount: 0,
        items: [],
        loading: false,
    };

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function notify(icon, title) {
        if (global.Swal) {
            global.Swal.fire({ icon, title, timer: icon === 'success' ? 1600 : undefined, showConfirmButton: icon !== 'success' });
        } else {
            global.alert(title);
        }
    }

    function fmtDateInput(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function initDates() {
        const to = document.getElementById('gtNotifTo');
        const from = document.getElementById('gtNotifFrom');
        if (!to || !from) return;
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - 90);
        if (!to.value) to.value = fmtDateInput(end);
        if (!from.value) from.value = fmtDateInput(start);
    }

    function setTab(tab) {
        state.tab = tab === 'settings' ? 'settings' : 'inbox';
        document.querySelectorAll('[data-gt-notif-tab]').forEach((btn) => {
            const active = btn.dataset.gtNotifTab === state.tab;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        const inbox = document.getElementById('gtNotifInboxPanel');
        const settings = document.getElementById('gtNotifSettingsPanel');
        if (inbox) inbox.hidden = state.tab !== 'inbox';
        if (settings) settings.hidden = state.tab !== 'settings';
        if (state.tab === 'settings') loadPrefs();
        else loadInbox();
    }

    function row(p) {
        const cells = channels.map((ch) => {
            const checked = p[ch] ? 'checked' : '';
            const label = escHtml(i18n[ch] || ch);
            return `<td class="gt-notif-ch"><input type="checkbox" class="form-check-input" data-type="${escHtml(p.type)}" data-ch="${ch}" ${checked} aria-label="${label}"></td>`;
        }).join('');
        return `<tr>
            <td>${escHtml(p.label || p.type)}</td>
            ${cells}
        </tr>`;
    }

    async function loadPrefs() {
        if (!body) return;
        let prefs = [];
        try {
            const res = await fetch(cfg.jsonUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await res.json();
            prefs = data.preferences || [];
        } catch (e) { prefs = []; }

        if (!prefs.length) {
            body.innerHTML = `<tr><td colspan="5" class="gt-notif-empty">${escHtml(i18n.noTypes || 'No notification types')}</td></tr>`;
            return;
        }

        body.innerHTML = prefs.map(row).join('');
    }

    function renderInbox() {
        if (!listEl) return;
        if (!state.items.length) {
            listEl.innerHTML = '';
            if (emptyEl) emptyEl.hidden = false;
            if (pagerEl) pagerEl.hidden = true;
            return;
        }
        if (emptyEl) emptyEl.hidden = true;
        listEl.innerHTML = state.items.map((item) => {
            const unread = !item.read;
            const time = item.time_display || item.time || '';
            const vehicle = item.device_name || '';
            const title = item.title || item.type || 'Alert';
            const message = item.message || item.body || '';
            return `<li class="gt-notif-item${unread ? ' is-unread' : ''}" data-id="${escHtml(item.id)}" role="button" tabindex="0">
                <span class="gt-notif-dot" aria-hidden="true"></span>
                <div>
                    <p class="gt-notif-title">${escHtml(title)}</p>
                    <div class="gt-notif-meta">${escHtml([vehicle, time].filter(Boolean).join(' · '))}</div>
                    ${message ? `<p class="gt-notif-msg">${escHtml(message)}</p>` : ''}
                </div>
            </li>`;
        }).join('');

        const pages = Math.max(1, Math.ceil(state.total / state.perPage));
        if (pagerEl) {
            pagerEl.hidden = pages <= 1;
            if (pageLabel) {
                pageLabel.textContent = (i18n.pageOf || ':page / :pages')
                    .replace(':page', String(state.page))
                    .replace(':pages', String(pages));
            }
            const prev = document.getElementById('gtNotifPrev');
            const next = document.getElementById('gtNotifNext');
            if (prev) prev.disabled = state.page <= 1;
            if (next) next.disabled = !state.hasMore && state.page >= pages;
        }

        if (unreadBadge) {
            unreadBadge.hidden = !(state.unreadCount > 0);
            unreadBadge.textContent = String(state.unreadCount);
        }
    }

    async function loadInbox() {
        if (!cfg.inboxUrl || state.loading) return;
        state.loading = true;
        const filter = document.getElementById('gtNotifFilter')?.value || 'all';
        const from = document.getElementById('gtNotifFrom')?.value || '';
        const to = document.getElementById('gtNotifTo')?.value || '';
        const params = new URLSearchParams({
            page: String(state.page),
            per_page: String(state.perPage),
        });
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        if (filter === 'unread') params.set('unread_only', '1');

        try {
            const res = await fetch(`${cfg.inboxUrl}?${params}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error('failed');
            state.items = data.notifications || [];
            state.total = data.total || 0;
            state.hasMore = !!data.has_more;
            state.page = data.page || state.page;
            state.unreadCount = data.unread_count || 0;
            renderInbox();
        } catch (err) {
            state.items = [];
            state.total = 0;
            renderInbox();
            notify('error', i18n.loadFailed || 'Failed to load notifications');
        } finally {
            state.loading = false;
        }
    }

    async function markRead(ids) {
        if (!cfg.markReadUrl || !ids.length) return;
        const res = await fetch(cfg.markReadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ alert_ids: ids }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) throw new Error('failed');
    }

    document.querySelectorAll('[data-gt-notif-tab]').forEach((btn) => {
        btn.addEventListener('click', () => setTab(btn.dataset.gtNotifTab));
    });

    document.getElementById('gtNotifReload')?.addEventListener('click', () => {
        state.page = 1;
        loadInbox();
    });
    document.getElementById('gtNotifPrev')?.addEventListener('click', () => {
        if (state.page <= 1) return;
        state.page -= 1;
        loadInbox();
    });
    document.getElementById('gtNotifNext')?.addEventListener('click', () => {
        if (!state.hasMore && state.page >= Math.ceil(state.total / state.perPage)) return;
        state.page += 1;
        loadInbox();
    });

    document.getElementById('gtNotifMarkAll')?.addEventListener('click', async () => {
        const ids = state.items.filter((i) => !i.read).map((i) => i.id);
        if (!ids.length) return;
        try {
            await markRead(ids);
            notify('success', i18n.markedRead || 'Marked as read');
            await loadInbox();
        } catch (_) {
            notify('error', i18n.failed || 'Failed');
        }
    });

    listEl?.addEventListener('click', async (ev) => {
        const item = ev.target.closest('.gt-notif-item');
        if (!item) return;
        const id = parseInt(item.dataset.id, 10);
        if (!id) return;
        const row = state.items.find((i) => Number(i.id) === id);
        if (!row || row.read) return;
        try {
            await markRead([id]);
            row.read = true;
            if (state.unreadCount > 0) state.unreadCount -= 1;
            renderInbox();
        } catch (_) { /* ignore */ }
    });

    form?.querySelectorAll('[data-toggle-col]').forEach((link) => {
        link.addEventListener('click', () => {
            const ch = link.dataset.toggleCol;
            const boxes = [...body.querySelectorAll(`[data-ch="${ch}"]`)];
            const allOn = boxes.every((b) => b.checked);
            boxes.forEach((b) => { b.checked = !allOn; });
        });
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const types = new Set([...body.querySelectorAll('[data-type]')].map((el) => el.dataset.type));
        const preferences = [...types].map((type) => {
            const pref = { type };
            channels.forEach((ch) => {
                pref[ch] = !!body.querySelector(`[data-type="${CSS.escape(type)}"][data-ch="${ch}"]`)?.checked;
            });
            return pref;
        });

        const btn = form.querySelector('button[type="submit"]');
        btn?.setAttribute('disabled', 'disabled');
        try {
            const res = await fetch(cfg.updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                body: JSON.stringify({ preferences }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error('failed');
            notify('success', i18n.saved || 'Saved');
        } catch (err) {
            notify('error', i18n.failed || 'Failed');
        } finally {
            btn?.removeAttribute('disabled');
        }
    });

    initDates();
    setTab('inbox');
})(window);
