/*
 * Layout 1: compact icon rail + secondary labeled sidebar panel.
 *
 * Everything shown here comes from the exact nested <li class="pc-item">/<ul class="pc-submenu">
 * markup ModuleService::generateNavbar() already rendered into .pc-navbar (real, permission-
 * filtered nav data) — this script never re-derives nav content, it only rearranges/clones
 * what's already in the DOM: one icon per top-level section on the rail, and the hovered-or-
 * active section's real submenu tree (however many levels deep) cloned into the secondary
 * panel, which is nested inside .pc-sidebar so it inherits the app's existing AJAX partial-nav
 * click handling for free.
 *
 * No-ops entirely unless body[data-pc-layout="layout-1"] is active.
 */
(function () {
    function isActiveLayout() {
        return document.body.getAttribute('data-pc-layout') === 'layout-1';
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
        var panelTitle = document.getElementById('layout1SecondaryTitle');
        var panelBody = document.getElementById('layout1SecondaryBody');
        if (!railList || !panelTitle || !panelBody) return;

        var topItems = Array.prototype.slice.call(railList.children).filter(function (el) {
            return el.tagName === 'LI' && el.classList.contains('pc-item');
        });

        var activeItem = findActiveTopLevelItem(topItems);
        var hoveredItem = null;

        function updateRailVisualState() {
            var visible = hoveredItem || activeItem;
            topItems.forEach(function (li) {
                li.classList.toggle('layout1-active', li === activeItem);
                li.classList.toggle('layout1-hovered', li === visible && li !== activeItem);
            });
        }

        function renderPanel() {
            var item = hoveredItem || activeItem;
            updateRailVisualState();
            panelBody.innerHTML = '';

            if (!item) {
                panelTitle.textContent = '';
                return;
            }

            var labelEl = item.querySelector(':scope > a.pc-link > .pc-mtext');
            panelTitle.textContent = labelEl ? labelEl.textContent.trim() : '';

            var submenu = item.querySelector(':scope > .pc-submenu');
            if (submenu) {
                var clone = submenu.cloneNode(true);
                // The live nav's menu_click() stamps every non-active submenu with an inline
                // display:none (copied verbatim by cloneNode) so it can animate open/closed on
                // the real rail — that inline style outranks any stylesheet rule. The panel's
                // root-level content should always be visible regardless of that state, so
                // clear it explicitly here; nested branches keep their inherited collapsed
                // state and are toggled by this panel's own click handler below.
                clone.style.display = 'block';
                panelBody.appendChild(clone);
                if (window.feather && typeof window.feather.replace === 'function') {
                    try {
                        window.feather.replace();
                    } catch (e) {}
                }
            } else {
                var empty = document.createElement('p');
                empty.className = 'layout1-secondary-empty';
                empty.textContent = 'No items in this section.';
                panelBody.appendChild(empty);
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
                        // Committing a section is this rail's job; block the legacy accordion
                        // toggle (menu_click()) and the AJAX-nav handler from also reacting.
                        ev.preventDefault();
                        ev.stopPropagation();
                        activeItem = li;
                        hoveredItem = li;
                        renderPanel();
                    });
                }
            }
            // Leaf top-level items (no children, e.g. Home) get no extra handling here —
            // their click already navigates normally via the existing AJAX-nav listener.
        });

        // Listens on the whole sidebar (rail + secondary panel, since the panel is nested
        // inside it), not just the rail — otherwise the cursor crossing from a rail icon into
        // the adjacent panel would fire this every time and immediately revert the preview.
        sidebar.addEventListener('mouseleave', function () {
            hoveredItem = null;
            renderPanel();
        });

        // Nested branches inside the cloned tree expand/collapse on click. This manages the
        // submenu's inline display itself (matching what the vendor's slideUp()/slideDown()
        // ultimately do to the live rail) rather than relying on the .pc-trigger stylesheet
        // rule, since an inline style copied over by cloneNode always outranks it. Scoped to
        // this clone only — never touches the live (hidden) rail submenu.
        panelBody.addEventListener('click', function (ev) {
            var link = ev.target.closest('a.pc-link');
            if (!link || !panelBody.contains(link)) return;
            var li = link.parentElement;

            if (!li || !li.classList.contains('pc-hasmenu')) {
                // Leaf link: commit the previewed section as active before the AJAX nav
                // actually leaves, so the panel doesn't revert to a stale section if the
                // mouse leaves the sidebar mid-navigation.
                activeItem = hoveredItem || activeItem;
                return; // let the existing AJAX-nav listener on .pc-sidebar handle the click
            }

            ev.preventDefault();
            ev.stopPropagation();

            var childSubmenu = li.querySelector(':scope > .pc-submenu');
            if (!childSubmenu) return;

            var isOpen = li.classList.contains('pc-trigger');
            li.classList.toggle('pc-trigger', !isOpen);
            childSubmenu.style.display = isOpen ? 'none' : 'block';
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
