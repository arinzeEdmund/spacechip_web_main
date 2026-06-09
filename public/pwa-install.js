(function () {
  const STORAGE = {
    dismissedUntil: 'pwa_install_dismissed_until',
    lastShownAt: 'pwa_install_last_shown_at',
    installed: 'pwa_installed',
  };

  const CONFIG = {
    showDelayMs: 4000,
    remindAfterDismissMs: 7 * 24 * 60 * 60 * 1000,
    minTimeBetweenShowsMs: 24 * 60 * 60 * 1000,
    manualFallbackDelayMs: 6000,
  };

  const isStandalone = () =>
    window.matchMedia && window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

  const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);

  const now = () => Date.now();

  const readNum = (k) => {
    const v = localStorage.getItem(k);
    if (!v) return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  };

  const shouldShow = () => {
    if (isStandalone()) return false;
    if (localStorage.getItem(STORAGE.installed) === '1') return false;
    const dismissedUntil = readNum(STORAGE.dismissedUntil);
    if (dismissedUntil && dismissedUntil > now()) return false;
    const lastShownAt = readNum(STORAGE.lastShownAt);
    if (lastShownAt && now() - lastShownAt < CONFIG.minTimeBetweenShowsMs) return false;
    return true;
  };

  const getUi = () => {
    const banner = document.getElementById('pwa-install-banner');
    if (!banner) return null;
    return {
      banner,
      text: document.getElementById('pwa-install-text'),
      later: document.getElementById('pwa-install-later'),
      action: document.getElementById('pwa-install-action'),
    };
  };

  const createFallbackUi = () => {
    if (getUi()) return;

    const wrap = document.createElement('div');
    wrap.id = 'pwa-install-banner';
    wrap.style.cssText = [
      'position:fixed',
      'left:16px',
      'right:16px',
      'bottom:16px',
      'z-index:9999',
      'display:none',
    ].join(';');

    const card = document.createElement('div');
    card.style.cssText = [
      'border-radius:22px',
      'padding:14px 14px',
      'background:rgba(31,41,55,.72)',
      'backdrop-filter:blur(12px)',
      'border:1px solid rgba(255,255,255,.14)',
      'box-shadow:0 14px 35px rgba(0,0,0,.25)',
      'color:rgba(255,255,255,.9)',
      'display:flex',
      'gap:12px',
      'align-items:center',
      'justify-content:space-between',
      'flex-wrap:wrap',
    ].join(';');

    const left = document.createElement('div');
    left.style.cssText = 'min-width:200px;max-width:560px';
    left.innerHTML = '<div style="font-weight:900">Install spacechip</div><div id="pwa-install-text" style="font-size:13px;opacity:.78;margin-top:2px">Get faster access and offline support.</div>';

    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:10px;align-items:center;flex-wrap:nowrap';

    const later = document.createElement('button');
    later.type = 'button';
    later.id = 'pwa-install-later';
    later.textContent = 'Not now';
    later.style.cssText = 'padding:10px 14px;border-radius:9999px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.14);color:rgba(255,255,255,.85);font-weight:900;cursor:pointer';

    const install = document.createElement('button');
    install.type = 'button';
    install.id = 'pwa-install-action';
    install.textContent = 'Install';
    install.style.cssText = 'padding:10px 14px;border-radius:9999px;background:linear-gradient(90deg,#f27457,#145454);border:none;color:#fff;font-weight:900;cursor:pointer';

    actions.appendChild(later);
    actions.appendChild(install);
    card.appendChild(left);
    card.appendChild(actions);
    wrap.appendChild(card);
    document.body.appendChild(wrap);
  };

  const ensureUi = () => {
    const existing = getUi();
    if (existing) return existing;
    createFallbackUi();
    return getUi();
  };

  const setVisible = (visible) => {
    const ui = ensureUi();
    if (!ui) return;
    ui.banner.classList.toggle('hidden', !visible);
    ui.banner.style.display = visible ? 'block' : 'none';
    ui.banner.setAttribute('aria-hidden', visible ? 'false' : 'true');
  };

  const showUi = (mode) => {
    const ui = ensureUi();
    if (!ui) return;

    if (mode === 'ios') {
      if (ui.text) ui.text.textContent = 'On iPhone/iPad: tap Share → Add to Home Screen.';
      if (ui.action) {
        ui.action.textContent = 'Got it';
        ui.action.disabled = false;
      }
    } else if (mode === 'manual') {
      if (ui.text) ui.text.textContent = 'To install: open the browser menu and choose “Install app”.';
      if (ui.action) {
        ui.action.textContent = 'Got it';
        ui.action.disabled = false;
      }
    } else {
      if (ui.text) ui.text.textContent = 'Get faster access and offline support.';
      if (ui.action) {
        ui.action.textContent = 'Install';
        ui.action.disabled = false;
      }
    }

    setVisible(true);
    localStorage.setItem(STORAGE.lastShownAt, String(now()));
  };

  const hideUi = () => {
    setVisible(false);
  };

  const dismiss = () => {
    localStorage.setItem(STORAGE.dismissedUntil, String(now() + CONFIG.remindAfterDismissMs));
    hideUi();
  };

  let deferredPrompt = null;
  let handlersAttached = false;
  let sawBeforeInstallPrompt = false;

  const attachHandlers = () => {
    if (handlersAttached) return;
    const ui = ensureUi();
    if (!ui) return;

    if (ui.later) ui.later.addEventListener('click', dismiss);

    if (ui.action) {
      ui.action.addEventListener('click', async () => {
        if (isIos()) {
          dismiss();
          return;
        }

        if (!deferredPrompt) {
          dismiss();
          return;
        }

        ui.action.disabled = true;
        try {
          deferredPrompt.prompt();
          const choice = await deferredPrompt.userChoice;
          deferredPrompt = null;
          if (choice && choice.outcome === 'accepted') {
            localStorage.setItem(STORAGE.installed, '1');
            hideUi();
          } else {
            dismiss();
          }
        } catch (_) {
          dismiss();
        } finally {
          ui.action.disabled = false;
        }
      });
    }

    handlersAttached = true;
  };

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    sawBeforeInstallPrompt = true;
    deferredPrompt = e;
    if (!shouldShow()) return;
    attachHandlers();
    window.setTimeout(() => showUi('android'), CONFIG.showDelayMs);
  });

  window.addEventListener('appinstalled', () => {
    localStorage.setItem(STORAGE.installed, '1');
    deferredPrompt = null;
    hideUi();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState !== 'visible') return;
    if (!shouldShow()) return;
    if (isIos() && !isStandalone()) {
      attachHandlers();
      window.setTimeout(() => showUi('ios'), CONFIG.showDelayMs);
    }
  });

  const registerSw = async () => {
    if (!('serviceWorker' in navigator)) return;
    try {
      await navigator.serviceWorker.register('/service-worker.js', { scope: '/' });
    } catch (_) {}
  };

  registerSw();

  if (shouldShow() && !isIos() && !isStandalone()) {
    window.setTimeout(() => {
      if (!shouldShow()) return;
      if (sawBeforeInstallPrompt) return;
      if (deferredPrompt) return;
      attachHandlers();
      showUi('manual');
    }, CONFIG.manualFallbackDelayMs);
  }

  if (shouldShow() && isIos() && !isStandalone()) {
    window.setTimeout(() => {
      attachHandlers();
      showUi('ios');
    }, CONFIG.showDelayMs);
  }
})();
