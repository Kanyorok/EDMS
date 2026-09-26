// Sidebar state persistence for AblePro pc-navbar
// Keeps clicked item active and ancestors expanded across navigations
// Uses localStorage per user; idempotent restore

export const SidebarState = (() => {
  let cfg = {
    rootSelector: 'nav.pc-sidebar',
    itemSelector: 'li.pc-item',
    linkSelector: 'a.pc-link',
    submenuSelector: '.pc-submenu',
    activeItemClass: 'active',
    expandedItemClass: 'pc-trigger',
    userKey: 'guest',
  };

  const storageKey = () => `erp:sidebar:${cfg.userKey}`;
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  let postClickRestoreTimer = null;

  function read() {
    try {
      return JSON.parse(localStorage.getItem(storageKey()) || '{}');
    } catch {
      return {};
    }
  }

  function write(state) {
    localStorage.setItem(storageKey(), JSON.stringify(state));
  }

  function normalizePath(p) {
    try {
      return new URL(p, location.href).pathname.replace(/\/+$|^\/+/, '/');
    } catch {
      return (`${p}`).replace(/\/+$/, '');
    }
  }

  function getState() {
    const s = read();
    const defaultRoute = window.__DEFAULT_ACTIVE_ROUTE__ || normalizePath(location.pathname);
    return {
      activeRouteId: s.activeRouteId ?? defaultRoute,
      openItemIds: Array.isArray(s.openItemIds) ? s.openItemIds : [],
    };
  }

  function setActive(routeId) {
    const state = getState();
    state.activeRouteId = routeId || null;
    write(state);
  }

  function setOpenItemIds(ids) {
    const state = getState();
    state.openItemIds = Array.from(new Set(ids || []));
    write(state);
  }

  function computeAncestorItemIds(itemEl, root) {
    const ids = new Set();
    // The nested list is the source of truth. data-parent-id is useful metadata,
    // but older cached navbar markup may not contain it on every menu level.
    let current = itemEl?.parentElement?.closest(cfg.itemSelector);
    while (current && current !== root) {
      if (current.matches && current.matches(cfg.itemSelector)) {
        const id = current.dataset.itemId;
        if (id) ids.add(id);
      }
      current = current.parentElement?.closest(cfg.itemSelector);
    }
    return Array.from(ids);
  }

  function directSubmenu(item) {
    return Array.from(item?.children || []).find((child) => child.matches?.(cfg.submenuSelector)) || null;
  }

  function expandItem(item) {
    if (!item) return;
    item.classList.add(cfg.expandedItemClass);

    // AblePro's menu handler animates a clicked leaf through its top-level
    // ancestor and can leave an inline `display: none` behind. Clear those
    // animation styles when restoring the persisted active branch.
    const submenu = directSubmenu(item);
    if (submenu) submenu.removeAttribute('style');
  }

  function findRouteLink(routeId, root) {
    if (!routeId) return null;

    const normalizedRoute = normalizePath(routeId);
    const exact = qa(cfg.linkSelector, root).find((link) => {
      const candidate = link.dataset.routeId || link.dataset.route;
      return candidate && normalizePath(candidate) === normalizedRoute;
    });
    if (exact) return exact;

    return qa(cfg.linkSelector, root)
      .filter((link) => {
        const candidate = link.dataset.routeId || link.dataset.route;
        if (!candidate) return false;
        const path = normalizePath(candidate);
        return path !== '/' && normalizedRoute.startsWith(`${path}/`);
      })
      .sort((a, b) => {
        const aPath = normalizePath(a.dataset.routeId || a.dataset.route);
        const bPath = normalizePath(b.dataset.routeId || b.dataset.route);
        return bPath.length - aPath.length;
      })[0] || null;
  }

  function clearActiveMarks(root) {
    qa(`${cfg.itemSelector}.${cfg.activeItemClass}`, root).forEach((li) => li.classList.remove(cfg.activeItemClass));
    qa(`${cfg.linkSelector}.${cfg.activeItemClass}`, root).forEach((link) => link.classList.remove(cfg.activeItemClass));
  }

  // Scroll helpers: keep the active item visible inside the sidebar
  function getScrollContainer(root) {
    // Prefer inner scroll area if present (AblePro uses .navbar-content)
    return q('.navbar-content', root) || root;
  }

  function isInView(el, container, margin = 64) {
    try {
      const er = el.getBoundingClientRect();
      const cr = (container === document || container === document.body)
        ? { top: 0, bottom: (window.innerHeight || document.documentElement.clientHeight) }
        : container.getBoundingClientRect();
      return er.top >= cr.top + margin && er.bottom <= cr.bottom - margin;
    } catch {
      return true;
    }
  }

  function scrollIntoCenterIfNeeded(el, root) {
    try {
      const container = getScrollContainer(root);
      if (!isInView(el, container)) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
      }
    } catch (error) {
      // Silently ignore scroll errors
    }
  }

  function applyToDom() {
    const root = q(cfg.rootSelector) || document;
    const { activeRouteId, openItemIds } = getState();

    clearActiveMarks(root);

    // Expand saved ancestors
    openItemIds.forEach((id) => {
      const li = q(`${cfg.itemSelector}[data-item-id="${id}"]`, root);
      expandItem(li);
    });

    // Mark active item
    if (activeRouteId) {
      const link = findRouteLink(activeRouteId, root);
      const item = link?.closest(cfg.itemSelector);
      if (link) link.classList.add(cfg.activeItemClass);
      if (item) item.classList.add(cfg.activeItemClass);
      if (item) {
        const ids = computeAncestorItemIds(item, root);
        ids.forEach((id) => {
          const li = q(`${cfg.itemSelector}[data-item-id="${id}"]`, root);
          if (li) {
            li.classList.add(cfg.activeItemClass);
            expandItem(li);
          }
        });
        // After classes are applied, ensure the active link is visible
        if (link) {
          setTimeout(() => scrollIntoCenterIfNeeded(link, root), 0);
        }
      }
    }
  }

  function handleSidebarClick(e) {
    const link = e.target.closest(cfg.linkSelector);
    if (!link) return;

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

    const routeId = link.dataset.routeId || link.dataset.route || normalizePath(href);
    setActive(normalizePath(routeId));

    const item = link.closest(cfg.itemSelector);
    const root = q(cfg.rootSelector) || document;
    const openIds = item ? computeAncestorItemIds(item, root) : [];
    setOpenItemIds(openIds);

    // Restore after the theme's bubbling click handler has finished. It
    // otherwise interprets a leaf click as a click on the top-level trigger.
    queueMicrotask(applyToDom);
    window.clearTimeout(postClickRestoreTimer);
    postClickRestoreTimer = window.setTimeout(applyToDom, 250);
  }

  function bind() {
    const root = q(cfg.rootSelector) || document;
    root.addEventListener('click', handleSidebarClick, true);

    window.addEventListener('popstate', applyToDom);
    document.addEventListener('partial:loaded', applyToDom);
    if (window.htmx) document.body.addEventListener('htmx:afterSwap', applyToDom);
  }

  function init(options = {}) {
    cfg = { ...cfg, ...options };
    // Seed data-route-id where missing
    (q(cfg.rootSelector) || document).querySelectorAll(cfg.linkSelector).forEach((a) => {
      if (!a.dataset.routeId && a.dataset.route) a.dataset.routeId = normalizePath(a.dataset.route);
    });
    applyToDom();
    bind();
  }

  return {
    init, restore: applyToDom, setActive, setOpenItemIds,
  };
})();
