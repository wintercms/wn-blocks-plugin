/**
 * Manages collapsible section state for block form widgets.
 *
 * WinterCMS's core form widget calls bindCollapsibleSections() after every
 * ajaxSuccess event, which re-collapses all [data-field-collapsible] sections.
 * This script counters that in two ways:
 *
 *  1. Sections configured with collapsed: false (data-field-collapsible-open)
 *     are always re-opened after each ajaxSuccess.
 *
 *  2. Sections the user has manually opened are tracked via data-user-opened
 *     on the element itself. That attribute survives the re-collapse (WinterCMS
 *     only touches the CSS class and display style, not our attribute), so we
 *     can restore those sections too.
 */
(function () {
    function openSections(root) {
        (root || document).querySelectorAll(
            '.section-field[data-field-collapsible][data-field-collapsible-open],' +
            '.section-field[data-field-collapsible][data-user-opened]'
        ).forEach(function (section) {
            section.classList.remove('collapsed');
            var el = section.nextElementSibling;
            while (el && !el.classList.contains('section-field')) {
                el.style.display = '';
                el = el.nextElementSibling;
            }
        });
    }

    // Track which sections the user opens or closes via click.
    // Uses event delegation so it catches sections inside dynamically added repeater items.
    // setTimeout defers the state check until after WinterCMS has toggled the collapsed class.
    document.addEventListener('click', function (e) {
        var section = e.target.closest('.section-field[data-field-collapsible]');
        if (!section) return;

        setTimeout(function () {
            if (section.classList.contains('collapsed')) {
                section.removeAttribute('data-user-opened');
            } else {
                section.setAttribute('data-user-opened', '1');
            }
        }, 0);
    });

    // Initial pass after the form widget has initialised.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { openSections(); });
    } else {
        setTimeout(openSections, 0);
    }

    // Restore open sections after any AJAX call (repeater add/remove triggers
    // bindCollapsibleSections which re-collapses everything).
    // setTimeout ensures we run after WinterCMS's own ajaxSuccess handler.
    document.addEventListener('ajaxSuccess', function () {
        setTimeout(openSections, 0);
    });
})();
