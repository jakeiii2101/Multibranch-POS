if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js').catch((error) => {
            console.error('Service worker registration failed:', error);
        });
    });
}

let disposeLandingNavigation = () => {};

function initializeLandingNavigation() {
    disposeLandingNavigation();
    const header = document.querySelector('.landing-header');
    if (!header) return;

    const controller = new AbortController();
    const { signal } = controller;
    const links = [...header.querySelectorAll('a[href^="#"]')];
    const sections = [...new Set(links.map(link => document.getElementById(link.hash.slice(1))))]
        .filter(Boolean);
    const menu = header.querySelector('details');
    let frame = null;

    const update = () => {
        frame = null;
        const offset = header.getBoundingClientRect().height + 16;
        document.body.style.setProperty('--landing-header-height', `${offset}px`);
        let active = sections[0];
        for (const section of sections) {
            if (section.getBoundingClientRect().top <= offset + 1) active = section;
        }
        if (window.scrollY > 0 && window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
            active = sections.at(-1);
        }
        for (const link of links) {
            if (active && link.hash === `#${active.id}`) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        }
    };
    const scheduleUpdate = () => {
        if (frame === null) frame = requestAnimationFrame(update);
    };

    header.addEventListener('click', event => {
        if (event.target.closest('a[href^="#"]')) {
            if (menu) menu.open = false;
            update();
        }
    }, { signal });
    window.addEventListener('scroll', scheduleUpdate, { passive: true, signal });
    window.addEventListener('resize', scheduleUpdate, { signal });
    window.addEventListener('hashchange', scheduleUpdate, { signal });
    const observer = new ResizeObserver(scheduleUpdate);
    observer.observe(header);
    update();

    disposeLandingNavigation = () => {
        controller.abort();
        observer.disconnect();
        if (frame !== null) cancelAnimationFrame(frame);
    };
}

document.addEventListener('livewire:navigated', initializeLandingNavigation);
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeLandingNavigation, { once: true });
} else {
    initializeLandingNavigation();
}
