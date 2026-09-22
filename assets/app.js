(() => {
  const root = document.documentElement;
  const shell = document.querySelector('.shell');
  const desktopToggle = document.getElementById('sidebarToggle');
  const mobileToggle = document.getElementById('mobileSidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const storageKey = 'familia-sidebar-collapsed';

  function setCollapsed(collapsed) {
    root.classList.toggle('sidebar-collapsed', collapsed);
    if (shell) shell.classList.toggle('sidebar-collapsed', collapsed);
    try { localStorage.setItem(storageKey, collapsed ? '1' : '0'); } catch (_) {}
    if (desktopToggle) desktopToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  }

  function setMobileOpen(open) {
    root.classList.toggle('sidebar-mobile-open', open);
    if (mobileToggle) mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  try {
    setCollapsed(localStorage.getItem(storageKey) === '1');
  } catch (_) {}

  desktopToggle?.addEventListener('click', () => {
    setCollapsed(!root.classList.contains('sidebar-collapsed'));
  });

  mobileToggle?.addEventListener('click', () => {
    setMobileOpen(!root.classList.contains('sidebar-mobile-open'));
  });

  overlay?.addEventListener('click', () => setMobileOpen(false));

  document.querySelectorAll('.side-nav a').forEach((link) => {
    link.addEventListener('click', () => setMobileOpen(false));
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth > 760) setMobileOpen(false);
  });
})();