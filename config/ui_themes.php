<?php

return [
    /*
     * The theme/preset applied when a user has no personal preference saved,
     * and the id every unavailable placeholder theme falls back to.
     */
    'default_theme' => 'preset-1',

    /*
     * The layout applied when a user has no personal preference saved. Matches the
     * Able Pro template's own data-pc-layout="vertical" value used across the app today.
     */
    'default_layout' => 'vertical',

    /*
     * Selectable color themes. 'preset-1' is today's live look and is never removed and never
     * gets a 'variables' override (it IS the current look, nothing to override). preset-12..15
     * correspond 1:1 to the ERP_Phase2 prototype's theme-1..4 — hex values copied verbatim from
     * that prototype's src/theme/themes.ts (swatches) and src/styles/main.scss (variables).
     *
     * 'variables' is the single source of truth for each non-default theme's palette, consumed
     * two ways: public/assets/css/theme-presets.css hand-mirrors these same values as
     * [data-pc-preset=preset-N] blocks (for instant client-side preview when toggling in the
     * customizer, and as the values that actually apply once data-pc-preset is set), and
     * layouts/_partials/_head.blade.php reads this array directly to emit the server-side
     * "user-theme-override" <style> block (so a user's personal theme choice renders correctly
     * on first paint, no flash, and takes precedence over org branding — see that file).
     */
    'themes' => [
        'preset-1' => [
            'id' => 'preset-1',
            'name' => 'Current',
            'description' => 'Default BRERP look',
            'available' => true,
            'swatches' => ['#2e3192', '#7c4dff', '#f4f5fa'],
            'variables' => [],
        ],
        'preset-12' => [
            'id' => 'preset-12',
            'name' => 'Violet',
            'description' => 'Primary Gull inspired',
            'available' => true,
            'swatches' => ['#401a57', '#7a1bb3', '#f6f3fb'],
            'variables' => [
                '--bs-body-bg' => '#f9f9fb',
                '--bs-body-bg-rgb' => '249, 249, 251',
                '--pc-heading-color' => '#23182d',
                '--pc-active-background' => '#f6f4fb',
                '--pc-sidebar-background' => '#2c163e',
                '--pc-sidebar-color' => '#f5ecff',
                '--pc-sidebar-color-rgb' => '245, 236, 255',
                '--pc-sidebar-active-color' => '#663399',
                '--pc-sidebar-shadow' => '0 8px 24px rgba(35, 21, 49, 0.18)',
                '--pc-sidebar-caption-color' => '#c4a6dc',
                '--pc-sidebar-border' => '1px solid rgba(255, 255, 255, 0.08)',
                '--pc-sidebar-user-background' => 'rgba(255, 255, 255, 0.06)',
                '--pc-header-background' => 'rgba(249, 249, 251, 0.85)',
                '--pc-header-color' => '#23182d',
                '--pc-header-shadow' => 'none',
                '--pc-card-box-shadow' => '0 4px 20px 1px rgba(0, 0, 0, 0.06), 0 1px 4px rgba(0, 0, 0, 0.08)',
                '--pc-header-submenu-background' => '#fff',
                '--pc-header-submenu-color' => '#23182d',
                '--bs-primary' => '#663399',
                '--bs-primary-rgb' => '102, 51, 153',
                '--bs-link-color' => '#663399',
                '--bs-link-color-rgb' => '102, 51, 153',
            ],
        ],
        'preset-13' => [
            'id' => 'preset-13',
            'name' => 'Ocean',
            'description' => 'Ocean contrast',
            'available' => true,
            'swatches' => ['#073a3a', '#0f8f9f', '#e7fbfc'],
            'variables' => [
                '--bs-body-bg' => '#edf6f7',
                '--bs-body-bg-rgb' => '237, 246, 247',
                '--pc-heading-color' => '#132a30',
                '--pc-active-background' => '#ebf8fa',
                '--pc-sidebar-background' => '#0d3d42',
                '--pc-sidebar-color' => '#e8fcff',
                '--pc-sidebar-color-rgb' => '232, 252, 255',
                '--pc-sidebar-active-color' => '#0f8f9f',
                '--pc-sidebar-shadow' => '0 16px 36px rgba(13, 61, 66, 0.22)',
                '--pc-sidebar-caption-color' => '#96d2db',
                '--pc-sidebar-border' => '1px solid rgba(255, 255, 255, 0.08)',
                '--pc-sidebar-user-background' => 'rgba(255, 255, 255, 0.06)',
                '--pc-header-background' => 'rgba(237, 246, 247, 0.85)',
                '--pc-header-color' => '#132a30',
                '--pc-header-shadow' => 'none',
                '--pc-card-box-shadow' => '0 8px 24px rgba(13, 61, 66, 0.08)',
                '--pc-header-submenu-background' => '#fff',
                '--pc-header-submenu-color' => '#132a30',
                '--bs-primary' => '#0f8f9f',
                '--bs-primary-rgb' => '15, 143, 159',
                '--bs-link-color' => '#0f8f9f',
                '--bs-link-color-rgb' => '15, 143, 159',
            ],
        ],
        'preset-14' => [
            'id' => 'preset-14',
            'name' => 'Amber',
            'description' => 'Amber graphite',
            'available' => true,
            'swatches' => ['#272315', '#bb7d00', '#fff7e2'],
            'variables' => [
                '--bs-body-bg' => '#f8f6f1',
                '--bs-body-bg-rgb' => '248, 246, 241',
                '--pc-heading-color' => '#2f2a20',
                '--pc-active-background' => '#fcf7ea',
                '--pc-sidebar-background' => '#322d1f',
                '--pc-sidebar-color' => '#fff5dc',
                '--pc-sidebar-color-rgb' => '255, 245, 220',
                '--pc-sidebar-active-color' => '#bb7d00',
                '--pc-sidebar-shadow' => '0 16px 36px rgba(51, 45, 31, 0.22)',
                '--pc-sidebar-caption-color' => '#efce86',
                '--pc-sidebar-border' => '1px solid rgba(255, 255, 255, 0.08)',
                '--pc-sidebar-user-background' => 'rgba(255, 255, 255, 0.06)',
                '--pc-header-background' => 'rgba(248, 246, 241, 0.85)',
                '--pc-header-color' => '#2f2a20',
                '--pc-header-shadow' => 'none',
                '--pc-card-box-shadow' => '0 8px 24px rgba(51, 45, 31, 0.08)',
                '--pc-header-submenu-background' => '#fff',
                '--pc-header-submenu-color' => '#2f2a20',
                '--bs-primary' => '#bb7d00',
                '--bs-primary-rgb' => '187, 125, 0',
                '--bs-link-color' => '#bb7d00',
                '--bs-link-color-rgb' => '187, 125, 0',
            ],
        ],
        'preset-15' => [
            'id' => 'preset-15',
            'name' => 'Crimson',
            'description' => 'Crimson fog',
            'available' => true,
            'swatches' => ['#3c1020', '#d13867', '#fff0f5'],
            'variables' => [
                '--bs-body-bg' => '#faf0f4',
                '--bs-body-bg-rgb' => '250, 240, 244',
                '--pc-heading-color' => '#301823',
                '--pc-active-background' => '#fff3f8',
                '--pc-sidebar-background' => '#411727',
                '--pc-sidebar-color' => '#ffeef5',
                '--pc-sidebar-color-rgb' => '255, 238, 245',
                '--pc-sidebar-active-color' => '#d13867',
                '--pc-sidebar-shadow' => '0 16px 36px rgba(65, 23, 39, 0.24)',
                '--pc-sidebar-caption-color' => '#efafc4',
                '--pc-sidebar-border' => '1px solid rgba(255, 255, 255, 0.08)',
                '--pc-sidebar-user-background' => 'rgba(255, 255, 255, 0.06)',
                '--pc-header-background' => 'rgba(250, 240, 244, 0.85)',
                '--pc-header-color' => '#301823',
                '--pc-header-shadow' => 'none',
                '--pc-card-box-shadow' => '0 8px 24px rgba(65, 23, 39, 0.08)',
                '--pc-header-submenu-background' => '#fff',
                '--pc-header-submenu-color' => '#301823',
                '--bs-primary' => '#d13867',
                '--bs-primary-rgb' => '209, 56, 103',
                '--bs-link-color' => '#d13867',
                '--bs-link-color-rgb' => '209, 56, 103',
            ],
        ],
    ],

    /*
     * Selectable sidebar/navigation layouts. 'vertical' is today's live layout and is
     * never removed. layout-1..4 correspond 1:1 to the ERP_Phase2 prototype's
     * Layout 1..4 and stay `available => false` until each is built out in Phase B.
     */
    'layouts' => [
        'vertical' => [
            'id' => 'vertical',
            'name' => 'Current',
            'description' => 'Default BRERP sidebar',
            'available' => true,
        ],
        'layout-1' => [
            'id' => 'layout-1',
            'name' => 'Layout 1',
            'description' => 'Compact rail + secondary sidebar',
            'available' => true,
        ],
        'layout-2' => [
            'id' => 'layout-2',
            'name' => 'Layout 2',
            'description' => 'Large sidebar with labels',
            'available' => true,
        ],
        'layout-3' => [
            'id' => 'layout-3',
            'name' => 'Layout 3',
            'description' => 'Vertical accordion sidebar',
            'available' => true,
        ],
        'layout-4' => [
            // Uses a custom id, like layout-1/2/3 — NOT the vendor's literal "horizontal"
            // value. That was tried first (to reuse style.css's dormant horizontal-mode CSS
            // directly) but live testing found it's a reserved keyword: this app's own
            // localStorage['layout'] sync (added in Phase A) causes script.js's setLayout()
            // to dynamically re-inject the vendor's own layout-horizontal.js the instant it
            // sees that literal string, regardless of what's actually included in Blade — and
            // that script was separately found to silently drop overflowing top-level items
            // (BRERP's nav has no .pc-caption elements, an edge case its logic doesn't
            // handle). See public/assets/js/layout-4.js and public/assets/css/layout-4.css.
            'id' => 'layout-4',
            'name' => 'Layout 4',
            'description' => 'Horizontal top navigation bar',
            'available' => true,
        ],
    ],
];
