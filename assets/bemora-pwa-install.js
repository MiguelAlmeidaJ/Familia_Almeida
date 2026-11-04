(() => {
  'use strict';
  const manifest = document.createElement('link');
  manifest.rel = 'manifest';
  manifest.href = '/manifest.webmanifest';
  if (!document.querySelector('link[rel="manifest"]')) document.head.appendChild(manifest);

  if (!document.querySelector('meta[name="theme-color"]')) {
    const theme = document.createElement('meta');
    theme.name = 'theme-color';
    theme.content = '#06463F';
    document.head.appendChild(theme);
  }
  if (!document.querySelector('link[rel="apple-touch-icon"]')) {
    const icon = document.createElement('link');
    icon.rel = 'apple-touch-icon';
    icon.href = '/assets/brand/favicon.png';
    document.head.appendChild(icon);
  }
  if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
  }
  let deferredPrompt = null;
  const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (isStandalone()) return;
  if (!document.querySelector('.ref-sidebar')) return;

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'bemora-pwa-install';
  button.textContent = 'Instalar Bemora';
  button.setAttribute('aria-label', 'Instalar Bemora neste dispositivo');
  button.hidden = true;
  Object.assign(button.style, {
    border: '1px solid #b8d8d0', background: '#eaf5f1', color: '#06463F',
    padding: '9px 13px', borderRadius: '12px', fontSize: '13px',
    fontWeight: '600', cursor: 'pointer', width: '100%', marginTop: '10px'
  });
  const sidebar = document.querySelector('.ref-sidebar');
  const nav = sidebar.querySelector('.side-nav');
  if (nav) nav.insertAdjacentElement('afterend', button);
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredPrompt = event;
    button.hidden = false;
  });
  button.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    const prompt = deferredPrompt;
    deferredPrompt = null;
    button.hidden = true;
    await prompt.prompt();
  });
  window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    button.hidden = true;
  });
})();