/**
 * Self-contained collapsible sections for block form widgets.
 *
 * Why this exists instead of reusing core's data-field-collapsible:
 *
 *   WinterCMS's FormWidget.bindCollapsibleSections() runs on EVERY FormWidget
 *   init(), scoped to the closest <form>. When a nested repeater adds an item,
 *   a new FormWidget initialises, finds the shared parent form, and re-collapses
 *   ALL data-field-collapsible sections in it while binding a SECOND click
 *   handler to each. The doubled handler is why adding a repeater item appeared
 *   to "stall" (two toggles cancel out) and why manually-opened sections snapped
 *   shut again.
 *
 * Solution:
 *   Blocks.php emits data-block-collapsible (and data-block-collapsible-open),
 *   which core's JS never selects, so core never collapses or binds these.
 *   This file owns everything:
 *     - one-time init per section (guarded by .block-collapsible-ready)
 *     - a single delegated click handler on document (cannot double-bind)
 *   It reuses core's .is-collapsible / .collapsed CSS classes so the visual
 *   styling (chevron, hover) is identical to native collapsible sections.
 */
(function () {
    var READY_CLASS = 'block-collapsible-ready';

    // Collect the fields that follow a section header up to the next section.
    function followingFields(section) {
        var els = [];
        var el = section.nextElementSibling;
        while (el && !el.classList.contains('section-field')) {
            els.push(el);
            el = el.nextElementSibling;
        }
        return els;
    }

    function setCollapsed(section, collapsed) {
        section.classList.toggle('collapsed', collapsed);
        followingFields(section).forEach(function (el) {
            el.style.display = collapsed ? 'none' : '';
        });
    }

    // One-time setup for any not-yet-initialised collapsible section.
    function initSections(root) {
        (root || document)
            .querySelectorAll('.section-field[data-block-collapsible]:not(.' + READY_CLASS + ')')
            .forEach(function (section) {
                section.classList.add(READY_CLASS);

                // Apply core's styling hook for the chevron + pointer cursor.
                var header = section.querySelector('.field-section');
                if (header) {
                    header.classList.add('is-collapsible');
                }

                // Initial state: collapsed unless explicitly marked open.
                var startOpen = section.hasAttribute('data-block-collapsible-open');
                setCollapsed(section, !startOpen);
            });
    }

    // Single delegated click handler — never doubles regardless of widget inits.
    document.addEventListener('click', function (e) {
        var section = e.target.closest('.section-field[data-block-collapsible]');
        if (!section) {
            return;
        }
        setCollapsed(section, !section.classList.contains('collapsed'));
    });

    // Initial pass.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initSections(); });
    } else {
        initSections();
    }

    // Initialise sections inside any HTML injected by AJAX (new repeater items,
    // newly added blocks). Already-initialised sections are skipped via the
    // guard class, so existing open/closed state is preserved.
    document.addEventListener('ajaxSuccess', function () { initSections(); });
    document.addEventListener('render', function () { initSections(); });
})();
