import Alpine from 'alpinejs';

window.Alpine = Alpine;

// ----- Accessibility preferences ---------------------------------------
// Persisted to localStorage and applied to <html> as classes + a font-scale
// CSS variable. A tiny inline script in <head> (partials/head) applies the
// same values before paint to avoid a flash.
const A11Y_KEY = 'ieee-vol-a11y';

const A11Y_TOGGLES = {
    contrast: 'a11y-contrast',
    legible: 'a11y-legible',
    links: 'a11y-links',
    spacing: 'a11y-spacing',
    motion: 'a11y-reduce-motion',
    cursor: 'a11y-big-cursor',
    guide: 'a11y-guide',
};

const A11Y_FONT_MIN = 0.9;
const A11Y_FONT_MAX = 1.6;
const A11Y_FONT_STEP = 0.1;

Alpine.store('a11y', {
    fontScale: 1,
    contrast: false,
    legible: false,
    links: false,
    spacing: false,
    motion: false,
    cursor: false,
    guide: false,

    init() {
        const saved = this.load();
        Object.assign(this, saved);
        if (saved.motion === undefined && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            this.motion = true;
        }
        if (saved.contrast === undefined && window.matchMedia('(prefers-contrast: more)').matches) {
            this.contrast = true;
        }
        this.apply();
    },
    load() {
        try { return JSON.parse(localStorage.getItem(A11Y_KEY)) || {}; } catch (e) { return {}; }
    },
    save() {
        try {
            localStorage.setItem(A11Y_KEY, JSON.stringify({
                fontScale: this.fontScale, contrast: this.contrast, legible: this.legible, links: this.links,
                spacing: this.spacing, motion: this.motion, cursor: this.cursor, guide: this.guide,
            }));
        } catch (e) { /* storage unavailable */ }
    },
    apply() {
        const el = document.documentElement;
        el.style.setProperty('--a11y-font-scale', this.fontScale);
        Object.entries(A11Y_TOGGLES).forEach(([key, cls]) => el.classList.toggle(cls, !!this[key]));
    },
    fontPercent() { return Math.round(this.fontScale * 100); },
    fontUp() { this.fontScale = Math.min(A11Y_FONT_MAX, Math.round((this.fontScale + A11Y_FONT_STEP) * 10) / 10); this.apply(); this.save(); },
    fontDown() { this.fontScale = Math.max(A11Y_FONT_MIN, Math.round((this.fontScale - A11Y_FONT_STEP) * 10) / 10); this.apply(); this.save(); },
    fontReset() { this.fontScale = 1; this.apply(); this.save(); },
    toggle(key) { this[key] = !this[key]; this.apply(); this.save(); },
    reset() { this.fontScale = 1; Object.keys(A11Y_TOGGLES).forEach((k) => { this[k] = false; }); this.apply(); this.save(); },
});

document.addEventListener('mousemove', (event) => {
    const store = Alpine.store('a11y');
    if (!store || !store.guide) return;
    const guide = document.getElementById('a11y-guide');
    if (guide) guide.style.top = `${event.clientY}px`;
});

// ----- Skill multi-select ------------------------------------------------
// See components/skill-picker.blade.php. New entries (when allowed) are sent
// as "new:<label>" and created server-side.
Alpine.data('skillPicker', (options, selected, allowCreate = false, max = null) => ({
    options,
    selected: [...selected],
    query: '',
    open: false,
    allowCreate,
    max,
    labelFor(id) {
        if (String(id).startsWith('new:')) return String(id).slice(4);
        return this.options.find((o) => o.id === String(id))?.label ?? id;
    },
    filtered() {
        const q = this.query.trim().toLowerCase();
        return this.options
            .filter((o) => !this.selected.includes(o.id))
            .filter((o) => !q || o.label.toLowerCase().includes(q))
            .slice(0, 50);
    },
    full() { return this.max && this.selected.length >= this.max; },
    add(id) {
        if (this.full() || this.selected.includes(id)) return;
        this.selected.push(id);
        this.query = '';
        this.$refs.search.focus();
    },
    remove(id) { this.selected = this.selected.filter((s) => s !== id); },
    canCreate() {
        const q = this.query.trim();
        return this.allowCreate && q.length > 1 && !this.full()
            && !this.options.some((o) => o.label.toLowerCase() === q.toLowerCase())
            && !this.selected.includes(`new:${q}`);
    },
    create() { this.add(`new:${this.query.trim()}`); },
    addFirst() {
        const first = this.filtered()[0];
        if (first) this.add(first.id);
        else if (this.canCreate()) this.create();
    },
}));

// ----- Co-owner picker (searches /lookup/users) --------------------------
Alpine.data('userPicker', (endpoint, initial = [], max = 9, exclude = []) => ({
    endpoint,
    people: [...initial],
    results: [],
    query: '',
    open: false,
    loading: false,
    max,
    exclude,
    timer: null,
    search() {
        clearTimeout(this.timer);
        const q = this.query.trim();
        if (q.length < 2) { this.results = []; return; }
        this.timer = setTimeout(async () => {
            this.loading = true;
            try {
                const res = await fetch(`${this.endpoint}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                const taken = new Set([...this.people.map((p) => p.id), ...this.exclude]);
                this.results = data.filter((u) => !taken.has(u.id));
                this.open = true;
            } finally {
                this.loading = false;
            }
        }, 200);
    },
    add(person) {
        if (this.people.length >= this.max) return;
        this.people.push(person);
        this.query = '';
        this.results = [];
        this.open = false;
    },
    remove(id) { this.people = this.people.filter((p) => p.id !== id); },
}));

Alpine.start();

// ----- Lazy-loaded heavy modules -------------------------------------------
function boot() {
    const charts = document.querySelectorAll('[data-chart]');
    if (charts.length) import('./charts').then((m) => m.initCharts(charts));

    const editors = document.querySelectorAll('[data-editorjs]');
    if (editors.length) import('./editor').then((m) => m.initEditors(editors));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
