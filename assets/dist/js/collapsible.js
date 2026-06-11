/**
 * Reopens collapsible sections that carry data-field-collapsible-open.
 *
 * WinterCMS's core form widget collapses ALL data-field-collapsible sections on
 * init. This script runs after that and re-opens the ones marked as open by default.
 */
(function () {
    function openMarkedSections(root) {
        (root || document).querySelectorAll(
            '.section-field[data-field-collapsible][data-field-collapsible-open]'
        ).forEach(function (section) {
            section.classList.remove('collapsed');
            var el = section.nextElementSibling;
            while (el && !el.classList.contains('section-field')) {
                el.style.display = '';
                el = el.nextElementSibling;
            }
        });
    }

    // Run after the form widget has initialised (it uses $(document).ready internally)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { openMarkedSections(); });
    } else {
        setTimeout(openMarkedSections, 0);
    }

    // Also handle dynamically added repeater items
    document.addEventListener('ajaxSuccess', function () { openMarkedSections(); });
})();
