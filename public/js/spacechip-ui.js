/*
 * Spacechip UI runtime
 * ------------------------------------------------------------------
 * Loaded (deferred) on every page by partials/head-assets.blade.php.
 *
 *   1. Reveal   - staggered entrance for cards and sections, including
 *                 cards rendered later by page scripts
 *   2. Nav      - material deepens once the page scrolls
 *   3. Count-up - animated money values ([data-sc-countup])
 *   4. Copy     - [data-copy="text"] buttons copy to the clipboard and
 *                 confirm with a toast (window.spacechipToast)
 *
 * Every effect is progressive: without this file the pages render
 * normally, and prefers-reduced-motion skips movement entirely.
 */
(function () {
    'use strict';

    var doc = document;
    var root = doc.documentElement;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    root.classList.add('sc-js');

    /* ---------------------------------------------------------------
     * 1. Reveal
     * ------------------------------------------------------------- */
    var SECTION_SELECTOR = [
        '.section-head', '.wallet-panel', '.controls-row', '.page-card',
        '.scp-hero', '.scp-card', '.scd-hero', '[data-reveal]',
    ].join(',');

    function isCard(node) {
        if (!node.classList || node.classList.contains('skeleton-card') || node.classList.contains('skeleton-placeholder')) return false;
        for (var i = 0; i < node.classList.length; i++) {
            var t = node.classList[i];
            if (t === 'card' || (t.length > 5 && t.slice(-5) === '-card')) return true;
        }
        return false;
    }

    function wantsReveal(node) {
        return node.nodeType === 1 && !node.closest('nav, header, .scn-tabbar, [x-data]') &&
            (isCard(node) || node.matches(SECTION_SELECTOR));
    }

    var io = null;
    var queue = [];
    var flushScheduled = false;
    var stageReady = false;

    function requestFlush() {
        if (stageReady && queue.length && !flushScheduled) {
            flushScheduled = true;
            requestAnimationFrame(flush);
        }
    }

    function settle(node) {
        node.classList.remove('sc-reveal', 'is-in', 'sc-reveal--quick');
        node.style.removeProperty('--sc-delay');
    }

    function flush() {
        flushScheduled = false;
        queue.forEach(function (node, i) {
            node.style.setProperty('--sc-delay', Math.min(i, 9) * 55 + 'ms');
            node.classList.add('is-in');
            var t = setTimeout(function () { settle(node); }, 1200 + Math.min(i, 9) * 55);
            node.addEventListener('transitionend', function handler(e) {
                if (e.target !== node || e.propertyName !== 'transform') return;
                node.removeEventListener('transitionend', handler);
                clearTimeout(t);
                settle(node);
            });
        });
        queue = [];
    }

    function watch(node, quick) {
        if (node.__scWatched) return;
        node.__scWatched = true;
        node.classList.add('sc-reveal');
        if (quick) node.classList.add('sc-reveal--quick');
        io.observe(node);
    }

    function scan(scope) {
        if (scope.nodeType !== 1) return;
        if (wantsReveal(scope)) watch(scope, scanQuick);
        scope.querySelectorAll('*').forEach(function (n) { if (wantsReveal(n)) watch(n, scanQuick); });
    }
    var scanQuick = false;

    function startReveal() {
        if (reduceMotion || !('IntersectionObserver' in window)) return;

        io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                io.unobserve(entry.target);
                queue.push(entry.target);
            });
            requestFlush();
        }, { rootMargin: '0px', threshold: 0 });

        scan(doc.body);
        scanQuick = true; // anything rendered later gets the lighter entrance

        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); });
            });
        }).observe(doc.body, { childList: true, subtree: true });
    }

    /* ---------------------------------------------------------------
     * 2. Navigation material
     * ------------------------------------------------------------- */
    function startNav() {
        var nav = doc.querySelector('.scn-nav');
        if (!nav) return;
        var ticking = false;
        var update = function () {
            nav.classList.toggle('is-scrolled', window.scrollY > 8);
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        update();
    }

    /* ---------------------------------------------------------------
     * 3. Count-up for money values
     * ------------------------------------------------------------- */
    function startCountUp() {
        doc.querySelectorAll('[data-sc-countup]').forEach(function (node) {
            var current = parse(node.textContent);
            var animating = false;

            new MutationObserver(function () {
                if (animating) return;
                var text = node.textContent;
                var target = parse(text);
                if (target === null || current === null || target === current || reduceMotion) {
                    current = target;
                    return;
                }
                var prefix = (text.match(/^[^\d-]*/) || [''])[0];
                var from = current;
                var t0 = performance.now();
                var dur = 900;
                animating = true;
                (function step(now) {
                    var p = Math.min(1, (now - t0) / dur);
                    var eased = 1 - Math.pow(1 - p, 4);
                    var v = from + (target - from) * eased;
                    node.textContent = prefix + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (p < 1) requestAnimationFrame(step);
                    else {
                        node.textContent = text;
                        current = target;
                        setTimeout(function () { animating = false; }, 0);
                    }
                })(t0);
                node.classList.remove('sc-pop');
                void node.offsetWidth;
                node.classList.add('sc-pop');
            }).observe(node, { childList: true, characterData: true, subtree: true });

            function parse(s) {
                var n = parseFloat(String(s).replace(/[^\d.-]/g, ''));
                return isNaN(n) ? null : n;
            }
        });
    }

    /* ---------------------------------------------------------------
     * 4. Copy + toast
     * ------------------------------------------------------------- */
    var toastEl = null, toastTimer = 0;
    function toast(message) {
        if (!toastEl) {
            toastEl = doc.createElement('div');
            toastEl.className = 'ui-toast';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
            doc.body.appendChild(toastEl);
        }
        toastEl.textContent = message;
        toastEl.classList.add('is-shown');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.classList.remove('is-shown'); }, 1800);
    }
    window.spacechipToast = toast;

    doc.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-copy]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var text = btn.getAttribute('data-copy') || '';
        var done = function () {
            btn.classList.add('is-copied');
            setTimeout(function () { btn.classList.remove('is-copied'); }, 1400);
            toast((btn.getAttribute('data-copy-label') || 'Value') + ' copied');
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { toast('Copy failed'); });
        } else {
            var ta = doc.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;opacity:0';
            doc.body.appendChild(ta);
            ta.select();
            try { doc.execCommand('copy'); done(); } catch (err) { toast('Copy failed'); }
            ta.remove();
        }
    });

    // Site header menu sheet (<details class="sh-menu">): close on outside click / Escape.
    doc.addEventListener('click', function (e) {
        doc.querySelectorAll('details.sh-menu[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
    doc.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') doc.querySelectorAll('details.sh-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
    });

    /* ---------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------- */
    function boot() {
        startNav();
        startCountUp();
        startReveal();
        root.classList.remove('sc-preload');
        stageReady = true;
        requestFlush();
    }

    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
