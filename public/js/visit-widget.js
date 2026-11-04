document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-visit-widget]');
    if (!widget) return;
    const toggle = widget.querySelector('.visit-toggle');
    const panel = widget.querySelector('.visit-panel');
    const status = widget.querySelector('[data-visit-status]');
    const list = widget.querySelector('[data-visit-list]');
    const formatter = new Intl.NumberFormat('id-ID');
    let loaded = false;
    let loading = false;

    async function load() {
        if (loading || loaded) return;
        loading = true;
        status.hidden = false;
        status.textContent = 'Memuat statistik...';
        try {
            const response = await fetch(widget.dataset.url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Gagal memuat');
            const data = await response.json();
            widget.querySelectorAll('[data-visit-value]').forEach(value => {
                value.textContent = formatter.format(data[value.dataset.visitValue]);
            });
            widget.querySelector('[data-visit-today]').textContent = formatter.format(data.today);
            list.hidden = false;
            status.hidden = true;
            loaded = true;
        } catch (error) {
            status.textContent = 'Statistik tidak tersedia. Tutup dan buka kembali untuk mencoba lagi.';
            widget.querySelector('[data-visit-today]').textContent = '-';
        } finally {
            loading = false;
        }
    }
    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Tutup statistik kunjungan' : 'Buka statistik kunjungan');
        if (open) { document.dispatchEvent(new CustomEvent('portal-widget-open', { detail: 'visits' })); load(); }
    });
    document.addEventListener('portal-widget-open', event => {
        if (event.detail !== 'visits') { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.setAttribute('aria-label', 'Buka statistik kunjungan'); }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Buka statistik kunjungan');
            toggle.focus();
        }
    });
    const map = document.getElementById('public-map');
    if (map && 'IntersectionObserver' in window) {
        new IntersectionObserver(entries => {
            widget.classList.toggle('visit-map-visible', entries[0].isIntersecting);
        }, { threshold: 0.05 }).observe(map);
    }
    load();
});
