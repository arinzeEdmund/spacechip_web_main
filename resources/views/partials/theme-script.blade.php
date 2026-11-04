{{--
    Theme bootstrap. Must run in <head> before any stylesheet paints, so the
    page never flashes the wrong mode. Mirrors new_theme/theme/theme-script.ts:
    the stored choice is "light", "dark" or "system" (follows the OS).

    Any element with [data-theme-value="light|dark|system"] sets the choice;
    [data-theme-flip] toggles light <-> dark. Choices sync across tabs.
--}}
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#f7f8f7" data-theme-meta>
<script>
    (function () {
        var KEY = 'spacechip-theme', DEFAULT = 'system', DARK = '(prefers-color-scheme: dark)';
        var root = document.documentElement, memory = null;

        function stored() {
            if (memory) return memory;
            try {
                var v = localStorage.getItem(KEY);
                return v === 'light' || v === 'dark' || v === 'system' ? v : DEFAULT;
            } catch (e) { return DEFAULT; }
        }
        function resolve(p) { return p === 'system' ? (matchMedia(DARK).matches ? 'dark' : 'light') : p; }
        function apply(animate) {
            var pref = stored(), mode = resolve(pref);
            if (animate) {
                root.classList.add('theme-switching');
                setTimeout(function () { root.classList.remove('theme-switching'); }, 320);
            }
            root.classList.remove('light', 'dark');
            root.classList.add(mode);
            root.style.colorScheme = mode;
            root.dataset.themePref = pref;
            var meta = document.querySelector('meta[data-theme-meta]');
            if (meta) meta.setAttribute('content', mode === 'dark' ? '#000000' : '#f7f8f7');
            document.querySelectorAll('[data-theme-value]').forEach(function (b) {
                b.setAttribute('aria-checked', String(b.getAttribute('data-theme-value') === pref));
            });
        }
        function set(pref) {
            try { localStorage.setItem(KEY, pref); } catch (e) { memory = pref; }
            apply(true);
        }

        apply(false);
        window.spacechipTheme = { get: stored, resolved: function () { return resolve(stored()); }, set: set };

        document.addEventListener('click', function (e) {
            var t = e.target.closest && e.target.closest('[data-theme-value],[data-theme-flip]');
            if (!t) return;
            e.preventDefault();
            set(t.hasAttribute('data-theme-flip') ? (resolve(stored()) === 'dark' ? 'light' : 'dark') : t.getAttribute('data-theme-value'));
        });
        document.addEventListener('DOMContentLoaded', function () { apply(false); });
        window.addEventListener('storage', function (e) { if (e.key === KEY) apply(true); });
        try { matchMedia(DARK).addEventListener('change', function () { if (stored() === 'system') apply(true); }); } catch (e) {}
    })();
</script>
