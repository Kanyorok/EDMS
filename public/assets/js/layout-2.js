/*
 * Layout 2: large labeled primary sidebar + a secondary flyout panel that slides in over the
 * main content (rather than reserving permanent space like Layout 1's persistent panel).
 *
 * Same data source and constraints as layout-1.js: everything shown here comes from the exact
 * nested <li class="pc-item">/<ul class="pc-submenu"> markup ModuleService::generateNavbar()
 * already rendered (real, permission-filtered nav data) — this script only rearranges/clones
 * what's already in the DOM, never re-derives nav content.
 *
 * No-ops entirely unless body[data-pc-layout="layout-2"] is active.
 */
(function () {
    function isActiveLayout() {
        return document.body.getAttribute('data-pc-layout') === 'layout-2';
    }

    function normalizePath(p) {
        try {
            return new URL(p, location.href).pathname.replace(/\/+$|^\/+/g, '/');
        } catch (e) {
            return ('' + p).replace(/\/+$/, '');
        }
    }

    function collectRoutePaths(scopeEl) {
        var paths = [];
        scopeEl.querySelectorAll('a[href]').forEach(function (a) {
            var href = a.getAttribute('href');
            if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) return;
            paths.push(normalizePath(href));
        });
        return paths;
    }

    function findActiveTopLevelItem(topItems) {
        var current = normalizePath(location.pathname);
        var best = null;
        var bestLen = -1;

        topItems.forEach(function (li) {
            collectRoutePaths(li).forEach(function (p) {
                if (p === current) {
                    best = li;
                    bestLen = Infinity;
                } else if (bestLen !== Infinity && p !== '/' && current.indexOf(p) === 0 && p.length > bestLen) {
                    best = li;
                    bestLen = p.length;
                }
            });
        });

        if (best) return best;

        return topItems.filter(function (li) { return li.classList.contains('pc-hasmenu'); })[0] || topItems[0] || null;
    }

    function init() {
        if (!isActiveLayout()) return;

        var sidebar = document.querySelector('nav.pc-sidebar');
        var railList = sidebar && sidebar.querySelector('.pc-navbar');
        var panel = document.getElementById('layout2SecondaryPanel');
        var panelTitle = document.getElementById('layout2SecondaryTitle');
        var panelBody = document.getElementById('layout2SecondaryBody');
        if (!railList || !panel || !panelTitle || !panelBody) return;

        var topItems = Array.prototype.slice.call(railList.children).filter(function (el) {
            return el.tagName === 'LI' && el.classList.contains('pc-item');
        });

        var activeItem = findActiveTopLevelItem(topItems);
        var hoveredItem = null;

        function updateRailVisualState() {
            var visible = hoveredItem || activeItem;
            topItems.forEach(function (li) {
                li.classList.toggle('layout2-active', li === activeItem);
                li.classList.toggle('layout2-hovered', li === visible && li !== activeItem);
            });
        }

        function renderPanel() {
            var item = hoveredItem || activeItem;
            updateRailVisualState();

            var submenu = item ? item.querySelector(':scope > .pc-submenu') : null;

            if (!submenu) {
                panel.classList.remove('open');
                panelTitle.textContent = '';
                panelBody.innerHTML = '';
                return;
            }

            panel.classList.add('open');

            var labelEl = item.querySelector(':scope > a.pc-link > .pc-mtext');
            panelTitle.textContent = labelEl ? labelEl.textContent.trim() : '';

            panelBody.innerHTML = '';
            var clone = submenu.cloneNode(true);
            // See layout-1.js for why this is necessary: menu_click() stamps every non-active
            // submenu with an inline display:none, which cloneNode copies verbatim and which
            // outranks any stylesheet rule.
            clone.style.display = 'block';
            panelBody.appendChild(clone);

            if (window.feather && typeof window.feather.replace === 'function') {
                try {
                    window.feather.replace();
                } catch (e) {}
            }
        }

        topItems.forEach(function (li) {
            li.addEventListener('mouseenter', function () {
                hoveredItem = li;
                renderPanel();
            });

            if (li.classList.contains('pc-hasmenu')) {
                var link = li.querySelector(':scope > a.pc-link');
                if (link) {
                    link.addEventListener('click', function (ev) {
                        ev.preventDefault();
                        ev.stopPropagation();
                        activeItem = li;
                        hoveredItem = li;
                        renderPanel();
                    });
                }
            }
        });

        // Bound to the whole sidebar (primary + flyout, since the flyout is nested inside it),
        // not just the rail — otherwise the cursor crossing from a primary item into the
        // adjacent flyout would fire this mid-transit and collapse the panel immediately.
        sidebar.addEventListener('mouseleave', function () {
            hoveredItem = null;
            renderPanel();
        });

        panelBody.addEventListener('click', function (ev) {
            var link = ev.target.closest('a.pc-link');
            if (!link || !panelBody.contains(link)) return;
            var li = link.parentElement;

            if (li && li.classList.contains('pc-hasmenu')) {
                ev.preventDefault();
                ev.stopPropagation();

                var childSubmenu = li.querySelector(':scope > .pc-submenu');
                if (!childSubmenu) return;

                var isOpen = li.classList.contains('pc-trigger');
                li.classList.toggle('pc-trigger', !isOpen);
                childSubmenu.style.display = isOpen ? 'none' : 'block';
                return;
            }

            // Leaf link: commit the previewed section as active before the AJAX nav (or full
            // navigation) actually leaves, so the flyout doesn't flicker back to whatever was
            // active before once the mouse leaves the sidebar mid-navigation.
            activeItem = hoveredItem || activeItem;
        });

        renderPanel();

        document.addEventListener('partial:loaded', function () {
            if (!isActiveLayout()) return;
            activeItem = findActiveTopLevelItem(topItems);
            hoveredItem = null;
            renderPanel();
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
