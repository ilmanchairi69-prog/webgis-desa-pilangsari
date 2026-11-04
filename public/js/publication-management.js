document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.publication-category').forEach(form => {
        const input = form.querySelector('input[type=checkbox]');
        const status = form.querySelector('.publication-status');
        const feedback = form.querySelector('.publication-feedback');
        input.addEventListener('change', async () => {
            const previous = input.dataset.saved === '1';
            input.disabled = true;
            feedback.textContent = 'Menyimpan?';
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 15000);
            try {
                const response = await fetch(form.dataset.url, {
                    method: 'PATCH', cache: 'no-store', credentials: 'same-origin',
                    headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},
                    body: JSON.stringify({is_public:input.checked}), signal:controller.signal
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.errors?.is_public?.[0] || 'Perubahan tidak berhasil disimpan. Muat ulang untuk memeriksa status terbaru.');
                if (typeof data.is_public !== 'boolean') throw new Error('Respons server tidak valid. Muat ulang halaman.');
                input.checked = data.is_public;
                input.dataset.saved = data.is_public ? '1' : '0';
                status.textContent = data.is_public ? 'Published' : 'Unpublished';
                const ready = Number(form.dataset.eligibleCount || 0);
                const visibleCount = form.querySelector('.publication-visible-count');
                const visibilityNote = form.querySelector('.publication-visibility-note');
                if (visibleCount) visibleCount.textContent = String(data.is_public ? ready : 0);
                if (visibilityNote) visibilityNote.textContent = !data.is_public
                    ? 'Kategori nonaktif: objek yang sudah Published tetap disembunyikan.'
                    : ready ? 'Kategori aktif: objek yang memenuhi syarat dapat tampil di peta publik.'
                    : 'Kategori aktif, tetapi belum ada objek yang memenuhi syarat. Periksa verifikasi, publikasi individual, izin tampil, arsip, dan koordinat objek.';
                feedback.textContent = 'Tersimpan.';
            } catch (error) {
                input.checked = previous;
                feedback.textContent = error.name === 'AbortError' ? 'Waktu tunggu habis. Muat ulang untuk memastikan status tersimpan.' : error.message;
            } finally {
                clearTimeout(timeout);
                input.disabled = false;
            }
        });
    });
});
