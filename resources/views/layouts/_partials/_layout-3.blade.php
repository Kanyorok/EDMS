{{--
    Layout 3: vertical accordion sidebar (expanded mode reuses the app's existing
    menu_click()/slideUp()/slideDown() accordion behavior as-is, just at a distinct width —
    no new JS needed for that mode) with a compact/rail toggle. This is the only new markup
    Layout 3 needs; compact mode's hover flyouts are pure CSS (layout-3.css) driven off the
    live .pc-submenu elements already in .pc-navbar, not a cloned panel like Layouts 1/2.
--}}
<button type="button" id="layout3CompactToggle" class="layout3-compact-toggle"
    aria-pressed="false" aria-label="Toggle compact sidebar">
    <span class="layout3-compact-toggle-track">
        <span class="layout3-compact-toggle-thumb"></span>
    </span>
</button>
