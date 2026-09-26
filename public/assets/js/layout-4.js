/*
 * Layout 4: horizontal top navigation, no sidebar.
 *
 * Everything shown here comes from the exact <li class="pc-item">/<ul class="pc-submenu">
 * markup ModuleService::generateNavbar() already rendered into .pc-navbar (real, permission-
 * filtered nav data) — this script only rearranges what's already in the DOM (moving real
 * nodes, never cloning/dropping them) and adds dropdown interaction, never re-derives content.
 *
 * Does NOT use the vendor's own companion script (layout-horizontal.js), and deliberately does
 * NOT use the vendor's literal data-pc-layout="horizontal" value either (see layout-4.css for
 * why — that string is a reserved keyword this app's own localStorage['layout'] sync treats
 * specially). Two real bugs found only through live testing led here:
 *   1. layout-horizontal.js silently drops the last few top-level items when the row doesn't
 *      fit and the source markup has no .pc-caption elements (which BRERP's nav never has) —
 *      a real data-loss bug, not just cosmetic.
 *   2. Using the literal "horizontal" attribute value causes script.js's own setLayout() to
 *      dynamically re-inject that same buggy vendor script anyway, regardless of what's
 *      included here, since it's driven by localStorage rather than this page's own markup.
 * This file reimplements only what's actually needed: moving items that don't fit into a
 * "More" item instead of losing them, plus hover dropdowns for top-level items and
 * click-to-expand for nested branches (same technique as layouts 1-3).
 *
 * No-ops entirely unless body[data-pc-layout="layout-4"] is active.
 */
(function () {
    function isActiveLayout() {
        return document.body.getAttribute('data-pc-layout') === 'layout-4';
    }

    function init() {
        if (!isActiveLayout()) return;

        var sidebar = document.querySelector('nav.pc-sidebar');
        var railList = sidebar && sidebar.querySelector('.pc-navbar');
        var navbarContent = sidebar && sidebar.querySelector('.navbar-content');
        if (!sidebar || !railList || !navbarContent) return;

        // Captured once, before anything is moved — the permanent, authoritative order.
        var originalItems = Array.prototype.slice.call(railList.children).filter(function (el) {
            return el.tagName === 'LI' && el.classList.contains('pc-item');
        });

        var moreLi = document.createElement('li');
        moreLi.className = 'pc-item pc-hasmenu layout4-more-item';
        moreLi.innerHTML =
            '<a href="javascript:void(0)" class="pc-link">' +
            '<span class="pc-micon"><i data-feather="more-horizontal"></i></span>' +
            '<span class="pc-mtext">More</span>' +
            '<span class="pc-arrow"><i data-feather="chevron-right"></i></span>' +
            '</a><ul class="pc-submenu"></ul>';
        var moreSubmenu = moreLi.querySelector(':scope > .pc-submenu');

        if (window.feather && typeof window.feather.replace === 'function') {
            try {
                window.feather.replace();
            } catch (e) {}
        }

        // Only fires the hover-dropdown while the item is genuinely sitting in the top-level
        // row (checked live, since relayout() moves items in and out of it on resize) — an
        // item currently living inside the "More" dropdown instead expands inline on click,
        // via the delegated listener below, so the same element never does both.
        //
        // Two things reported as "not working as expected" with hover-only, position:fixed
        // dropdowns rendered below a horizontal bar:
        //   1. There's no DOM-adjacency between the bar item and the dropdown that the browser
        //      can use to treat "moving from one to the other" as a single continuous hover —
        //      the dropdown is a DOM descendant of the <li> but is rendered far below it, so
        //      the cursor crosses a real dead zone (e.g. header padding) on the way down,
        //      firing a genuine mouseleave and snapping the dropdown shut before the user can
        //      reach it. Fixed with a short close delay (hover-intent pattern): mouseleave
        //      schedules a close, but re-entering either the trigger or the dropdown itself
        //      cancels it.
        //   2. Clicking a parent item did nothing at all (no click handler existed for
        //      top-level items, and their href is a no-op) — hover was the only way in, which
        //      isn't discoverable/reliable for every user. Click now pins the dropdown open
        //      (closing any other pinned one first) until clicked again or the user clicks
        //      elsewhere on the page; while pinned, hovering a different item no longer
        //      fights it open.
        var dropdownControllers = [];
        var pinnedLi = null;

        function attachDropdownBehavior(li) {
            var submenu = li.querySelector(':scope > .pc-submenu');
            if (!submenu) return;

            var closeTimer = null;

            function cancelClose() {
                if (closeTimer) {
                    clearTimeout(closeTimer);
                    closeTimer = null;
                }
            }

            function position() {
                var rect = li.getBoundingClientRect();
                var link = li.querySelector(':scope > a.pc-link');
                var linkRect = link ? link.getBoundingClientRect() : rect;
                var targetContainer = navbarWrapper || navbarContent || sidebar;
                var containerRect = targetContainer ? targetContainer.getBoundingClientRect() : linkRect;

                submenu.style.position = 'fixed';
                // Position directly below the navbar-wrapper / navbar-content container
                var topPos = containerRect.bottom;
                submenu.style.top = Math.round(topPos) + 'px';
                submenu.style.left = Math.round(linkRect.left) + 'px';
                submenu.style.marginTop = '0px';

                var subRect = submenu.getBoundingClientRect();
                if (subRect.right > window.innerWidth) {
                    submenu.style.left = Math.max(8, Math.round(linkRect.right - subRect.width)) + 'px';
                }
            }

            function open() {
                if (li.parentElement !== railList) return;
                cancelClose();
                submenu.style.position = 'fixed';
                submenu.classList.add('layout4-dropdown-open');
                position();
            }

            function close() {
                cancelClose();
                submenu.classList.remove('layout4-dropdown-open');
                submenu.style.removeProperty('position');
                submenu.style.removeProperty('top');
                submenu.style.removeProperty('left');
                submenu.style.removeProperty('margin-top');
                if (pinnedLi === li) pinnedLi = null;
            }

            function scheduleClose() {
                if (pinnedLi === li) return; // click-pinned open; only an explicit close hides it
                cancelClose();
                closeTimer = setTimeout(close, 250);
            }

            li.addEventListener('mouseenter', function () {
                if (pinnedLi && pinnedLi !== li) return; // a different item is pinned; don't fight it
                open();
            });
            li.addEventListener('mouseleave', function () {
                if (li.parentElement !== railList) return;
                scheduleClose();
            });

            // The dropdown itself is a DOM child of li (so this doesn't need its own
            // top-level-only guard) — re-entering it from the trigger cancels the pending close.
            submenu.addEventListener('mouseenter', cancelClose);
            submenu.addEventListener('mouseleave', function () {
                if (li.parentElement !== railList) return;
                scheduleClose();
            });

            var link = li.querySelector(':scope > a.pc-link');
            if (link) {
                link.addEventListener('click', function (ev) {
                    if (li.parentElement !== railList) return;
                    ev.preventDefault();
                    ev.stopPropagation();

                    if (pinnedLi === li) {
                        close();
                        return;
                    }
                    dropdownControllers.forEach(function (c) {
                        if (c.li !== li) c.close();
                    });
                    pinnedLi = li;
                    open();
                });
            }

            dropdownControllers.push({ li: li, close: close });
        }

        originalItems.forEach(function (li) {
            if (li.classList.contains('pc-hasmenu')) attachDropdownBehavior(li);
        });
        attachDropdownBehavior(moreLi);

        // Clicking anywhere outside the pinned item (or its dropdown, a DOM descendant of it)
        // closes it — standard expectation once a click has explicitly opened something.
        document.addEventListener('click', function (ev) {
            if (!pinnedLi || pinnedLi.contains(ev.target)) return;
            dropdownControllers.forEach(function (c) {
                if (c.li === pinnedLi) c.close();
            });
        });

        railList.addEventListener('click', function (ev) {
            var link = ev.target.closest('a.pc-link');
            if (!link) return;
            var li = link.parentElement;
            if (!li || !li.classList.contains('pc-hasmenu')) return;
            if (li.parentElement === railList) return; // real top-level item: hover already handles it

            ev.preventDefault();
            ev.stopPropagation();

            var childSubmenu = li.querySelector(':scope > .pc-submenu');
            if (!childSubmenu) return;

            var isOpen = li.classList.contains('pc-trigger');
            li.classList.toggle('pc-trigger', !isOpen);
            childSubmenu.style.display = isOpen ? 'none' : 'block';
        });

        function relayout() {
            // Reset to the authoritative order first, then re-measure from scratch — simpler
            // and more robust than trying to incrementally patch a previous overflow state.
            originalItems.forEach(function (li) {
                railList.appendChild(li);
            });
            if (moreLi.parentElement) moreLi.remove();
            railList.appendChild(moreLi);

            var available = navbarContent.clientWidth;
            var moreWidth = moreLi.getBoundingClientRect().width || 80;
            var used = 0;
            var overflowStart = null;

            for (var i = 0; i < originalItems.length; i++) {
                var w = originalItems[i].getBoundingClientRect().width;
                if (used + w > available - moreWidth) {
                    overflowStart = i;
                    break;
                }
                used += w;
            }

            if (overflowStart === null) {
                moreLi.remove();
                return;
            }

            for (var j = overflowStart; j < originalItems.length; j++) {
                moreSubmenu.appendChild(originalItems[j]);
            }
        }

        relayout();

        var resizeTimer;
        window.addEventListener('resize', function () {
            if (!isActiveLayout()) return;
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(relayout, 150);
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
