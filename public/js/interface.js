document.addEventListener('DOMContentLoaded', () => {
    const publicToggle = document.querySelector('[data-nav-toggle]');
    const publicNav = document.querySelector('[data-nav]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.getElementById('admin-navigation');
    const backdrop = document.querySelector('[data-sidebar-dismiss]');
    const mobile = window.matchMedia('(max-width: 960px)');

    function closePublicMenu() {
        publicNav?.classList.remove('is-open');
        publicToggle?.setAttribute('aria-expanded', 'false');
        publicToggle?.setAttribute('aria-label', 'Buka menu');
    }
    publicToggle?.addEventListener('click', () => {
        const open = publicNav.classList.toggle('is-open');
        publicToggle.setAttribute('aria-expanded', String(open));
        publicToggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    });
    publicNav?.querySelectorAll('a').forEach(link => link.addEventListener('click', closePublicMenu));

    function setSidebar(open, restoreFocus = false) {
        if (!sidebar || !sidebarToggle) return;
        document.body.classList.toggle('sidebar-is-open', open);
        sidebarToggle.setAttribute('aria-expanded', String(open));
        sidebarToggle.setAttribute('aria-label', open ? 'Tutup menu administrasi' : 'Buka menu administrasi');
        backdrop.hidden = !open;
        sidebar.inert = mobile.matches && !open;
        if (open) sidebar.querySelector('a')?.focus();
        if (restoreFocus) sidebarToggle.focus();
    }
    sidebarToggle?.addEventListener('click', () => setSidebar(!document.body.classList.contains('sidebar-is-open')));
    backdrop?.addEventListener('click', () => setSidebar(false, true));
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setSidebar(false)));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            if (document.body.classList.contains('sidebar-is-open')) setSidebar(false, true);
            if (publicNav?.classList.contains('is-open')) { closePublicMenu(); publicToggle?.focus(); }
        }
        if (event.key === 'Tab' && mobile.matches && document.body.classList.contains('sidebar-is-open')) {
            const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled])')];
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    mobile.addEventListener('change', () => { setSidebar(false); closePublicMenu(); });
    setSidebar(false);
});
