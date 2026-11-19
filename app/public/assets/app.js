// Bâtons — un peu de JS, jamais indispensable : tout fonctionne sans.
(() => {
    // Confirmation avant les actions destructrices.
    document.addEventListener('submit', (ev) => {
        const msg = ev.target.dataset.confirm;
        if (msg && !window.confirm(msg)) ev.preventDefault();
    });

    // Sélecteur de couleur libre : décoche les pastilles de la palette.
    document.querySelectorAll('[data-color-custom]').forEach((input) => {
        input.addEventListener('input', () => {
            input.form.querySelectorAll('input[name="color"]').forEach((r) => { r.checked = false; });
        });
    });

    // Retour haptique discret sur mobile lors d'un tap.
    document.querySelectorAll('.tap').forEach((btn) => {
        btn.addEventListener('click', () => navigator.vibrate?.(15));
    });

    // Le message flash disparaît tout seul.
    const flash = document.querySelector('.flash');
    if (flash) setTimeout(() => flash.remove(), 6000);
})();
