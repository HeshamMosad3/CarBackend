/* ==========================================================================
   Live dashboard.
     1. Updates itself: a websocket (Laravel Reverb) tells every open dashboard when something
        changed; tables reload in place, counters/badges update, detail pages refresh their
        sections. A 30-second check backs this up if the websocket is down.
     2. Alerts the admin when something needs them: a browser notification and a loud sound
        (and, on the server, the emails they chose).
   Everything here fails soft: with no websocket it polls; with notifications blocked it still
   plays the sound and shows an in-page message.
   ========================================================================== */
(function () {
    'use strict';

    const cfg = window.DASH_LIVE;
    if (!cfg) return;

    const $ = (selector, root) => (root || document).querySelector(selector);
    const $$ = (selector, root) => Array.from((root || document).querySelectorAll(selector));
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const store = {
        get(key, fallback) {
            try { const v = localStorage.getItem(key); return v === null ? fallback : JSON.parse(v); } catch (e) { return fallback; }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); } catch (e) { /* private mode: just not remembered */ }
        }
    };

    // ------------------------------------------------------------------
    // Preferences — kept per browser (the email choices live on the server)
    // ------------------------------------------------------------------
    const PREFS_KEY = 'dash-live-prefs';
    const DEFAULT_PREFS = { sound: true, volume: 0.9, desktop: true, types: {} };

    function prefs() {
        const saved = store.get(PREFS_KEY, {});
        return Object.assign({}, DEFAULT_PREFS, saved, { types: Object.assign({}, saved.types || {}) });
    }

    function savePrefs(next) {
        store.set(PREFS_KEY, next);
        document.dispatchEvent(new CustomEvent('dash:prefs', { detail: next }));
        renderControls();
    }

    const typeEnabled = (type) => prefs().types[type] !== false;

    // ------------------------------------------------------------------
    // Sound — synthesised with the Web Audio API, so there is no file to host
    // ------------------------------------------------------------------
    const Sound = (function () {
        let ctx = null;

        function context() {
            if (!ctx) {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return null;
                ctx = new AudioCtx();
            }
            return ctx;
        }

        // Browsers only allow sound after the user has interacted with the page once.
        function unlock() {
            const c = context();
            if (c && c.state === 'suspended') c.resume().catch(function () { });
        }
        ['pointerdown', 'keydown', 'touchstart'].forEach(function (name) {
            window.addEventListener(name, unlock, { capture: true, passive: true });
        });

        function note(freq, start, length, volume, type) {
            const c = ctx;
            const osc = c.createOscillator();
            const gain = c.createGain();
            osc.type = type || 'sine';
            osc.frequency.setValueAtTime(freq, start);
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(volume, start + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
            osc.connect(gain);
            gain.connect(c.destination);
            osc.start(start);
            osc.stop(start + length + 0.05);
        }

        // A bell: the note, a quieter octave above it and a short bright overtone.
        function bell(freq, start, volume) {
            note(freq, start, 1.0, volume, 'sine');
            note(freq * 2, start, 0.55, volume * 0.4, 'sine');
            note(freq * 2.76, start, 0.25, volume * 0.18, 'triangle');
        }

        /**
         * Normal alert: a rising three-note chime, twice (about 2 seconds).
         * Urgent alert (money to return): a faster, more insistent two-tone pattern, three times.
         * Returns false when the browser still blocks sound (the user hasn't touched the page yet).
         */
        function play(urgent, volume) {
            const c = context();
            if (!c) return false;
            if (c.state !== 'running') {
                c.resume().catch(function () { });
                return false;
            }

            const level = 0.5 * Math.max(0.05, Math.min(1, volume == null ? 0.9 : volume));
            const t0 = c.currentTime + 0.05;

            if (urgent) {
                for (let round = 0; round < 3; round++) {
                    const base = t0 + round * 1.0;
                    [988, 740, 988, 740].forEach(function (freq, i) { note(freq, base + i * 0.2, 0.22, level, 'triangle'); });
                }
            } else {
                for (let round = 0; round < 2; round++) {
                    const base = t0 + round * 1.0;
                    [659.25, 783.99, 1046.5].forEach(function (freq, i) { bell(freq, base + i * 0.2, level); });
                }
            }
            return true;
        }

        return { play: play, unlock: unlock, isReady: function () { return !!ctx && ctx.state === 'running'; } };
    })();

    // ------------------------------------------------------------------
    // Browser notifications
    // ------------------------------------------------------------------
    const Desktop = (function () {
        const supported = 'Notification' in window;

        function permission() { return supported ? Notification.permission : 'unsupported'; }

        function request() {
            if (!supported) return Promise.resolve('unsupported');
            return new Promise(function (resolve) {
                // Older browsers take a callback, newer ones return a promise.
                const maybe = Notification.requestPermission(resolve);
                if (maybe && typeof maybe.then === 'function') maybe.then(resolve);
            }).then(function (result) { renderControls(); return result; });
        }

        function show(alert, silent) {
            if (permission() !== 'granted') return null;
            try {
                const n = new Notification(alert.title, {
                    body: alert.body || '',
                    tag: 'dash-alert-' + alert.id,
                    icon: cfg.icon,
                    badge: cfg.icon,
                    dir: 'rtl',
                    lang: 'ar',
                    // Something needs the admin: keep it on screen until they dismiss it.
                    requireInteraction: true,
                    // Our own sound already played; let the system add one only if ours was blocked.
                    silent: !!silent
                });
                n.onclick = function () {
                    window.focus();
                    n.close();
                    if (alert.url) window.location.href = alert.url;
                };
                return n;
            } catch (e) {
                return null; // e.g. Android Chrome only allows notifications from a service worker
            }
        }

        return { supported: supported, permission: permission, request: request, show: show };
    })();

    // ------------------------------------------------------------------
    // Alerts
    // ------------------------------------------------------------------
    const state = {
        token: cfg.token,
        lastAlertId: cfg.lastAlertId || 0,
        counts: cfg.counts || {},
        subscribed: false,
        mode: 'connecting',
        contentStale: false,
        failures: 0
    };
    const seenAlerts = new Set();
    let soundHintShown = false;

    function toastIcon(tone) { return tone === 'danger' ? 'error' : (tone === 'warning' ? 'warning' : 'info'); }

    function showToast(alert) {
        if (!window.Swal) return;
        Swal.fire({
            toast: true,
            position: 'top-start',
            icon: toastIcon(alert.tone),
            title: alert.title,
            text: alert.body || '',
            timer: alert.urgent ? 20000 : 12000,
            timerProgressBar: true,
            showConfirmButton: !!alert.url,
            confirmButtonText: 'فتح',
            showCloseButton: true,
            didOpen: function (t) { t.onmouseenter = Swal.stopTimer; t.onmouseleave = Swal.resumeTimer; }
        }).then(function (result) {
            if (result.isConfirmed && alert.url) window.location.href = alert.url;
        });
    }

    function ringBell() {
        const icon = $('#bellIcon');
        if (!icon) return;
        icon.classList.remove('bell-ring');
        void icon.offsetWidth; // restart the animation
        icon.classList.add('bell-ring');
        setTimeout(function () { icon.classList.remove('bell-ring'); }, 2500);
    }

    function handleAlert(alert) {
        if (!alert || seenAlerts.has(alert.id)) return;
        seenAlerts.add(alert.id);
        if (alert.id > state.lastAlertId) state.lastAlertId = alert.id;

        if (!typeEnabled(alert.type)) return;

        const p = prefs();
        let played = false;
        if (p.sound) played = Sound.play(!!alert.urgent, p.volume);
        if (p.desktop && Desktop.permission() === 'granted') Desktop.show(alert, played);
        if (document.visibilityState === 'visible') showToast(alert);
        ringBell();

        // The browser refused to play it: the admin hasn't touched this page yet.
        if (p.sound && !played && !soundHintShown && window.toast) {
            soundHintShown = true;
            toast('اضغط في أي مكان في الصفحة لتفعيل صوت التنبيهات', 'info');
        }
    }

    function testAlert() {
        handleAlert({
            id: -Date.now(),
            type: '__test',
            title: 'تنبيه تجريبي',
            body: 'هكذا يصلك التنبيه عندما يلزمك إجراء: صوت + إشعار من المتصفح.',
            url: window.location.href,
            tone: 'warning',
            urgent: false
        });
    }

    // ------------------------------------------------------------------
    // Counters, badges, tab title
    // ------------------------------------------------------------------
    const baseTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');

    function applyCounts(counts) {
        if (!counts) return;
        state.counts = counts;

        const badge = $('#bellBadge');
        if (badge) {
            badge.hidden = !counts.total;
            badge.textContent = counts.total > 99 ? '99+' : counts.total;
        }

        $$('[data-badge-key]').forEach(function (link) {
            const n = counts[link.dataset.badgeKey] || 0;
            const label = $('p', link);
            let pill = $('.nav-badge', link);
            if (n > 0) {
                if (!pill && label) {
                    pill = document.createElement('span');
                    pill.className = 'nav-badge';
                    label.appendChild(pill);
                }
                if (pill) pill.textContent = n > 99 ? '99+' : n;
            } else if (pill) {
                pill.remove();
            }
        });

        document.title = (counts.total > 0 ? '(' + counts.total + ') ' : '') + baseTitle;
    }

    function applyChips(chips) {
        const keys = Object.keys(chips || {});
        if (!keys.length) return;
        $$('[data-count-key]').forEach(function (el) {
            const key = el.dataset.countKey;
            el.textContent = key in chips ? chips[key] : '0';
        });
    }

    // ------------------------------------------------------------------
    // Asking the server (the same call serves events, the safety check and catching up)
    // ------------------------------------------------------------------
    async function pulse() {
        let data;
        try {
            const url = cfg.pulseUrl + '?after=' + encodeURIComponent(state.lastAlertId) + '&scope=' + encodeURIComponent(cfg.scope || '');
            const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
            if (!res.ok) throw new Error('status ' + res.status);
            data = await res.json();
        } catch (e) {
            state.failures++;
            if (state.failures >= 3) setStatus('offline');
            return null;
        }
        state.failures = 0;
        if (state.mode === 'offline') setStatus(state.subscribed ? 'live' : 'polling');

        applyCounts(data.counts);
        applyChips(data.chips);
        const menu = $('#attentionMenuBody');
        if (menu && data.menu && menu.innerHTML.trim() !== data.menu.trim()) menu.innerHTML = data.menu;

        // Alerts that happened while no websocket was listening (a closed tab, a dropped connection).
        (data.new_alerts || []).forEach(handleAlert);
        if (data.last_alert_id > state.lastAlertId) state.lastAlertId = data.last_alert_id;

        return data;
    }

    // ------------------------------------------------------------------
    // Refreshing what is on screen
    // ------------------------------------------------------------------
    let refreshTimer = null;
    let refreshing = false;
    let queued = false;

    function scheduleRefresh(delay) {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function () { refresh('event'); }, delay == null ? 600 : delay);
    }

    async function refresh(reason) {
        if (refreshing) { queued = true; return; }
        refreshing = true;
        try {
            const data = await pulse();
            if (!data) return;
            const changed = data.token !== state.token;
            state.token = data.token;
            if (reason === 'event' || reason === 'force' || changed) await refreshContent(reason === 'force');
        } finally {
            refreshing = false;
            if (queued) { queued = false; scheduleRefresh(300); }
        }
    }

    async function refreshContent(force) {
        // A hidden tab can wait: it catches up the moment it is looked at.
        if (document.hidden) { state.contentStale = true; return; }
        state.contentStale = false;

        reloadLiveTables();
        await refreshRegions(force);
        await refreshChart();
    }

    function reloadLiveTables(attempt) {
        if (!window.DataTable || !DataTable.tables().length) return;
        // Redrawing a table closes a status menu the admin has just opened; wait for them to choose.
        if ($('table.dataTable .dropdown-menu.show') && (attempt || 0) < 8) {
            setTimeout(function () { reloadLiveTables((attempt || 0) + 1); }, 1500);
            return;
        }
        window.reloadTables();
    }

    async function refreshChart() {
        if (!$('[data-live-chart]') || !window.DashHome) return;
        try {
            const res = await fetch(cfg.chartUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (res.ok) window.DashHome.updateChart(await res.json());
        } catch (e) { /* the next change will try again */ }
    }

    // A region is a part of a server-rendered page marked data-live-region. It is swapped for the
    // freshly rendered version — unless the admin is in the middle of using it.
    document.addEventListener('input', markDirty, true);
    document.addEventListener('change', markDirty, true);
    function markDirty(event) {
        const region = event.target.closest && event.target.closest('[data-live-region]');
        if (region) region.dataset.dirty = '1';
    }

    function regionInUse(region) {
        if (region.dataset.dirty === '1') return true;
        const active = document.activeElement;
        if (active && region.contains(active) && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return true;
        if ($('.dropdown-menu.show', region)) return true;
        if ($('.modal.show')) return true;
        if (window.Swal && Swal.isVisible() && !Swal.getPopup()?.classList.contains('swal2-toast')) return true;
        return false;
    }

    function runScripts(region) {
        $$('script', region).forEach(function (old) {
            const fresh = document.createElement('script');
            Array.from(old.attributes).forEach(function (a) { fresh.setAttribute(a.name, a.value); });
            fresh.text = old.textContent;
            old.replaceWith(fresh);
        });
    }

    async function refreshRegions(force) {
        const regions = $$('[data-live-region]');
        if (!regions.length) return;

        let html;
        try {
            const res = await fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }, cache: 'no-store' });
            if (!res.ok || new URL(res.url).pathname !== window.location.pathname) return; // e.g. session ended
            html = await res.text();
        } catch (e) { return; }

        const doc = new DOMParser().parseFromString(html, 'text/html');
        let postponed = false;

        regions.forEach(function (region) {
            const name = region.dataset.liveRegion;
            const fresh = doc.querySelector('[data-live-region="' + name + '"]');
            if (!fresh || fresh.innerHTML.trim() === region.innerHTML.trim()) return;

            if (!force && regionInUse(region)) { postponed = true; return; }

            region.innerHTML = fresh.innerHTML;
            delete region.dataset.dirty;
            region.classList.remove('live-flash');
            void region.offsetWidth;
            region.classList.add('live-flash');
            runScripts(region);
            if (window.initTooltips) initTooltips(region);
            document.dispatchEvent(new CustomEvent('dash:region-updated', { detail: { name: name, region: region } }));
        });

        postponed ? showStaleChip() : hideStaleChip();
    }

    // "This page changed" chip, for when an update was held back so as not to disturb the admin.
    function showStaleChip() {
        if ($('#liveStaleChip')) return;
        const chip = document.createElement('div');
        chip.id = 'liveStaleChip';
        chip.className = 'live-stale';
        chip.innerHTML = '<i class="bi bi-arrow-repeat"></i> تغيّرت بيانات هذه الصفحة <button type="button" class="btn btn-sm btn-primary">تحديث</button>';
        chip.querySelector('button').addEventListener('click', function () { refresh('force'); });
        document.body.appendChild(chip);
    }

    function hideStaleChip() { $('#liveStaleChip')?.remove(); }

    // ------------------------------------------------------------------
    // Connection status pill
    // ------------------------------------------------------------------
    function setStatus(mode) {
        state.mode = mode;
        const pill = $('#liveStatus');
        if (!pill) return;
        const text = {
            live: ['مباشر', 'التحديث المباشر يعمل: كل تغيير يظهر فوراً'],
            polling: ['يتحدّث كل بضع ثوانٍ', 'التحديث المباشر غير متاح، تُفحص البيانات دورياً'],
            connecting: ['جارٍ الاتصال', 'جارٍ الاتصال بالتحديث المباشر'],
            offline: ['لا يوجد اتصال', 'تعذّر الوصول إلى الخادم، سيُعاد المحاولة تلقائياً']
        }[mode];
        pill.className = 'live-pill is-' + mode;
        pill.title = text[1];
        const label = $('.live-text', pill);
        if (label) label.textContent = text[0];
    }

    // ------------------------------------------------------------------
    // The websocket
    // ------------------------------------------------------------------
    function connect() {
        if (!cfg.realtime || !window.Pusher) { setStatus('polling'); return; }

        try {
            const pusher = new Pusher(cfg.key, {
                cluster: 'mt1', // required by the client, ignored by Reverb
                wsHost: cfg.host,
                wsPort: cfg.port,
                wssPort: cfg.port,
                forceTLS: cfg.scheme === 'https',
                enabledTransports: ['ws', 'wss'],
                disableStats: true,
                channelAuthorization: {
                    endpoint: cfg.authEndpoint,
                    transport: 'ajax',
                    headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
                }
            });

            const channel = pusher.subscribe('private-admin.dashboard');

            channel.bind('pusher:subscription_succeeded', function () {
                state.subscribed = true;
                setStatus('live');
                // Whatever happened while we weren't connected.
                refresh('reconnect');
            });
            channel.bind('pusher:subscription_error', function () { state.subscribed = false; setStatus('polling'); });

            pusher.connection.bind('state_change', function (change) {
                if (change.current === 'connected') return; // wait for the subscription
                state.subscribed = false;
                setStatus(change.current === 'connecting' ? 'connecting' : 'polling');
            });

            channel.bind('dashboard.updated', function (data) {
                if (data.counts) applyCounts(data.counts);
                if (data.alert) handleAlert(data.alert);
                scheduleRefresh();
            });
        } catch (e) {
            setStatus('polling');
        }
    }

    // The safety net: every 60 s while live, every 12 s while the websocket is unavailable.
    function scheduleSafetyCheck() {
        const wait = state.subscribed ? 60000 : 12000;
        setTimeout(function () {
            if (!document.hidden) refresh('poll');
            scheduleSafetyCheck();
        }, wait);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        refresh(state.contentStale ? 'force' : 'poll');
    });

    // ------------------------------------------------------------------
    // The bell's controls and the "allow notifications" bar
    // ------------------------------------------------------------------
    function renderControls() {
        const p = prefs();

        const soundToggle = $('#liveSoundToggle');
        if (soundToggle) soundToggle.checked = !!p.sound;

        const button = $('#liveDesktopBtn');
        if (button) {
            const permission = Desktop.permission();
            button.disabled = permission === 'denied' || permission === 'unsupported';
            button.className = 'btn btn-sm ' + (permission === 'default' ? 'btn-primary' : 'btn-light');
            button.textContent = {
                default: 'تفعيل',
                granted: p.desktop ? 'مفعّلة ✓' : 'متوقفة',
                denied: 'محظورة',
                unsupported: 'غير مدعومة'
            }[permission];
            button.title = permission === 'denied' ? 'المتصفح يحظر الإشعارات لهذا الموقع. فعّلها من إعدادات الموقع في المتصفح.' : '';
        }

        document.dispatchEvent(new CustomEvent('dash:controls'));
    }

    function bindControls() {
        $('#liveSoundToggle')?.addEventListener('change', function (e) {
            const p = prefs();
            p.sound = e.target.checked;
            savePrefs(p);
            if (p.sound) Sound.play(false, p.volume); // let them hear what they just turned on
        });

        $('#liveDesktopBtn')?.addEventListener('click', function () {
            const permission = Desktop.permission();
            if (permission === 'default') {
                enableDesktopAlerts();
            } else if (permission === 'granted') {
                const p = prefs();
                p.desktop = !p.desktop;
                savePrefs(p);
            }
        });

        $('#liveTestBtn')?.addEventListener('click', testAlert);
    }

    async function enableDesktopAlerts() {
        Sound.unlock(); // this click is the gesture browsers want before they allow sound
        const result = await Desktop.request();
        hideBanner();
        if (result === 'granted') {
            const p = prefs();
            p.desktop = true;
            savePrefs(p);
            // Show them what it will look like.
            testAlert();
        }
    }

    function showBanner() {
        if (!Desktop.supported || Desktop.permission() !== 'default') return;
        if (Date.now() < store.get('dash-live-ask-snooze', 0)) return;
        if ($('#liveBanner')) return;

        const host = $('.app-main');
        if (!host) return;

        const bar = document.createElement('div');
        bar.id = 'liveBanner';
        bar.className = 'live-banner';
        bar.setAttribute('role', 'alert');
        bar.innerHTML =
            '<span class="icon"><i class="bi bi-bell-fill"></i></span>' +
            '<div class="flex-grow-1"><div class="fw-bold">فعّل إشعارات المتصفح</div>' +
            '<div class="small">لتصلك التنبيهات المهمة بصوت وإشعار حتى لو كنت في تبويب آخر أو برنامج آخر.</div></div>' +
            '<button type="button" class="btn btn-primary" id="liveAllowBtn">تفعيل الإشعارات</button>' +
            '<button type="button" class="btn btn-light" id="liveLaterBtn">لاحقاً</button>';
        host.insertBefore(bar, host.firstChild);

        $('#liveAllowBtn', bar).addEventListener('click', enableDesktopAlerts);
        $('#liveLaterBtn', bar).addEventListener('click', function () {
            store.set('dash-live-ask-snooze', Date.now() + 24 * 60 * 60 * 1000); // ask again tomorrow
            hideBanner();
        });
    }

    function hideBanner() { $('#liveBanner')?.remove(); }

    // ------------------------------------------------------------------
    // Start
    // ------------------------------------------------------------------
    $$('[data-alert-id]').forEach(function (el) { seenAlerts.add(Number(el.dataset.alertId)); });

    let started = false;
    function start() {
        if (started) return;
        started = true;
        bindControls();
        renderControls();
        showBanner();
    }
    document.addEventListener('DOMContentLoaded', start);
    if (document.readyState !== 'loading') start();

    setStatus('connecting');
    connect();
    scheduleSafetyCheck();
    // One check straight away: counts and the menu may have moved between the page render and now.
    setTimeout(function () { refresh('poll'); }, 1500);

    window.DashLive = {
        config: cfg,
        prefs: prefs,
        savePrefs: savePrefs,
        test: testAlert,
        refresh: function () { return refresh('force'); },
        sound: Sound,
        desktop: Desktop,
        enableDesktop: enableDesktopAlerts,
        status: function () { return state.mode; }
    };
})();
