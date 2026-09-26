{{--
    Layout 2: large labeled primary sidebar (the existing .pc-sidebar/.pc-navbar, re-skinned to
    220px icon+label via layout-2.css) + this secondary flyout panel, which slides in over the
    main content (not reserving permanent grid space, unlike Layout 1's persistent panel) to
    preview/commit the hovered-or-active top-level section's real submenu tree.

    Nested inside <nav class="pc-sidebar"> (see app.blade.php) for the same reason as Layout 1's
    panel: it inherits the existing AJAX partial-navigation click handling and .pc-submenu
    styling for free. Populated client-side by public/assets/js/layout-2.js from the nav markup
    ModuleService already rendered — never re-derived here.
--}}
<aside id="layout2SecondaryPanel" class="layout2-secondary-panel" aria-label="Section navigation">
    <div class="layout2-secondary-header">
        <h2 class="layout2-secondary-title" id="layout2SecondaryTitle">&nbsp;</h2>
    </div>
    <div class="layout2-secondary-body" id="layout2SecondaryBody"></div>
</aside>
