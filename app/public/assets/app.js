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

    // Sélecteur d'icônes MDI : recherche à la frappe, clic = « mdi:nom » dans le champ.
    const picker = document.querySelector('[data-icon-picker]');
    const iconInput = document.querySelector('[data-icon-input]');
    const preview = document.querySelector('[data-icon-preview]');
    if (picker && iconInput) {
        picker.hidden = false;
        const search = picker.querySelector('[data-icon-search]');
        const results = picker.querySelector('[data-icon-results]');
        const svg = (p) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${p}"/></svg>`;
        let timer, seq = 0;
        const load = async (q) => {
            const mine = ++seq;
            try {
                const res = await fetch('./?r=icons&q=' + encodeURIComponent(q), { credentials: 'same-origin' });
                const list = await res.json();
                if (mine !== seq) return;
                results.innerHTML = list.length ? '' : '<p class="muted">Aucune icône trouvée (essaie en anglais : coffee, run, pill…).</p>';
                list.forEach(({ n, p }) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'icon-choice' + (iconInput.value === 'mdi:' + n ? ' selected' : '');
                    b.title = n;
                    b.innerHTML = svg(p);
                    b.addEventListener('click', () => {
                        iconInput.value = 'mdi:' + n;
                        if (preview) preview.innerHTML = svg(p);
                        results.querySelectorAll('.selected').forEach((el) => el.classList.remove('selected'));
                        b.classList.add('selected');
                    });
                    results.appendChild(b);
                });
            } catch (e) { /* hors ligne : on garde la saisie manuelle */ }
        };
        search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(search.value), 200); });
        search.addEventListener('keydown', (ev) => { if (ev.key === 'Enter') ev.preventDefault(); });
        // Aperçu d'un émoji saisi à la main.
        iconInput.addEventListener('input', () => {
            if (preview && !iconInput.value.startsWith('mdi:')) preview.textContent = iconInput.value || '|';
        });
        load('');
    }

    // Couleur choisie -> aperçu de l'icône.
    document.querySelectorAll('input[name="color"], input[name="color_custom"]').forEach((el) => {
        el.addEventListener('input', () => document.querySelector('.icon-field')?.style.setProperty('--c', el.value));
    });

    // Retour haptique discret sur mobile lors d'un tap.
    document.querySelectorAll('.tap').forEach((btn) => {
        btn.addEventListener('click', () => navigator.vibrate?.(15));
    });

    // Le message flash disparaît tout seul.
    const flash = document.querySelector('.flash');
    if (flash) setTimeout(() => flash.remove(), 6000);
})();
