import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../css/onboarding.css';

/**
 * First-login onboarding tour, shared by the operator panel and the renter
 * portal. The page injects a `window.pmsOnboarding` config object (built
 * server-side in Blade, so all copy is translatable via __()):
 *
 *   {
 *     autostart:  bool,    // true only on first login (onboarding_completed_at null)
 *     completeUrl:string,  // POST endpoint that stamps completion
 *     csrf:       string,  // CSRF token for the POST
 *     accent:     string,  // theme colour (operator: teal; portal: client brand)
 *     labels:     { next, previous, done, progress },
 *     steps:      [ { element?, title, description, side?, align? }, ... ],
 *   }
 *
 * A step with no `element` (or whose element isn't on the page) renders as a
 * centered popover, so the tour never breaks if a nav item is hidden by role.
 */
function isElementOnScreen(el) {
    const rect = el.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0 || el.offsetParent === null) {
        return false;
    }

    // Off-canvas — e.g. the mobile sidebar is translated off the left edge.
    return rect.right > 0 && rect.left < window.innerWidth;
}

function sidebarIsOnScreen() {
    const sidebar = document.querySelector('.fi-sidebar');
    if (!sidebar) {
        return false;
    }

    const rect = sidebar.getBoundingClientRect();

    return rect.width > 0 && rect.right > 0 && rect.left < window.innerWidth;
}

/**
 * Decide whether a step should spotlight its element or fall back to a centered
 * popover:
 *   - on screen now            → anchor to it
 *   - hidden in a collapsed desktop sidebar group → anchor (expanded on highlight)
 *   - off-canvas / hidden (mobile sidebar, hidden desktop nav) → centered popover
 */
function resolveStepElement(selector) {
    if (!selector) {
        return undefined;
    }

    const el = document.querySelector(selector);
    if (!el) {
        return undefined;
    }

    if (isElementOnScreen(el)) {
        return selector;
    }

    if (el.closest('.fi-sidebar-group.fi-collapsed') && sidebarIsOnScreen()) {
        return selector;
    }

    return undefined; // present but off-canvas → centered, not floating top-left
}

function buildSteps(config) {
    return (config.steps || [])
        // Drop steps whose target isn't in the DOM at all (feature hidden by
        // the user's role). Targets that exist but are off-canvas are kept and
        // resolved to a centered popover by resolveStepElement().
        .filter((step) => !step.element || document.querySelector(step.element))
        .map((step) => ({
            element: resolveStepElement(step.element),
            popover: {
                title: step.title,
                description: step.description,
                side: step.side || 'right',
                align: step.align || 'start',
            },
        }));
}

/**
 * Filament keeps a collapsed nav group's links in the DOM but hidden
 * (display:none via x-show), so driver.js finds the target yet can't anchor to
 * it and dumps the popover top-left. If the step's group is collapsed, click its
 * header to expand it; the caller then refreshes once the x-collapse animation
 * settles. Returns true if an expansion was triggered.
 */
function expandSidebarGroupFor(element) {
    if (!element) {
        return false;
    }

    const group = element.closest('.fi-sidebar-group');
    if (!group || !group.classList.contains('fi-collapsed')) {
        return false;
    }

    const toggle = group.querySelector('.fi-sidebar-group-btn');
    if (!toggle) {
        return false;
    }

    toggle.click();

    return true;
}

function markComplete(config) {
    if (!config.completeUrl) {
        return;
    }

    // Best-effort: a failed stamp just means the tour shows again next login.
    fetch(config.completeUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': config.csrf || '',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    }).catch(() => {});
}

function createDriver(config, steps) {
    const labels = config.labels || {};
    let driverObj;

    driverObj = driver({
        showProgress: true,
        allowClose: true,
        overlayColor: 'rgba(15, 23, 42, 0.55)',
        stagePadding: 6,
        stageRadius: 8,
        popoverClass: 'pms-tour',
        nextBtnText: labels.next || 'Next',
        prevBtnText: labels.previous || 'Back',
        doneBtnText: labels.done || 'Done',
        progressText: labels.progress || '{{current}} of {{total}}',
        steps,
        // If the highlighted item lives in a collapsed sidebar group, open the
        // group and reposition once the 200ms x-collapse animation finishes.
        onHighlightStarted: (element) => {
            if (expandSidebarGroupFor(element)) {
                setTimeout(() => {
                    try {
                        driverObj.refresh();
                    } catch (e) {
                        /* tour may have moved on; ignore */
                    }
                }, 260);
            }
        },
        // Fires for both finishing (Done) and skipping (X / overlay / Esc).
        onDestroyed: () => markComplete(config),
    });

    return driverObj;
}

function start() {
    const config = window.pmsOnboarding;
    if (!config) {
        return;
    }

    const steps = buildSteps(config);
    if (steps.length === 0) {
        return;
    }

    createDriver(config, steps).drive();
}

// Expose a manual trigger so a "Replay tour" menu item / button can call it.
window.pmsStartTour = start;

document.addEventListener('DOMContentLoaded', () => {
    const config = window.pmsOnboarding;
    if (!config) {
        return;
    }

    const wantsReplay = new URLSearchParams(window.location.search).has('tour');

    if (config.autostart || wantsReplay) {
        // Let the dashboard paint and any deferred nav render before spotlighting.
        setTimeout(start, 400);
    }
});
