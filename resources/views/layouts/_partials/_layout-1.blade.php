{{--
    Layout 1: compact icon rail (the existing .pc-sidebar/.pc-navbar, re-skinned to icon-only
    via layout-1.css) + this secondary labeled panel, which previews/commits the hovered-or-
    active top-level section's real, already permission-filtered submenu tree.

    Nested inside <nav class="pc-sidebar"> (see app.blade.php) so it inherits the existing
    AJAX partial-navigation click handling and .pc-submenu styling for free — everything here
    is populated client-side by public/assets/js/layout-1.js from the nav markup that's
    already rendered by ModuleService, never re-derived.
--}}
<aside id="layout1SecondaryPanel" class="layout1-secondary-panel" aria-label="Section navigation">
    <div class="layout1-secondary-header">
        <h2 class="layout1-secondary-title" id="layout1SecondaryTitle">&nbsp;</h2>
    </div>
    <div class="layout1-secondary-body" id="layout1SecondaryBody"></div>
</aside>
