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
 *   This file owns everything and reuses core's .is-collapsible / .collapsed CSS
 *   classes so the styling is identical to native collapsible sections.
 *
 * Init detection uses a MutationObserver rather than DOMContentLoaded / ajax
 * events: the block form fields (and nested repeater items) are injected by
 * AJAX, and the backend dispatches its lifecycle events through jQuery, which
 * native addEventListener handlers do not reliably receive. The observer fires
 * on any DOM insertion regardless of framework, so newly rendered sections are
 * always picked up. A per-section guard class makes init idempotent and cheap.
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
    function initSections() {
        document
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

    // Single delegated click handler — native click events bubble normally, so
    // addEventListener is reliable here (unlike framework lifecycle events).
    document.addEventListener('click', function (e) {
        var section = e.target.closest('.section-field[data-block-collapsible]');
        if (!section) {
            return;
        }
        setCollapsed(section, !section.classList.contains('collapsed'));
    });

    // Run once now for anything already present.
    initSections();

    // Watch for sections injected later (block forms, repeater items). Observing
    // childList only — our own class/style writes are attribute mutations and
    // won't retrigger this, so there is no feedback loop.
    var scheduled = false;
    var observer = new MutationObserver(function () {
        if (scheduled) {
            return;
        }
        scheduled = true;
        // Coalesce bursts of insertions into a single pass.
        (window.requestAnimationFrame || window.setTimeout)(function () {
            scheduled = false;
            initSections();
        }, 0);
    });

    function startObserving() {
        if (document.body) {
            observer.observe(document.body, { childList: true, subtree: true });
        } else {
            document.addEventListener('DOMContentLoaded', startObserving);
        }
    }
    startObserving();
})();
