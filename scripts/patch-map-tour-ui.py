#!/usr/bin/env python3
from pathlib import Path

path = Path(__file__).resolve().parents[1] / "public/js/map-tour.js"
text = path.read_text(encoding="utf-8")
dc = "</" + "motion>"  # typo guard
dc = "</" + "motion>"
dc = "</" + "div>"

old_spotlight = '            <div class="map-tour-spotlight" data-tour-spotlight hidden>' + dc
new_spotlight = (
    '            <div class="map-tour-spotlight-wrap" data-tour-spotlight-wrap hidden>\n'
    '                <span class="map-tour-spotlight__label" data-tour-spotlight-label></span>\n'
    '                <div class="map-tour-spotlight" data-tour-spotlight></div>\n'
    '            </div>'
)

if old_spotlight not in text:
    raise SystemExit("spotlight marker not found: " + repr(old_spotlight))
text = text.replace(old_spotlight, new_spotlight, 1)

old_header = (
    '                <motion class="map-tour-card__icon" data-tour-icon><i class="fas fa-map"></i></div>\n'
    '                <h2 class="map-tour-card__title" id="mapTourTitle" data-tour-title>Tour</h2>\n'
    '                <p class="map-tour-card__body" data-tour-body></p>'
)
old_header = old_header.replace("<motion", "<div").replace("</motion>", dc)

new_header = (
    '                <div class="map-tour-card__header">\n'
    '                    <div class="map-tour-card__icon" data-tour-icon aria-hidden="true"><i class="fas fa-map"></i></div>\n'
    '                    <motion class="map-tour-card__titles">\n'
    '                        <span class="map-tour-card__kicker" data-tour-kicker></span>\n'
    '                        <h2 class="map-tour-card__title" id="mapTourTitle" data-tour-title>Tour</h2>\n'
    '                    </div>\n'
    '                </div>\n'
    '                <p class="map-tour-card__body" data-tour-body></p>\n'
    '                <p class="map-tour-card__hint map-tour-card__hint--warn" data-tour-hint hidden></p>'
)
new_header = new_header.replace("<motion", "<motion>").replace("motion class", "motion class")
new_header = new_header.replace("<motion class=\"map-tour-card__titles\">", '<motion class="map-tour-card__titles">')
# fix new_header properly
new_header = (
    '                <div class="map-tour-card__header">\n'
    '                    <div class="map-tour-card__icon" data-tour-icon aria-hidden="true"><i class="fas fa-map"></i></div>\n'
    '                    <div class="map-tour-card__titles">\n'
    '                        <span class="map-tour-card__kicker" data-tour-kicker></span>\n'
    '                        <h2 class="map-tour-card__title" id="mapTourTitle" data-tour-title>Tour</h2>\n'
    '                    </div>\n'
    '                </div>\n'
    '                <p class="map-tour-card__body" data-tour-body></p>\n'
    '                <p class="map-tour-card__hint map-tour-card__hint--warn" data-tour-hint hidden></p>'
)

if old_header not in text:
    raise SystemExit("header marker not found")
text = text.replace(old_header, new_header, 1)

# positionSpotlight
old_ps = """    function positionSpotlight(el, pad) {
        const spot = root.querySelector('[data-tour-spotlight]');
        if (!spot) {
            return;
        }
        if (!el) {
            spot.hidden = true;
            clearHighlight();
            return;
        }
        setHighlightTarget(el);
        const rect = el.getBoundingClientRect();
        const p = pad ?? 10;
        spot.hidden = false;
        spot.style.top = `${Math.max(0, rect.top - p)}px`;
        spot.style.left = `${Math.max(0, rect.left - p)}px`;
        spot.style.width = `${rect.width + p * 2}px`;
        spot.style.height = `${rect.height + p * 2}px`;
    }"""

new_ps = """    function positionSpotlight(el, pad, label) {
        const wrap = root.querySelector('[data-tour-spotlight-wrap]');
        const spot = root.querySelector('[data-tour-spotlight]');
        const labelEl = root.querySelector('[data-tour-spotlight-label]');
        if (!wrap || !spot) {
            return;
        }
        if (!el) {
            wrap.hidden = true;
            if (labelEl) {
                labelEl.hidden = true;
            }
            clearHighlight();
            return;
        }
        setHighlightTarget(el);
        const rect = el.getBoundingClientRect();
        const p = pad ?? 12;
        wrap.hidden = false;
        spot.style.top = `${Math.max(0, rect.top - p)}px`;
        spot.style.left = `${Math.max(0, rect.left - p)}px`;
        spot.style.width = `${rect.width + p * 2}px`;
        spot.style.height = `${rect.height + p * 2}px`;
        if (labelEl) {
            labelEl.textContent = label || '';
            labelEl.hidden = !label;
            labelEl.style.top = `${Math.max(8, rect.top - p - 38)}px`;
            labelEl.style.left = `${Math.max(8, rect.left - p)}px`;
        }
    }"""

if old_ps not in text:
    raise SystemExit("positionSpotlight not found")
text = text.replace(old_ps, new_ps, 1)

old_pc = """    function positionCard(placement, el) {
        const card = root.querySelector('.map-tour-card');
        if (!card) return;
        card.classList.remove('map-tour-card--center');
        if (!el || placement === 'center') {
            card.style.top = '';
            card.style.left = '';
            card.style.right = '';
            card.style.bottom = '';
            card.classList.add('map-tour-card--center');
            return;
        }
        const rect = el.getBoundingClientRect();
        const margin = 16;
        const cardRect = card.getBoundingClientRect();
        let top = rect.bottom + margin;
        let left = rect.left;
        if (placement === 'top') {
            top = rect.top - cardRect.height - margin;
        } else if (placement === 'left') {
            top = rect.top;
            left = rect.left - cardRect.width - margin;
        } else if (placement === 'right') {
            top = rect.top;
            left = rect.right + margin;
        }
        left = Math.min(Math.max(margin, left), window.innerWidth - cardRect.width - margin);
        top = Math.min(Math.max(margin, top), window.innerHeight - cardRect.height - margin);
        card.style.top = `${top}px`;
        card.style.left = `${left}px`;
        card.style.right = 'auto';
        card.style.bottom = 'auto';
    }"""

new_pc = """    function setCardPointer(placement) {
        const card = root.querySelector('.map-tour-card');
        if (!card) return;
        card.classList.remove(
            'map-tour-card--pointer-top',
            'map-tour-card--pointer-bottom',
            'map-tour-card--pointer-left',
            'map-tour-card--pointer-right',
            'map-tour-card--center'
        );
        if (placement === 'center') {
            card.classList.add('map-tour-card--center');
            return;
        }
        card.classList.add('map-tour-card--pointer-' + (placement || 'bottom'));
    }

    function positionCard(placement, el) {
        const card = root.querySelector('.map-tour-card');
        if (!card) return;
        card.style.top = '';
        card.style.left = '';
        card.style.right = '';
        card.style.bottom = '';
        card.style.insetInlineStart = '';
        if (!el || placement === 'center') {
            setCardPointer('center');
            return;
        }
        setCardPointer(placement);
        const rect = el.getBoundingClientRect();
        const margin = 18;
        const cardRect = card.getBoundingClientRect();
        let top = rect.bottom + margin;
        let inlineStart = rect.left;
        if (placement === 'top') {
            top = rect.top - cardRect.height - margin;
        } else if (placement === 'left') {
            top = rect.top;
            inlineStart = rect.left - cardRect.width - margin;
        } else if (placement === 'right') {
            top = rect.top;
            inlineStart = rect.right + margin;
        }
        inlineStart = Math.min(Math.max(margin, inlineStart), window.innerWidth - cardRect.width - margin);
        top = Math.min(Math.max(margin, top), window.innerHeight - cardRect.height - margin);
        card.style.top = `${top}px`;
        if (isRtl()) {
            card.style.insetInlineStart = `${inlineStart}px`;
            card.style.left = 'auto';
        } else {
            card.style.left = `${inlineStart}px`;
        }
    }"""

if old_pc not in text:
    raise SystemExit("positionCard not found")
text = text.replace(old_pc, new_pc, 1)

old_r1 = """        root.querySelector('[data-tour-title]').textContent = step.title;
        root.querySelector('[data-tour-body]').textContent = step.body;
        root.querySelector('[data-tour-step-label]').textContent = (i18n.step_label || 'Step :current of :total').replace(':current', index + 1).replace(':total', total);
        root.querySelector('[data-tour-progress]').style.width = `${((index + 1) / total) * 100}%`;

        const iconEl = root.querySelector('[data-tour-icon] i');
        if (iconEl) {
            iconEl.className = `fas ${step.icon || 'fa-map-pin'}`;
        }"""

new_r1 = """        const kicker = root.querySelector('[data-tour-kicker]');
        const hint = root.querySelector('[data-tour-hint]');
        const iconWrap = root.querySelector('[data-tour-icon]');
        const cardEl = root.querySelector('.map-tour-card');

        root.querySelector('[data-tour-title]').textContent = step.title;
        root.querySelector('[data-tour-body]').textContent = step.body;
        root.querySelector('[data-tour-step-label]').textContent = (i18n.step_label || 'Step :current of :total').replace(':current', index + 1).replace(':total', total);
        root.querySelector('[data-tour-progress]').style.width = `${((index + 1) / total) * 100}%`;

        if (kicker) {
            kicker.textContent = (isModal || isFinish) ? '' : step.title;
        }
        if (hint) {
            hint.hidden = true;
        }

        const iconEl = root.querySelector('[data-tour-icon] i');
        if (iconEl) {
            iconEl.className = `fas ${step.icon || 'fa-map-pin'}`;
        }
        if (iconWrap) {
            iconWrap.classList.toggle('map-tour-card__icon--finish', isFinish);
            iconWrap.classList.toggle('map-tour-card__icon--modal', isModal && !isFinish);
        }
        if (cardEl) {
            cardEl.setAttribute('dir', isRtl() ? 'rtl' : 'ltr');
            cardEl.lang = document.documentElement.lang || 'en';
        }"""

if old_r1 not in text:
    raise SystemExit("render partial not found")
text = text.replace(old_r1, new_r1, 1)

old_r2 = """        setTimeout(() => {
            if (isModal) {
                clearHighlight();
                positionSpotlight(null);
                positionCard('center');
            } else if (target) {
                positionSpotlight(target, step.pad);
                positionCard(mirrorPlacement(step.placement || 'bottom'), target);
            }
            if (typeof window.deviceMapResize === 'function') {
                window.deviceMapResize();
            }
        }, step.beforeShow ? 380 : 80);"""

new_r2 = """        setTimeout(() => {
            if (isModal) {
                clearHighlight();
                positionSpotlight(null, 0, '');
                positionCard('center');
            } else if (target) {
                positionSpotlight(target, step.pad, step.title);
                positionCard(mirrorPlacement(step.placement || 'bottom'), target);
            } else {
                clearHighlight();
                positionSpotlight(null, 0, '');
                positionCard('center');
                const hintEl = root.querySelector('[data-tour-hint]');
                if (hintEl && i18n.target_missing) {
                    hintEl.textContent = i18n.target_missing;
                    hintEl.hidden = false;
                }
            }
            if (typeof window.deviceMapResize === 'function') {
                window.deviceMapResize();
            }
        }, step.beforeShow ? 380 : 80);"""

if old_r2 not in text:
    raise SystemExit("render timeout not found")
text = text.replace(old_r2, new_r2, 1)

path.write_text(text, encoding="utf-8")
print("OK")
