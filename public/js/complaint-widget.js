let complaintTurnstileToken = '';
let complaintTurnstileUpdate = () => {};
window.complaintTurnstileSuccess = token => { complaintTurnstileToken = token; complaintTurnstileUpdate(); };
window.complaintTurnstileExpired = () => { complaintTurnstileToken = ''; complaintTurnstileUpdate('Verifikasi telah kedaluwarsa. Silakan ulangi verifikasi.'); };
window.complaintTurnstileError = () => { complaintTurnstileToken = ''; complaintTurnstileUpdate('Verifikasi sementara tidak tersedia. Silakan coba kembali.'); };

document.addEventListener('DOMContentLoaded', () => {
    const widget = document.querySelector('[data-complaint-widget]');
    if (!widget) return;
    const toggle = widget.querySelector('[data-complaint-toggle]');
    const panel = widget.querySelector('#complaint-panel');
    const form = widget.querySelector('form');
    const feedback = widget.querySelector('[data-complaint-feedback]');
    const submit = form.querySelector('[type="submit"]');
    const description = form.querySelector('[name="description"]');
    const attachment = form.querySelector('[name="attachment"]');
    let sending = false;
    complaintTurnstileUpdate = message => {
        submit.disabled = sending || !complaintTurnstileToken || !form.checkValidity();
        if (message) feedback.textContent = message;
    };
    form.addEventListener('input', () => complaintTurnstileUpdate());
    form.addEventListener('change', () => complaintTurnstileUpdate());
    complaintTurnstileUpdate();
    const setOpen = open => { panel.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); if (open) panel.querySelector('input[name="name"]').focus(); else toggle.focus(); };
    toggle.addEventListener('click', () => { const open = panel.hidden; setOpen(open); if (open) document.dispatchEvent(new CustomEvent('portal-widget-open', { detail: 'complaint' })); });
    document.addEventListener('portal-widget-open', event => { if (event.detail !== 'complaint' && !panel.hidden) { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); } });
    widget.querySelector('[data-complaint-close]').addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) setOpen(false); });
    description.addEventListener('input', () => { widget.querySelector('[data-complaint-count]').textContent = description.value.length.toLocaleString('id-ID'); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submit.disabled) return;
        const file = attachment.files[0];
        if (file && (file.size > 5 * 1024 * 1024 || !/\.(jpe?g|png|webp|pdf)$/i.test(file.name))) { feedback.textContent = 'Lampiran harus berupa JPG, PNG, WEBP, atau PDF dengan ukuran maksimal 5 MB.'; return; }
        sending = true; submit.disabled = true; submit.textContent = 'Mengirim...'; feedback.textContent = '';
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Pengaduan gagal dikirim.'));
            form.reset();
            form.querySelector('[name="submission_key"]').value = data.next_key;
            widget.querySelector('[data-complaint-count]').textContent = '0';
            feedback.textContent = 'Pengaduan Anda berhasil dikirim. Terima kasih telah berpartisipasi dalam menyampaikan aspirasi untuk Desa Pilangsari. Nomor tiket: ' + data.ticket;
        } catch (error) { feedback.textContent = error.message || 'Pengaduan gagal dikirim. Silakan coba kembali.'; }
        finally { complaintTurnstileToken = ''; if (window.turnstile && form.querySelector('.cf-turnstile')) window.turnstile.reset(form.querySelector('.cf-turnstile')); sending = false; submit.textContent = 'Kirim'; complaintTurnstileUpdate(); }
    });
    const map = document.getElementById('public-map');
    if (map && 'IntersectionObserver' in window) new IntersectionObserver(entries => { widget.classList.toggle('complaint-map-visible', entries[0].isIntersecting && panel.hidden); }, { threshold: 0.05 }).observe(map);
});
