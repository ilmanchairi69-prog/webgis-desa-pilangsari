document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('click', (event) => {
      if (!confirm(el.dataset.confirm || 'Lanjutkan tindakan ini?')) event.preventDefault();
    });
  });
});
