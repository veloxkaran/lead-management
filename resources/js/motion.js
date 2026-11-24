// Motion behaviours that need a little JS (the CSS side: resources/css/_motion.scss).
//
//  • Count-up: dashboard tiles and lead KPI numbers count up to their value
//    the first time they scroll into view.
//  • Self-closing success messages: the green flash after saving closes after
//    6 seconds (paused while hovered/focused). Errors stay until dismissed.
//
// Both respect the OS "reduce motion" setting.

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// ---- Count-up ---------------------------------------------------------------

// "NPR 1,25,000.50 deals" → prefix "NPR ", number "1,25,000.50", suffix " deals"
const NUMBER = /^(\D*?)(\d[\d,]*(?:\.\d+)?)(.*)$/s;

function firstNumberText(el) {
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    let node;

    while ((node = walker.nextNode())) {
        if (/\d/.test(node.nodeValue)) {
            return node;
        }
    }

    return null;
}

function countUp(node) {
    const original = node.nodeValue;
    const match = original.match(NUMBER);

    if (!match) {
        return;
    }

    const [, prefix, digits, suffix] = match;
    const target = parseFloat(digits.replace(/,/g, ''));
    const decimals = (digits.split('.')[1] || '').length;
    // Keep the original grouping: 1,25,000 (Nepali/Indian) vs 125,000.
    const locale = /\d,\d{2},/.test(digits) ? 'en-IN' : 'en-US';
    const grouped = digits.includes(',');

    if (!Number.isFinite(target) || target === 0) {
        return;
    }

    const format = (value) => value.toLocaleString(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
        useGrouping: grouped,
    });

    const duration = Math.min(1100, 500 + Math.log10(target + 1) * 120);
    const start = performance.now();

    const frame = (now) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - (1 - t) ** 3; // ease-out cubic

        node.nodeValue = t < 1 ? prefix + format(target * eased) + suffix : original;

        if (t < 1) {
            requestAnimationFrame(frame);
        }
    };

    node.nodeValue = prefix + format(0) + suffix;
    requestAnimationFrame(frame);
}

function initCountUp() {
    if (reduceMotion || !('IntersectionObserver' in window)) {
        return;
    }

    const targets = [...document.querySelectorAll('.stat-card .fs-4, .lead-kpi-value, [data-count-up]')]
        .filter((el) => !el.closest('[x-data]') || el.hasAttribute('data-count-up')) // Alpine-rendered numbers manage themselves
        .map((el) => firstNumberText(el))
        .filter(Boolean);

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                countUp(entry.target.__countNode);
            }
        });
    }, { threshold: 0.4 });

    targets.forEach((node) => {
        const el = node.parentElement;
        el.__countNode = node;
        observer.observe(el);
    });
}

// ---- Self-closing success messages -------------------------------------------

const AUTO_DISMISS_MS = 6000;

function initAutoDismiss() {
    document.querySelectorAll('.app-content > .alert-success.alert-dismissible').forEach((alert) => {
        let remaining = AUTO_DISMISS_MS;
        let startedAt = 0;
        let timer = null;

        const close = () => window.bootstrap?.Alert.getOrCreateInstance(alert).close();
        const run = () => {
            startedAt = Date.now();
            timer = setTimeout(close, remaining);
            alert.classList.remove('is-paused');
        };
        const pause = () => {
            clearTimeout(timer);
            remaining -= Date.now() - startedAt;
            alert.classList.add('is-paused');
        };

        alert.style.setProperty('--autodismiss-ms', `${AUTO_DISMISS_MS}ms`);
        alert.classList.add('alert-autodismiss');

        alert.addEventListener('mouseenter', pause);
        alert.addEventListener('mouseleave', run);
        alert.addEventListener('focusin', pause);
        alert.addEventListener('focusout', run);

        run();
    });
}

// ---- Page-to-page transitions ------------------------------------------------
// The browser skips a cross-document view transition when it can't run one
// (e.g. the next page took too long). That's fine, but it rejects the
// transition's promises and Chrome reports them as uncaught errors — swallow
// them on both the outgoing (pageswap) and incoming (pagereveal) page.
['pageswap', 'pagereveal'].forEach((type) => {
    window.addEventListener(type, (event) => {
        event.viewTransition?.ready?.catch(() => {});
        event.viewTransition?.finished?.catch(() => {});
        event.viewTransition?.updateCallbackDone?.catch(() => {});
    });
});

document.addEventListener('DOMContentLoaded', () => {
    initCountUp();
    initAutoDismiss();
});
