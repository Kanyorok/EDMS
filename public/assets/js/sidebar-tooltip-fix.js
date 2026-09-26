/*
 * Sidebar nav-link tooltips (data-bs-toggle="tooltip", populated from each module's
 * Description column in t_Modules) are initialized once in script.js's own window.load
 * handler, with no placement/boundary options — reported live as sometimes getting stuck
 * open (not dismissing on mouseleave) and, when long, flipping to a position that overlaps
 * sibling sidebar items instead of appearing cleanly to their right.
 *
 * This re-initializes each one with explicit options that rule out the overlap (force
 * "right" placement only, bounded to the full body so Popper never has a reason to flip),
 * and adds a defensive hide-on-mouseleave listener so a tooltip can never persist after the
 * pointer has actually left its trigger, regardless of what caused the original stuck state.
 *
 * Deliberately does not touch script.js's own initialization — just disposes and re-creates
 * each instance with better options, so nothing here depends on understanding the vendor
 * script's exact behavior.
 */
(function () {
    function init() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;

        var triggers = document.querySelectorAll('nav.pc-sidebar [data-bs-toggle="tooltip"]');

        triggers.forEach(function (trigger) {
            var existing = bootstrap.Tooltip.getInstance(trigger);
            if (existing) existing.dispose();

            var instance = new bootstrap.Tooltip(trigger, {
                placement: 'right',
                fallbackPlacements: ['right'],
                boundary: document.body,
                trigger: 'hover focus'
            });

            trigger.addEventListener('mouseleave', function () {
                instance.hide();
            });
        });
    }

    // script.js initializes the original (unconfigured) tooltip instances on window.load —
    // registering the same event here, after script.js's own <script> tag, guarantees this
    // runs afterward regardless of exact load timing.
    window.addEventListener('load', init);

    // Sidebar content itself never changes across the app's own AJAX partial navigation
    // (only #mainBodyContent is swapped), so no re-init is needed on partial:loaded — the
    // window.load pass above covers every sidebar tooltip for the lifetime of the page.
})();
