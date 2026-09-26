/*
 * Layout 3: vertical accordion sidebar with a compact/rail toggle.
 *
 * Expanded mode needs no JS at all here — it's the app's existing accordion behavior
 * (menu_click() in script.js) completely unchanged. This file only handles:
 *   1. The compact/expanded toggle switch (persisted in localStorage, ephemeral like the
 *      other layouts' own collapse state — never sent to the server).
 *   2. Stripping the inline display:none menu_click() stamps on every submenu at load, once,
 *      when entering compact mode, so the pure-CSS :hover flyout rules in layout-3.css can
 *      take over (an inline style always outranks a stylesheet rule, same gotcha as in
 *      Layouts 1/2, but no cloning is involved here — this acts on the live elements).
 *   3. Click-to-expand for nested (2nd-level+) branches inside an open flyout, so arbitrarily
 *      deep real nav data is reachable without stacking further hover flyouts.
 *
 * No-ops entirely unless body[data-pc-layout="layout-3"] is active.
 */
(function () {
    var STORAGE_KEY = 'erp:layout3:compact';

    function isActiveLayout() {
        return document.body.getAttribute('data-pc-layout') === 'layout-3';
    }

    function stripInlineDisplay(scopeEl) {
        scopeEl.querySelectorAll('.pc-submenu').forEach(function (el) {
            el.style.removeProperty('display');
        });
    }

    function init() {
        if (!isActiveLayout()) return;

        var sidebar = document.querySelector('nav.pc-sidebar');
        var railList = sidebar && sidebar.querySelector('.pc-navbar');
        var toggle = document.getElementById('layout3CompactToggle');
        if (!sidebar || !railList || !toggle) return;

        function isCompact() {
            return document.body.classList.contains('layout3-compact');
        }

        function setCompact(next) {
            document.body.classList.toggle('layout3-compact', next);
            toggle.setAttribute('aria-pressed', next ? 'true' : 'false');
            try {
                localStorage.setItem(STORAGE_KEY, next ? '1' : '0');
            } catch (e) {}
            if (next) {
                stripInlineDisplay(railList);
            }
        }

        toggle.addEventListener('click', function () {
            setCompact(!isCompact());
        });

        var topItems = Array.prototype.slice.call(railList.children).filter(function (el) {
            return el.tagName === 'LI' && el.classList.contains('pc-item');
        });

        // The flyout is positioned via inline top/left (computed from the hovered icon's
        // actual position) rather than a CSS-only offset, because .navbar-content is wrapped
        // by SimpleBar, whose internal overflow:hidden wrapper clips regular
        // position:absolute content — position:fixed escapes that, but has no equivalent to
        // "left: 100% of parent", hence doing this in JS instead of pure CSS.
        topItems.forEach(function (li) {
            if (!li.classList.contains('pc-hasmenu')) return;
            var submenu = li.querySelector(':scope > .pc-submenu');
            if (!submenu) return;

            li.addEventListener('mouseenter', function () {
                if (!isCompact()) return;
                var rect = li.getBoundingClientRect();
                submenu.style.position = 'fixed';
                submenu.style.top = rect.top + 'px';
                submenu.style.left = rect.right + 'px';
                submenu.classList.add('layout3-flyout-open');
            });

            li.addEventListener('mouseleave', function () {
                submenu.classList.remove('layout3-flyout-open');
                submenu.style.removeProperty('position');
                submenu.style.removeProperty('top');
                submenu.style.removeProperty('left');
            });
        });

        railList.addEventListener('click', function (ev) {
            if (!isCompact()) return;
            var link = ev.target.closest('a.pc-link');
            if (!link) return;
            var li = link.parentElement;
            if (!li || !li.classList.contains('pc-hasmenu')) return;

            ev.preventDefault();
            ev.stopPropagation();

            if (li.parentElement === railList) return; // top-level: hover already reveals the flyout

            var childSubmenu = li.querySelector(':scope > .pc-submenu');
            if (!childSubmenu) return;

            var isOpen = li.classList.contains('pc-trigger');
            li.classList.toggle('pc-trigger', !isOpen);
            childSubmenu.style.display = isOpen ? 'none' : 'block';
        });

        var stored = null;
        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch (e) {}
        setCompact(stored === '1');
    }

    document.addEventListener('DOMContentLoaded', init);
})();
