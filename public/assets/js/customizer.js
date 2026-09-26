/*
 * Thin glue between the customizer panel and:
 *  - the template's own dormant preset_change()/main_layout_change() (theme.js/script.js)
 *    for instant visual preview
 *  - the server-side UI preference endpoint, so the choice survives reloads
 *
 * Layout changes are saved permanently to the user's account and reload the page (matches
 * the template's own dormant layout-switcher behavior in script.js). Theme changes apply
 * instantly (no reload) and are saved to the session only, not the user's account — they
 * override the org Color Scheme for the rest of this login, then reset to the org default
 * on next login.
 */
(function () {
    function postPreferences(payload) {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.content : '';

        return fetch('/settings/ui-preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (!res.ok) {
                throw new Error('Failed to save UI preference');
            }
            return res.json();
        });
    }

    function markSelected(group, selectedEl) {
        if (!group) return;
        var current = group.querySelectorAll('.customizer-option.selected');
        for (var i = 0; i < current.length; i++) {
            current[i].classList.remove('selected');
            current[i].setAttribute('aria-selected', 'false');
        }
        selectedEl.classList.add('selected');
        selectedEl.setAttribute('aria-selected', 'true');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.getElementById('customizerPanel');
        if (!panel) return;

        var themeGroup = panel.querySelector('.customizer-theme-options');
        var themeButtons = panel.querySelectorAll('.customizer-theme-option:not([disabled])');
        for (var t = 0; t < themeButtons.length; t++) {
            themeButtons[t].addEventListener('click', function (event) {
                var btn = event.currentTarget;
                var themeId = btn.getAttribute('data-theme-id');
                if (!themeId) return;

                // Match the server-rendered override: organization branding also uses
                // !important, so changing only data-pc-preset cannot override its colors.
                var variables = JSON.parse(btn.getAttribute('data-theme-variables') || '{}');
                var staleOverride = document.getElementById('user-theme-override');
                if (staleOverride) staleOverride.remove();

                document.body.setAttribute('data-pc-preset', themeId);
                if (typeof window.preset_change === 'function') {
                    window.preset_change(themeId);
                }
                if (Object.keys(variables).length) {
                    var override = document.createElement('style');
                    override.id = 'user-theme-override';
                    document.head.appendChild(override);
                    override.sheet.insertRule(':root, body {}', 0);
                    var declarations = override.sheet.cssRules[0].style;
                    Object.keys(variables).forEach(function (name) {
                        declarations.setProperty(name, variables[name], 'important');
                    });
                }
                markSelected(themeGroup, btn);

                postPreferences({ theme_id: themeId }).catch(function () {
                    // Non-fatal: the visual preview already applied; persistence can be retried on the next change.
                });
            });
        }

        var layoutButtons = panel.querySelectorAll('.customizer-layout-option:not([disabled])');
        for (var l = 0; l < layoutButtons.length; l++) {
            layoutButtons[l].addEventListener('click', function (event) {
                var btn = event.currentTarget;
                var layoutId = btn.getAttribute('data-layout-id');
                if (!layoutId) return;

                btn.disabled = true;
                postPreferences({ layout_id: layoutId })
                    .then(function () {
                        try {
                            localStorage.setItem('layout', layoutId);
                        } catch (e) {}
                        window.location.reload();
                    })
                    .catch(function () {
                        btn.disabled = false;
                    });
            });
        }
    });
})();
