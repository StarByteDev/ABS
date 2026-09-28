/* ABS V15.2.1 — Admin sidebar state + categorized navigation. */
(() => {
  const STORAGE_STATE = 'abs.admin.sidebar.state';
  const GROUP_PREFIX = 'abs.admin.nav.group.';
  const root = document.documentElement;
  const body = document.querySelector('body.abs-admin-v1521');
  const sidebar = document.querySelector('[data-admin-sidebar]');
  if (!body || !sidebar) return;

  const isMobile = () => window.matchMedia('(max-width: 1000px)').matches;
  const validState = (value) => ['expanded','collapsed','hidden'].includes(value) ? value : 'expanded';
  const getState = () => validState(root.dataset.absAdminSidebar || 'expanded');
  const setState = (state) => {
    state = validState(state);
    root.dataset.absAdminSidebar = state;
    try { localStorage.setItem(STORAGE_STATE, state); } catch (_) {}
    const collapse = document.querySelector('[data-sidebar-collapse]');
    if (collapse) {
      collapse.setAttribute('aria-label', state === 'collapsed' ? 'Expand sidebar' : 'Collapse sidebar');
      const icon = collapse.querySelector('.tool-icon');
      const label = collapse.querySelector('.tool-label');
      if (icon) icon.textContent = state === 'collapsed' ? '⇥' : '⇤';
      if (label) label.textContent = state === 'collapsed' ? 'Expand' : 'Collapse';
    }
  };

  setState(getState());

  document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
    if (isMobile()) return;
    setState(getState() === 'collapsed' ? 'expanded' : 'collapsed');
  });
  document.querySelector('[data-sidebar-hide]')?.addEventListener('click', () => {
    if (isMobile()) return;
    setState('hidden');
  });
  document.querySelector('[data-sidebar-show]')?.addEventListener('click', () => setState('expanded'));

  document.querySelectorAll('[data-nav-group]').forEach((group) => {
    const key = String(group.dataset.navGroup || 'group');
    const toggle = group.querySelector('[data-nav-group-toggle]');
    if (!toggle) return;
    let collapsed = false;
    try { collapsed = localStorage.getItem(GROUP_PREFIX + key) === 'collapsed'; } catch (_) {}
    if (group.classList.contains('is-current')) collapsed = false;
    group.classList.toggle('is-collapsed', collapsed);
    toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    toggle.addEventListener('click', () => {
      if (!isMobile() && getState() === 'collapsed') return;
      const next = !group.classList.contains('is-collapsed');
      group.classList.toggle('is-collapsed', next);
      toggle.setAttribute('aria-expanded', next ? 'false' : 'true');
      try { localStorage.setItem(GROUP_PREFIX + key, next ? 'collapsed' : 'expanded'); } catch (_) {}
    });
  });

  const mobileButton = document.querySelector('[data-admin-menu]');
  const overlay = document.querySelector('[data-admin-overlay]');
  const closeMobile = () => {
    sidebar.classList.remove('open');
    body.classList.remove('admin-nav-open');
    mobileButton?.setAttribute('aria-expanded','false');
  };
  mobileButton?.addEventListener('click', (event) => {
    if (!isMobile()) return;
    event.preventDefault();
    const open = !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', open);
    body.classList.toggle('admin-nav-open', open);
    mobileButton.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  overlay?.addEventListener('click', closeMobile);
  sidebar.addEventListener('click', (event) => { if (isMobile() && event.target.closest('a')) closeMobile(); });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && isMobile()) closeMobile(); });
  window.addEventListener('resize', () => { if (!isMobile()) closeMobile(); });
})();
