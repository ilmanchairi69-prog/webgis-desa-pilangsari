document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-portal-toggle]');
    const navigation = document.getElementById('portal-navigation');
    if (!toggle || !navigation) return;
    const mobile = window.matchMedia('(max-width: 960px)');
    const dropdowns = [...navigation.querySelectorAll('details')];
    const close = () => {
        navigation.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka menu navigasi');
        dropdowns.forEach(dropdown => { dropdown.open = false; });
    };
    toggle.addEventListener('click', () => {
        const open = navigation.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        if (!open) dropdowns.forEach(dropdown => { dropdown.open = false; });
    });
    navigation.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
    document.addEventListener('click', event => {
        if (!event.target.closest('.portal-header')) close();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const dropdown = dropdowns.find(item => item.open);
        if (dropdown) {
            dropdown.open = false;
            dropdown.querySelector('summary').focus();
        } else if (navigation.classList.contains('is-open')) {
            close();
            toggle.focus();
        }
    });
    mobile.addEventListener('change', close);
});
