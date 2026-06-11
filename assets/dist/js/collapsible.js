/**
 * Manages collapsible section state for block form widgets.
 *
 * WinterCMS calls bindCollapsibleSections() after every AJAX call, re-collapsing
 * all [data-field-collapsible] sections. Reacting after the fact (MutationObserver,
 * setTimeout) is unreliable because the repeater's post-AJAX widget initialisation
 * may trigger further collapse passes after our correction fires.
 *
 * Primary strategy — prevent, not fix:
 *   Before each AJAX request (ajaxBeforeSend), temporarily strip data-field-collapsible
 *   from any section the user has manually opened. bindCollapsibleSections() cannot
 *   find what it cannot select. After the request completes (ajaxComplete), the
 *   attribute is restored.
 *
 * Backup — MutationObserver:
 *   Catches any programmatic re-collapse that slips through (e.g. from non-AJAX
 *   widget initialisation). A userTogglingSection flag prevents it from fighting
 *   genuine user click-to-collapse actions.
 *
 * User open/close state is stored as data-user-opened on the element itself so it
 * survives across AJAX calls and DOM updates.
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

    // Track whether the current click is a user-initiated section toggle so the
    // MutationObserver knows not to undo it.
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

    // --- Primary fix: shield user-opened sections from bindCollapsibleSections ---

    // Before any AJAX call, hide user-opened sections from WinterCMS by removing
    // data-field-collapsible so bindCollapsibleSections() cannot select them.
    document.addEventListener('ajaxBeforeSend', function () {
        document.querySelectorAll('.section-field[data-field-collapsible][data-user-opened]')
            .forEach(function (s) {
                s.removeAttribute('data-field-collapsible');
                s.setAttribute('data-fc-suspended', '1');
            });
    });

    // After the AJAX call (success or failure), restore the attribute.
    document.addEventListener('ajaxComplete', function () {
        document.querySelectorAll('.section-field[data-fc-suspended]')
            .forEach(function (s) {
                s.setAttribute('data-field-collapsible', '1');
                s.removeAttribute('data-fc-suspended');
            });
        // Also re-open any collapsed: false sections in newly injected HTML
        setTimeout(openConfiguredSections, 0);
    });

    // --- Backup: MutationObserver for non-AJAX re-collapses ---

    function setupObserver() {
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
    }

    // Initial open pass + observer setup after the form widget has initialised
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            openConfiguredSections();
            setupObserver();
        });
    } else {
        setTimeout(openConfiguredSections, 0);
        setupObserver();
    }
})();
