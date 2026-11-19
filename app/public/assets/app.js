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
        const status = picker.querySelector('[data-icon-status]');
        const svg = (p) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${p}"/></svg>`;
        let timer, seq = 0;
        const load = async (q) => {
            const mine = ++seq;
            try {
                const res = await fetch('./?r=icons&q=' + encodeURIComponent(q), { credentials: 'same-origin' });
                const list = await res.json();
                if (mine !== seq) return;
                results.innerHTML = list.length ? '' : '<p class="muted">Aucune icône trouvée (essaie en anglais : coffee, run, pill…).</p>';
                if (status && q) status.textContent = list.length + ' icône' + (list.length > 1 ? 's' : '') + ' trouvée' + (list.length > 1 ? 's' : '');
                list.forEach(({ n, p }) => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'icon-choice' + (iconInput.value === 'mdi:' + n ? ' selected' : '');
                    b.title = n;
                    b.setAttribute('aria-label', 'Icône ' + n.replace(/-/g, ' '));
                    b.setAttribute('aria-pressed', iconInput.value === 'mdi:' + n ? 'true' : 'false');
                    b.innerHTML = svg(p);
                    b.addEventListener('click', () => {
                        iconInput.value = 'mdi:' + n;
                        if (preview) preview.innerHTML = svg(p);
                        results.querySelectorAll('.selected').forEach((el) => { el.classList.remove('selected'); el.setAttribute('aria-pressed', 'false'); });
                        b.classList.add('selected');
                        b.setAttribute('aria-pressed', 'true');
                        if (status) status.textContent = 'Icône ' + n.replace(/-/g, ' ') + ' choisie';
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

    // Menu du logo : fermeture par Échap ou clic à l'extérieur.
    const menu = document.querySelector('[data-appmenu]');
    if (menu) {
        document.addEventListener('click', (ev) => { if (menu.open && !menu.contains(ev.target)) menu.open = false; });
        document.addEventListener('keydown', (ev) => {
            if (ev.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary').focus(); }
        });
    }

    // --- Éléments masqués -------------------------------------------------------------

    // Reverrouillage : recharge la page à l'expiration, pour ne pas laisser d'éléments masqués à l'écran.
    const relock = parseInt(document.body.dataset.relock || '0', 10);
    if (relock > 0) {
        const deadline = Date.now() + relock * 1000;
        const check = () => { if (Date.now() >= deadline) location.reload(); };
        setTimeout(check, relock * 1000 + 500);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });
    }

    // Passkeys (WebAuthn) : Face ID, Touch ID, Windows Hello…
    const b64uToBuf = (s) => Uint8Array.from(atob(s.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((s.length + 3) % 4)), (c) => c.charCodeAt(0)).buffer;
    const bufToB64u = (b) => btoa(String.fromCharCode(...new Uint8Array(b))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const csrfToken = () => document.querySelector('input[name="csrf"]')?.value || '';
    const post = async (route, fields) => {
        const fd = new FormData();
        fd.append('csrf', csrfToken());
        Object.entries(fields).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch('./?r=' + route, { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await res.json().catch(() => ({ error: 'Réponse inattendue du serveur.' }));
        if (!res.ok || json.error) throw new Error(json.error || 'Erreur ' + res.status);
        return json;
    };
    const showError = (el, msg) => { if (el) { el.textContent = msg; el.hidden = false; } };
    const friendly = (e) => (e && e.name === 'NotAllowedError')
        ? 'Opération annulée ou non autorisée.'
        : (e && e.name === 'InvalidStateError') ? 'Cet appareil est déjà enregistré.' : (e?.message || String(e));
    const platformAuth = async () => {
        try {
            return !!(window.PublicKeyCredential && window.isSecureContext
                && await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable());
        } catch (e) { return false; }
    };

    // Déverrouillage
    const unlockBox = document.querySelector('[data-passkey-unlock]');
    if (unlockBox && document.querySelector('[data-has-passkeys]')) {
        platformAuth().then((ok) => {
            if (!ok) return;
            unlockBox.hidden = false;
            const btn = unlockBox.querySelector('[data-passkey-unlock-btn]');
            const err = document.querySelector('[data-passkey-error]');
            btn.focus();
            btn.addEventListener('click', async () => {
                err.hidden = true;
                btn.disabled = true;
                try {
                    const o = await post('passkey_options', { mode: 'get' });
                    o.challenge = b64uToBuf(o.challenge);
                    o.allowCredentials = (o.allowCredentials || []).map((c) => ({ ...c, id: b64uToBuf(c.id) }));
                    const cred = await navigator.credentials.get({ publicKey: o });
                    await post('passkey_unlock', {
                        id: cred.id,
                        clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                        authenticatorData: bufToB64u(cred.response.authenticatorData),
                        signature: bufToB64u(cred.response.signature),
                    });
                    location.href = './';
                } catch (e) {
                    showError(err, friendly(e));
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    // Enregistrement d'un appareil
    const regBox = document.querySelector('[data-passkey-register]');
    if (regBox) {
        platformAuth().then((ok) => {
            if (!ok) return;
            regBox.hidden = false;
            document.querySelector('[data-passkey-unsupported]')?.setAttribute('hidden', '');
            const name = regBox.querySelector('[data-passkey-name]');
            const ua = navigator.userAgent;
            name.value = /iPhone/.test(ua) ? 'iPhone' : /iPad/.test(ua) ? 'iPad' : /Macintosh/.test(ua) ? 'Mac'
                : /Android/.test(ua) ? 'Android' : /Windows/.test(ua) ? 'PC Windows' : 'Cet appareil';
            const btn = regBox.querySelector('[data-passkey-register-btn]');
            const err = regBox.querySelector('[data-passkey-error]');
            btn.addEventListener('click', async () => {
                err.hidden = true;
                btn.disabled = true;
                try {
                    const o = await post('passkey_options', { mode: 'register' });
                    o.challenge = b64uToBuf(o.challenge);
                    o.user.id = b64uToBuf(o.user.id);
                    o.excludeCredentials = (o.excludeCredentials || []).map((c) => ({ ...c, id: b64uToBuf(c.id) }));
                    const cred = await navigator.credentials.create({ publicKey: o });
                    await post('passkey_register', {
                        name: name.value,
                        clientDataJSON: bufToB64u(cred.response.clientDataJSON),
                        attestationObject: bufToB64u(cred.response.attestationObject),
                    });
                    location.reload();
                } catch (e) {
                    showError(err, friendly(e));
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }

    // Calendrier : afficher d'emblée les semaines les plus récentes (à droite).
    document.querySelectorAll('[data-scroll-end]').forEach((el) => { el.scrollLeft = el.scrollWidth; });

    // Œuvres : suggestions pendant la frappe (le formulaire marche aussi sans JS).
    const workSearch = document.querySelector('[data-work-search]');
    const workResults = document.querySelector('[data-work-results]');
    if (workSearch && workResults) {
        const input = workSearch.querySelector('input[name="q"]');
        let t, seqW = 0;
        input.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(async () => {
                const q = input.value.trim();
                if (q.length < 2) return;
                const mine = ++seqW;
                const params = new URLSearchParams(new FormData(workSearch));
                params.set('partial', '1');
                try {
                    const res = await fetch('./?' + params.toString(), { credentials: 'same-origin' });
                    if (mine === seqW && res.ok) workResults.innerHTML = await res.text();
                } catch (e) { /* hors ligne : le bouton Chercher reste disponible */ }
            }, 350);
        });
    }

    // Retour haptique discret sur mobile lors d'un tap.
    document.querySelectorAll('.tap').forEach((btn) => {
        btn.addEventListener('click', () => navigator.vibrate?.(15));
    });

    // Message flash : bouton de fermeture (pas de disparition automatique, pour laisser le temps de lire / annuler).
    const flash = document.querySelector('[data-flash]');
    const close = flash?.querySelector('[data-flash-close]');
    if (close) {
        close.hidden = false;
        close.addEventListener('click', () => { flash.remove(); document.getElementById('contenu')?.focus(); });
    }
})();
