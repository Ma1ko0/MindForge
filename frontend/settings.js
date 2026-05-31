// MindForge — client-side settings (localStorage, per device).
// Controls: theme, sidebar order + visibility, start page, lyrics rhyme default.
(function () {
    if (window.MindForgeSettings) return;

    const KEY = 'mindforge.settings';

    // Canonical sidebar navigation (default order). Each must have a matching
    // [data-nav-id] button in Sidebar.html.
    const NAV = [
        { id: 'dashboard', label: 'Dashboard', icon: 'fa-house', url: '/dashboard' },
        { id: 'workflow', label: 'Heute', icon: 'fa-sun', url: '/workflow' },
        { id: 'habits', label: 'Habits', icon: 'fa-repeat', url: '/habits' },
        { id: 'todo', label: 'ToDo-Listen', icon: 'fa-table-list', url: '/todo' },
        { id: 'projects', label: 'Projekte', icon: 'fa-folder-open', url: '/projects' },
        { id: 'tags', label: 'Tags', icon: 'fa-hashtag', url: '/tags' },
        { id: 'calendar', label: 'Kalender', icon: 'fa-calendar-days', url: '/calendar' },
        { id: 'notes', label: 'Notizen', icon: 'fa-note-sticky', url: '/notes' },
        { id: 'daily', label: 'Daily Note', icon: 'fa-calendar-day', url: '/daily' },
        { id: 'lyrics', label: 'Lyrics', icon: 'fa-music', url: '/lyrics' },
        { id: 'settings', label: 'Einstellungen', icon: 'fa-gear', url: '/views/settings/index.html' },
    ];
    // settings is always shown and stays at the bottom — not user-hideable/reorderable
    const FIXED_BOTTOM = ['settings'];

    const DEFAULTS = {
        theme: 'dark',
        startPage: 'dashboard',
        sidebarOrder: NAV.filter((n) => !FIXED_BOTTOM.includes(n.id)).map((n) => n.id),
        sidebarHidden: [],
        rhymeColors: true,
    };

    function read() {
        let s = {};
        try { s = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { s = {}; }
        const merged = Object.assign({}, DEFAULTS, s);
        // sanitize order: keep only known ids, append any new nav ids that appeared
        const known = NAV.map((n) => n.id).filter((id) => !FIXED_BOTTOM.includes(id));
        merged.sidebarOrder = (merged.sidebarOrder || []).filter((id) => known.includes(id));
        known.forEach((id) => { if (!merged.sidebarOrder.includes(id)) merged.sidebarOrder.push(id); });
        merged.sidebarHidden = (merged.sidebarHidden || []).filter((id) => known.includes(id));
        return merged;
    }

    function write(patch) {
        const next = Object.assign(read(), patch || {});
        try { localStorage.setItem(KEY, JSON.stringify(next)); } catch (e) {}
        return next;
    }

    function navById(id) { return NAV.find((n) => n.id === id); }

    function applyTheme(s) {
        s = s || read();
        document.documentElement.setAttribute('data-bs-theme', s.theme === 'light' ? 'light' : 'dark');
    }

    function applySidebar(s) {
        s = s || read();
        const nav = document.querySelector('.Sidebar nav');
        if (!nav) return;
        const order = s.sidebarOrder.slice();
        // reorder: append buttons in saved order
        order.forEach((id) => {
            const btn = nav.querySelector('[data-nav-id="' + id + '"]');
            if (btn) nav.appendChild(btn);
        });
        // fixed-bottom items go last
        FIXED_BOTTOM.forEach((id) => {
            const btn = nav.querySelector('[data-nav-id="' + id + '"]');
            if (btn) nav.appendChild(btn);
        });
        // visibility
        nav.querySelectorAll('[data-nav-id]').forEach((btn) => {
            const id = btn.getAttribute('data-nav-id');
            const hide = s.sidebarHidden.includes(id) && !FIXED_BOTTOM.includes(id);
            btn.classList.toggle('d-none', hide);
        });
    }

    let started = false;
    function applyStart() {
        if (started) return;
        started = true;
        const s = read();
        const nav = navById(s.startPage) || navById('dashboard');
        const url = nav ? nav.url : '/dashboard';
        if (window.htmx) {
            window.htmx.ajax('GET', url, { target: '#main-content', swap: 'innerHTML' });
        }
    }

    // Public API (used by the settings page UI)
    window.MindForgeSettings = {
        NAV: NAV,
        FIXED_BOTTOM: FIXED_BOTTOM,
        get: read,
        set: function (patch) {
            const next = write(patch);
            applyTheme(next);
            applySidebar(next);
            return next;
        },
        applyTheme: applyTheme,
        applySidebar: applySidebar,
        rhymeColorsDefault: function () { return read().rhymeColors !== false; },
    };

    // Re-apply sidebar prefs whenever the sidebar gets (re)loaded
    document.addEventListener('htmx:afterSwap', function (e) {
        const t = e.detail && e.detail.target;
        if (t && (t.querySelector && t.querySelector('.Sidebar') || (t.classList && t.classList.contains('Sidebar')))) {
            applySidebar();
        }
    });

    function boot() {
        applyTheme();
        applyStart();
        applySidebar();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
