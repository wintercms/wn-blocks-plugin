/**
 * Manages collapsible section state for block form widgets.
 *
 * WinterCMS calls bindCollapsibleSections() after every ajaxSuccess, which
 * re-collapses all [data-field-collapsible] sections — including ones the user
 * manually opened. A setTimeout race is unreliable because the repeater widget
 * may trigger further post-AJAX initialization that collapses again after our
 * timeout fires (visible as a "briefly shows then collapses" flicker).
 *
 * Strategy:
 *  - User-opened sections are tracked via data-user-opened on the element.
 *  - A MutationObserver watches for the collapsed class being re-added to any
 *    data-user-opened section and removes it immediately — no visible flicker.
 *  - A userTogglingSection flag suppresses the observer during genuine user
 *    clicks so the user can still collapse sections normally.
 *  - Sections configured with collapsed: false (data-field-collapsible-open)
 *    are handled separately via setTimeout after ajaxSuccess (existing behaviour).
 */
(function () {
    var userTogglingSection = false;

    function openSection(section) {
        section.classList.remove('collapsed');
        var el = section.nextElementSibling;
        while (el && !el.classList.contains('section-field')) {
            el.style.display = '';
            el = el.nextElementSibling;
        }
    }

    // Re-open sections configured with collapsed: false
    function openConfiguredSections(root) {
        (root || document).querySelectorAll(
            '.section-field[data-field-collapsible][data-field-collapsible-open]'
        ).forEach(openSection);
    }

    // Track user click intent so the MutationObserver knows not to fight it
    document.addEventListener('click', function (e) {
        var section = e.target.closest('.section-field[data-field-collapsible]');
        if (!section) return;

        userTogglingSection = true;
        setTimeout(function () {
            userTogglingSection = false;
            if (section.classList.contains('collapsed')) {
                section.removeAttribute('data-user-opened');
            } else {
                section.setAttribute('data-user-opened', '1');
            }
        }, 0);
    });

    // Immediately undo any programmatic re-collapse of a user-opened section.
    // Fires synchronously (as a microtask) after the DOM change, before any
    // setTimeout callbacks, so there is no visible flicker.
    var observer = new MutationObserver(function (mutations) {
        if (userTogglingSection) return;
        mutations.forEach(function (mutation) {
            var section = mutation.target;
            if (
                section.hasAttribute('data-user-opened') &&
                section.classList.contains('collapsed')
            ) {
                openSection(section);
            }
        });
    });

    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['class'],
        subtree: true,
    });

    // Initial pass after form widget init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { openConfiguredSections(); });
    } else {
        setTimeout(openConfiguredSections, 0);
    }

    // Restore configured-open sections after AJAX (new repeater items etc.)
    document.addEventListener('ajaxSuccess', function () {
        setTimeout(openConfiguredSections, 0);
    });
})();
