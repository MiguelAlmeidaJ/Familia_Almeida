(() => {
  const root = document.documentElement;
  const shell = document.querySelector('.shell');
  const desktopToggle = document.getElementById('sidebarToggle');
  const mobileToggle = document.getElementById('mobileSidebarToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const storageKey = 'familia-sidebar-collapsed';
  const mobileBreakpoint = 900;

  let desktopCollapsed = false;

  try {
    desktopCollapsed = localStorage.getItem(storageKey) === '1';
  } catch (_) {}

  function applyCollapsed(collapsed, persist = false) {
    root.classList.toggle('sidebar-collapsed', collapsed);
    shell?.classList.toggle('sidebar-collapsed', collapsed);

    if (persist) {
      desktopCollapsed = collapsed;
      try {
        localStorage.setItem(storageKey, collapsed ? '1' : '0');
      } catch (_) {}
    }

    desktopToggle?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  }

  function setMobileOpen(open) {
    root.classList.toggle('sidebar-mobile-open', open);
    mobileToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (open) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.removeProperty('overflow');
    }
  }

  function syncViewport() {
    const mobile = window.innerWidth <= mobileBreakpoint;

    if (mobile) {
      // O estado recolhido do desktop nunca deve esconder os textos
      // dentro do menu lateral móvel.
      applyCollapsed(false, false);
      return;
    }

    setMobileOpen(false);
    applyCollapsed(desktopCollapsed, false);
  }

  desktopToggle?.addEventListener('click', () => {
    if (window.innerWidth <= mobileBreakpoint) return;
    applyCollapsed(!root.classList.contains('sidebar-collapsed'), true);
  });

  mobileToggle?.addEventListener('click', () => {
    setMobileOpen(!root.classList.contains('sidebar-mobile-open'));
  });

  overlay?.addEventListener('click', () => setMobileOpen(false));

  document.querySelectorAll('.side-nav a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= mobileBreakpoint) {
        setMobileOpen(false);
      }
    });
  });

  window.addEventListener('resize', syncViewport);
  window.addEventListener('orientationchange', () => setTimeout(syncViewport, 80));

  syncViewport();
})();