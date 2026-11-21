/* Mode de saisie (html[data-input]) : « pointer » après un appui ou un clic, « key » après une touche du clavier.
   Le cadre de focus n'est montré qu'au clavier (CSS 01-base) : sur iPhone, les focus posés par programme
   (champ autofocus, retour sur un bouton après un dialogue ou un envoi « doux »…) n'encadrent plus rien. */
(() => {
    const root = document.documentElement;
    addEventListener('pointerdown', () => { root.dataset.input = 'pointer'; }, true);
    addEventListener('keydown', (ev) => {
        if (!ev.metaKey && !ev.ctrlKey && !ev.altKey) root.dataset.input = 'key';
    }, true);
})();

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

    // Onglet « Menu » (téléphone) : feuille modale ; sans <dialog>, le lien mène à la page Menu.
    const sheet = document.querySelector('[data-menu-sheet]');
    const sheetOpen = document.querySelector('[data-menu-open]');
    if (sheet && sheetOpen && typeof sheet.showModal === 'function') {
        sheetOpen.addEventListener('click', (ev) => {
            ev.preventDefault();
            sheet.showModal();
            // Focus sur le titre (invisible) : annoncé par les lecteurs d'écran, sans encadrer la 1re entrée.
            sheet.querySelector('[data-sheet-title]')?.focus();
        });
        sheet.addEventListener('click', (ev) => { if (ev.target === sheet) sheet.close(); });   // toucher à côté
        sheet.addEventListener('close', () => sheetOpen.focus());
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

    // Saisie d'un bâton d'œuvre : le bouton dit ce que l'on fait, selon « Ce bâton marque » (début, fin, séance…).
    const logBtn = document.querySelector('[data-log-submit]');
    if (logBtn) {
        const form = logBtn.closest('form');
        const sync = () => {
            const m = form.querySelector('input[name="milestone"]:checked')?.value ?? '';
            logBtn.querySelectorAll('.log-label').forEach((l) => { l.hidden = l.dataset.for !== m; });
        };
        form.addEventListener('change', (ev) => { if (ev.target.name === 'milestone') sync(); });
        sync();
    }

    // Œuvres : suggestions pendant la frappe (le formulaire marche aussi sans JS).
    const workSearch = document.querySelector('[data-work-search]');
    const workResults = document.querySelector('[data-work-results]');
    if (workSearch && workResults) {
        const input = workSearch.querySelector('input[name="q"]');
        const clear = workSearch.querySelector('[data-work-clear]');
        // Résultats pendant la frappe : le bouton « Chercher » devient inutile (la touche Entrée lance aussi la recherche).
        const submitBtn = workSearch.querySelector('[data-work-submit]');
        if (submitBtn) submitBtn.hidden = true;
        const syncClear = () => { if (clear) clear.hidden = input.value === ''; };
        syncClear();
        clear?.addEventListener('click', () => { input.value = ''; syncClear(); input.focus(); });
        // « Saisir à la main » : reprend le texte cherché comme titre, s'il n'y en a pas déjà un.
        const manual = document.querySelector('details.manual');
        manual?.addEventListener('toggle', () => {
            const title = manual.querySelector('input[name="title"]');
            if (manual.open && title && !title.value) title.value = input.value.trim();
        });
        let t, seqW = 0;
        input.addEventListener('input', () => {
            syncClear();
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

    // --- Réorganiser : glisser-déposer (souris, doigt, clavier), plier / déplier, renommer ----------
    const board = document.querySelector('[data-reorder]');
    if (board) {
        document.body.classList.add('dnd-on');
        document.querySelectorAll('.js-only, [data-fold], [data-fold-view], .drag-handle').forEach((el) => { el.hidden = false; });
        const status = document.querySelector('[data-order-status]');
        const menu = document.querySelector('[data-move-menu]');
        const sections = () => [...board.querySelectorAll(':scope > section.order-group')];
        const groupSections = () => sections().filter((s) => s.dataset.group !== '0');
        const noGroup = () => board.querySelector(':scope > section[data-group="0"]');
        const say = (msg) => { if (status) status.textContent = msg; };

        // Plier / déplier (mémorisé sur cet appareil).
        const FOLD_KEY = 'batons.folded';
        let folded = [];
        try { folded = JSON.parse(localStorage.getItem(FOLD_KEY) || '[]'); } catch (e) { folded = []; }
        const setFold = (sec, on) => {
            sec.classList.toggle('folded', on);
            const b = sec.querySelector('[data-fold]');
            if (b) {
                b.setAttribute('aria-expanded', on ? 'false' : 'true');
                b.setAttribute('aria-label', (on ? 'Déplier' : 'Replier') + ' le groupe ' + sec.dataset.name);
            }
        };
        const saveFold = () => {
            folded = groupSections().filter((s) => s.classList.contains('folded')).map((s) => s.dataset.group);
            try { localStorage.setItem(FOLD_KEY, JSON.stringify(folded)); } catch (e) { /* stockage indisponible */ }
            // Commutateur « Tout | Groupes seuls » : reflète l'état (aucun bouton enfoncé si mélangé).
            const n = groupSections().length;
            document.querySelectorAll('[data-fold-view-btn]').forEach((b) => {
                const on = b.dataset.foldViewBtn === 'all' ? folded.length === 0 : n > 0 && folded.length === n;
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            // Nombre de catégories, affiché sur un groupe plié.
            groupSections().forEach((sec) => {
                const c = sec.querySelector('[data-count]');
                if (c) c.textContent = String(sec.querySelectorAll('li[data-id]').length);
            });
        };
        groupSections().forEach((s) => setFold(s, folded.includes(s.dataset.group)));
        saveFold();
        board.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-fold]');
            if (!b) return;
            const sec = b.closest('section');
            setFold(sec, !sec.classList.contains('folded'));
            saveFold();
        });
        document.querySelector('[data-fold-view]')?.addEventListener('click', (ev) => {
            const b = ev.target.closest('[data-fold-view-btn]');
            if (!b) return;
            const fold = b.dataset.foldViewBtn === 'groups';
            groupSections().forEach((s) => setFold(s, fold));
            saveFold();
        });

        // Renommer sur place.
        board.addEventListener('click', (ev) => {
            const a = ev.target.closest('[data-rename]');
            if (a) {
                ev.preventDefault();
                const head = a.closest('.order-group-head');
                head.querySelector('h2').hidden = true;
                a.hidden = true;
                const f = head.querySelector('.rename-form');
                f.hidden = false;
                const input = f.querySelector('input[name=label]');
                input.focus();
                input.select();
                return;
            }
            const c = ev.target.closest('[data-rename-cancel]');
            if (c) closeRename(c.closest('.rename-form'));
        });
        const closeRename = (f) => {
            const head = f.closest('.order-group-head');
            f.hidden = true;
            f.reset();
            head.querySelector('h2').hidden = false;
            const a = head.querySelector('[data-rename]');
            a.hidden = false;
            a.focus();
        };
        board.addEventListener('keydown', (ev) => {
            const f = ev.target.closest('.rename-form');
            if (f && ev.key === 'Escape') { ev.preventDefault(); closeRename(f); }
        });

        // Enregistrement de l'ordre complet.
        const save = async (msg) => {
            const fd = new FormData();
            fd.append('csrf', board.dataset.csrf);
            fd.append('groups', groupSections().map((s) => s.dataset.group).join(','));
            sections().forEach((s) => {
                fd.append('cats[' + s.dataset.group + ']', [...s.querySelectorAll('li[data-id]')].map((li) => li.dataset.id).join(','));
            });
            let removed = [];
            try {
                const res = await fetch(board.dataset.reorder, { method: 'POST', body: fd, credentials: 'same-origin' });
                if (!res.ok) throw new Error(String(res.status));
                // Un groupe vidé disparaît (comme ailleurs dans l'application).
                groupSections().forEach((s) => {
                    if (!s.querySelector('li[data-id]')) { removed.push(s.dataset.name); s.remove(); }
                });
                say(msg + (removed.length ? ' Groupe vide retiré : ' + removed.join(', ') + '.' : ''));
                saveFold();
            } catch (e) {
                say('Impossible d\'enregistrer le nouvel ordre : la page va être rechargée.');
                setTimeout(() => location.reload(), 1500);
            }
        };
        const describe = (el) => {
            if (el.matches('section')) {
                const pos = groupSections().indexOf(el) + 1;
                return 'Groupe ' + el.dataset.name + ' : position ' + pos + ' sur ' + groupSections().length + '.';
            }
            const sec = el.closest('section');
            const items = [...sec.querySelectorAll('li[data-id]')];
            return el.dataset.name + ' : position ' + (items.indexOf(el) + 1) + ' sur ' + items.length + ' dans ' + sec.dataset.name + '.';
        };

        // Déplacements d'un cran (clavier et menu).
        const step = (el, dir) => {
            if (el.matches('section')) {
                const list = groupSections();
                const i = list.indexOf(el);
                const j = i + dir;
                if (j < 0 || j >= list.length) return false;
                if (dir < 0) board.insertBefore(el, list[j]); else board.insertBefore(el, list[j].nextElementSibling);
                return true;
            }
            const items = [...el.parentElement.querySelectorAll('li[data-id]')];
            const i = items.indexOf(el);
            const j = i + dir;
            if (j < 0 || j >= items.length) return false;
            el.parentElement.insertBefore(el, dir < 0 ? items[j] : items[j].nextElementSibling);
            return true;
        };
        const toGroup = (li, sec) => {
            const ul = sec.querySelector('ul[data-list]');
            ul.insertBefore(li, ul.querySelector('.order-empty'));
            if (sec.classList.contains('folded')) { setFold(sec, false); saveFold(); }
        };
        const handleOf = (el) => (el.matches('section') ? el.querySelector('.order-group-head [data-handle]') : el.querySelector('[data-handle]'));
        const after = (el) => { handleOf(el)?.focus(); save(describe(el)); };

        board.addEventListener('keydown', (ev) => {
            const h = ev.target.closest('[data-handle]');
            if (!h || (ev.key !== 'ArrowUp' && ev.key !== 'ArrowDown')) return;
            ev.preventDefault();
            const el = h.dataset.handle === 'group' ? h.closest('section') : h.closest('li');
            if (step(el, ev.key === 'ArrowUp' ? -1 : 1)) after(el);
        });

        // Menu d'un appui (alternative au glisser).
        const openMenu = (h) => {
            const isGroup = h.dataset.handle === 'group';
            const el = isGroup ? h.closest('section') : h.closest('li');
            const box = menu.querySelector('[data-move-actions]');
            menu.querySelector('.move-menu-title').textContent = 'Déplacer « ' + el.dataset.name + ' »';
            box.replaceChildren();
            const add = (label, fn) => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn ghost';
                b.textContent = label;
                b.addEventListener('click', () => { menu.close(); fn(); after(el); });
                box.append(b);
            };
            const list = isGroup ? groupSections() : [...el.parentElement.querySelectorAll('li[data-id]')];
            const i = list.indexOf(el);
            if (i > 0) add('↑ Monter', () => step(el, -1));
            if (i < list.length - 1) add('↓ Descendre', () => step(el, 1));
            if (i > 1) add('⤒ Tout en haut', () => { while (step(el, -1)); });
            if (i < list.length - 2) add('⤓ Tout en bas', () => { while (step(el, 1)); });
            if (!isGroup) {
                const here = el.closest('section');
                const others = sections().filter((s) => s !== here);
                if (others.length) {
                    const p = document.createElement('p');
                    p.textContent = 'Vers le groupe :';
                    box.append(p);
                    others.forEach((s) => add(s.dataset.name, () => toGroup(el, s)));
                }
            }
            if (!box.children.length) {
                const p = document.createElement('p');
                p.textContent = 'Rien à déplacer ici.';
                box.append(p);
            }
            menu.addEventListener('close', () => h.isConnected && document.activeElement === document.body && h.focus(), { once: true });
            menu.showModal();
            // Focus sur le titre : annoncé par les lecteurs d'écran, sans encadrer « Monter » (Safari iOS).
            menu.querySelector('.move-menu-title')?.focus();
        };
        menu?.addEventListener('click', (ev) => { if (ev.target === menu) menu.close(); });

        // Glisser-déposer (Pointer Events : souris, doigt, stylet).
        let drag = null;
        let suppressClick = false;
        board.addEventListener('click', (ev) => {
            const h = ev.target.closest('[data-handle]');
            if (!h) return;
            if (suppressClick) { suppressClick = false; return; }
            openMenu(h);                      // clavier (Entrée / Espace) ou appui bref
        });
        board.addEventListener('pointerdown', (ev) => {
            const h = ev.target.closest('[data-handle]');
            if (!h || ev.button !== 0) return;
            ev.preventDefault();
            h.setPointerCapture(ev.pointerId);
            drag = { h, x: ev.clientX, y: ev.clientY, started: false, pid: ev.pointerId };
        });
        board.addEventListener('pointermove', (ev) => {
            if (!drag || ev.pointerId !== drag.pid) return;
            if (!drag.started) {
                if (Math.hypot(ev.clientX - drag.x, ev.clientY - drag.y) < 6) return;
                startDrag(ev);
            }
            drag.lastY = ev.clientY;
            moveDrag(ev.clientY);
        });
        const endPointer = (ev) => {
            if (!drag || ev.pointerId !== drag.pid) return;
            const d = drag;
            drag = null;
            if (!d.started) { suppressClick = true; openMenu(d.h); return; }
            suppressClick = true;
            setTimeout(() => { suppressClick = false; }, 0);
            dropDrag(d, ev.type === 'pointercancel');
        };
        board.addEventListener('pointerup', endPointer);
        board.addEventListener('pointercancel', endPointer);

        const startDrag = (ev) => {
            const isGroup = drag.h.dataset.handle === 'group';
            const el = isGroup ? drag.h.closest('section') : drag.h.closest('li');
            drag.started = true;
            drag.el = el;
            drag.isGroup = isGroup;
            drag.origin = { parent: el.parentElement, next: el.nextElementSibling };
            // Écart doigt / haut de l'élément mesuré AVANT tout repli : le doigt est sur la poignée, en haut de
            // l'élément, et cet écart reste valable une fois le groupe replié.
            const before = el.getBoundingClientRect();
            drag.offY = ev.clientY - before.top;
            if (isGroup) {                    // tous les groupes repliés pendant le déplacement
                drag.foldBefore = groupSections().filter((s) => s.classList.contains('folded'));
                groupSections().forEach((s) => s.classList.add('folded'));
                // Le repli fait remonter la page : on la fait défiler pour que le groupe revienne sous le doigt.
                const shift = el.getBoundingClientRect().top - before.top;
                if (shift) scrollBy(0, shift);
            }
            const r = el.getBoundingClientRect();
            const ph = document.createElement(isGroup ? 'section' : 'li');
            ph.className = 'drop-ph';
            ph.style.height = r.height + 'px';
            ph.setAttribute('aria-hidden', 'true');
            el.parentElement.insertBefore(ph, el);
            drag.ph = ph;
            el.classList.add('is-dragging');
            Object.assign(el.style, { left: r.left + 'px', top: (ev.clientY - drag.offY) + 'px', width: r.width + 'px' });
            document.body.classList.add('dragging');
            drag.raf = requestAnimationFrame(autoScroll);
        };
        const autoScroll = () => {
            if (!drag || !drag.started) return;
            const y = drag.lastY ?? 0;
            const bottomBar = document.querySelector('.tabbar')?.offsetHeight || 0;
            const zone = 70;
            let dy = 0;
            if (y < zone) dy = -Math.ceil((zone - y) / 6);
            else if (y > innerHeight - bottomBar - zone) dy = Math.ceil((y - (innerHeight - bottomBar - zone)) / 6);
            if (dy) { scrollBy(0, dy); moveDrag(y); }
            drag.raf = requestAnimationFrame(autoScroll);
        };
        const moveDrag = (y) => {
            const { el, ph } = drag;
            el.style.top = (y - drag.offY) + 'px';
            board.querySelectorAll('.drop-into').forEach((s) => s.classList.remove('drop-into'));
            if (drag.isGroup) {
                const list = groupSections().filter((s) => s !== el);
                const before = list.find((s) => { const r = s.getBoundingClientRect(); return y < r.top + r.height / 2; });
                board.insertBefore(ph, before || noGroup() || null);
                return;
            }
            // Catégorie : section survolée (la plus proche verticalement).
            let best = null, bestD = Infinity;
            sections().forEach((s) => {
                const r = s.getBoundingClientRect();
                const d = y < r.top ? r.top - y : (y > r.bottom ? y - r.bottom : 0);
                if (d < bestD) { bestD = d; best = s; }
            });
            if (!best) return;
            const ul = best.querySelector('ul[data-list]');
            if (best.classList.contains('folded')) {
                best.classList.add('drop-into');
                ul.insertBefore(ph, ul.querySelector('.order-empty'));
                return;
            }
            const items = [...ul.querySelectorAll('li[data-id]')].filter((li) => li !== el);
            const before = items.find((li) => { const r = li.getBoundingClientRect(); return y < r.top + r.height / 2; });
            ul.insertBefore(ph, before || ul.querySelector('.order-empty'));
        };
        const dropDrag = (d, cancelled) => {
            cancelAnimationFrame(d.raf);
            const { el, ph } = d;
            board.querySelectorAll('.drop-into').forEach((s) => s.classList.remove('drop-into'));
            if (cancelled) d.origin.parent.insertBefore(el, d.origin.next); else ph.parentElement.insertBefore(el, ph);
            ph.remove();
            el.classList.remove('is-dragging');
            el.style.left = el.style.top = el.style.width = '';
            document.body.classList.remove('dragging');
            if (d.isGroup) groupSections().forEach((s) => s.classList.toggle('folded', d.foldBefore.includes(s)));
            else {
                const sec = el.closest('section');
                if (sec.classList.contains('folded')) { setFold(sec, false); saveFold(); }
            }
            const moved = !(el.parentElement === d.origin.parent && el.nextElementSibling === d.origin.next);
            handleOf(el)?.focus({ preventScroll: true });
            if (!cancelled && moved) save(describe(el));
        };
    }

    // Compte › Apparence : aperçu en direct (on change les classes de <body>, comme le fera l'enregistrement).
    const appear = document.querySelector('[data-appearance]');
    if (appear) {
        const apply = () => {
            const v = (n) => appear.querySelector('input[name="' + n + '"]:checked')?.value;
            const cls = [...document.body.classList].filter((c) => !/^(tiles|grad|shape)-/.test(c));
            document.body.className = [...cls, 'tiles-' + v('tile_style'), 'grad-' + v('tile_grad'), 'shape-' + v('tile_shape')].join(' ');
            const th = v('theme');
            if (th === 'clair') document.documentElement.dataset.theme = 'light';
            else if (th === 'sombre') document.documentElement.dataset.theme = 'dark';
            else delete document.documentElement.dataset.theme;
            appear.querySelector('[data-grad-field]').hidden = v('tile_style') !== 'degrade';
        };
        appear.addEventListener('change', apply);
        apply();
    }

    // Import : noms des fichiers choisis sous la zone de dépôt.
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const out = input.closest('form').querySelector('[data-file-names]');
        input.addEventListener('change', () => {
            const names = [...input.files].map((f) => f.name);
            if (out) out.textContent = names.length ? 'Choisi' + (names.length > 1 ? 's' : '') + ' : ' + names.join(', ') : '';
        });
    });

    // Exporter : la liste des catégories ne sert qu'avec « Une seule catégorie ».
    const exportCat = document.querySelector('[data-export-cat]');
    if (exportCat) {
        const form = exportCat.closest('form');
        const sync = () => {
            const one = form.querySelector('input[name=what]:checked')?.value === 'one';
            exportCat.closest('label').hidden = !one;
            exportCat.disabled = !one;
        };
        form.addEventListener('change', sync);
        sync();
    }

    // --- Chronologie : filtre ------------------------------------------------------------------
    const fSheet = document.querySelector('[data-filter-sheet]');
    const fOpen = document.querySelector('[data-filter-open]');
    if (fSheet && fOpen && typeof fSheet.showModal === 'function') {
        fOpen.addEventListener('click', (ev) => {
            ev.preventDefault();
            fSheet.showModal();
            const h = fSheet.querySelector('h2');
            h?.setAttribute('tabindex', '-1');
            h?.focus();
        });
        fSheet.addEventListener('click', (ev) => {
            if (ev.target === fSheet) fSheet.close();
            if (ev.target.closest('[data-filter-close]')) { ev.preventDefault(); fSheet.close(); }
        });
        fSheet.addEventListener('close', () => fOpen.focus());
    }
    document.querySelectorAll('[data-filter-form]').forEach((form) => {
        const boxes = () => [...form.querySelectorAll('input[name="c[]"]')];
        const submit = form.querySelector('[data-filter-submit]');
        const all = form.querySelector('[data-check-all]');
        if (all) all.hidden = false;
        // Avec JS, la case du groupe ne fait que (dé)cocher ses catégories : seules les catégories partent.
        form.querySelectorAll('[data-group-box]').forEach((b) => b.removeAttribute('name'));
        const setOpen = (g, open) => {
            const t = g.querySelector('[data-group-toggle]');
            g.querySelector('.tl-group-list').hidden = !open;
            g.classList.toggle('open', open);
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
            t.setAttribute('aria-label', (open ? 'Masquer' : 'Afficher') + ' les catégories de ' + g.querySelector('b').textContent);
        };
        form.querySelectorAll('[data-group]').forEach((g) => {
            g.querySelector('[data-group-toggle]').hidden = false;
            setOpen(g, g.dataset.partial === '1' || form.querySelectorAll('[data-group]').length <= 2);
        });
        const refresh = () => {
            form.querySelectorAll('[data-group]').forEach((g) => {
                const bs = [...g.querySelectorAll('input[name="c[]"]')];
                const on = bs.filter((b) => b.checked).length;
                const box = g.querySelector('[data-group-box]');
                box.checked = on === bs.length;
                box.indeterminate = on > 0 && on < bs.length;
                g.querySelector('[data-group-state]').textContent = on === bs.length ? 'tout le groupe' : on + ' sur ' + bs.length;
            });
            const checked = boxes().filter((b) => b.checked);
            const n = checked.reduce((s, b) => s + Number(b.dataset.n || 0), 0);
            const none = checked.length === 0 || checked.length === boxes().length;
            // Période « Dates… » : champs du / au visibles seulement quand elle est choisie.
            const period = form.querySelector('input[name="p"]:checked')?.value || '';
            const dates = form.querySelector('[data-filter-dates]');
            if (dates) dates.hidden = period !== 'dates';
            const q = (form.querySelector('[data-filter-q]')?.value || '').trim();
            // Avec une recherche ou une période, le nombre n'est connu qu'après l'envoi.
            submit.textContent = (q || period) ? 'Afficher' : none ? 'Afficher tous les bâtons' : 'Afficher ' + n.toLocaleString('fr-FR') + ' bâton' + (n > 1 ? 's' : '');
            if (all) all.textContent = checked.length === boxes().length ? 'Tout décocher' : 'Tout cocher';
        };
        form.querySelector('[data-filter-q]')?.addEventListener('input', () => refresh());
        form.querySelectorAll('[data-filter-dates] input').forEach((d) => d.addEventListener('input', () => {
            const r = form.querySelector('input[name="p"][value="dates"]');
            if (r) r.checked = true;
        }));
        form.addEventListener('change', (ev) => {
            const box = ev.target.closest('[data-group-box]');
            if (box) box.closest('[data-group]').querySelectorAll('input[name="c[]"]').forEach((b) => { b.checked = box.checked; });
            refresh();
        });
        form.addEventListener('click', (ev) => {
            const t = ev.target.closest('[data-group-toggle]');
            if (t) { const g = t.closest('[data-group]'); setOpen(g, !g.classList.contains('open')); }
            if (ev.target.closest('[data-check-all]')) {
                const target = !boxes().every((b) => b.checked);
                boxes().forEach((b) => { b.checked = target; });
                refresh();
            }
        });
        form.querySelector('[data-filter-search]')?.addEventListener('input', (ev) => {
            const q = ev.target.value.trim().toLowerCase();
            form.querySelectorAll('[data-group]').forEach((g) => {
                let any = false;
                g.querySelectorAll('.tl-cat').forEach((l) => {
                    const ok = !q || l.dataset.name.includes(q);
                    l.hidden = !ok;
                    any ||= ok;
                });
                g.hidden = !any;
                if (q) setOpen(g, true);
            });
        });
        refresh();
    });

    // Aides « ? » : fermeture par Échap ou clic à l'extérieur.
    document.addEventListener('click', (ev) => {
        document.querySelectorAll('details.help-box[open]').forEach((d) => { if (!d.contains(ev.target)) d.open = false; });
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key !== 'Escape') return;
        document.querySelectorAll('details.help-box[open]').forEach((d) => { d.open = false; d.querySelector('summary').focus(); });
    });

    // Retour haptique discret sur mobile lors d'un tap.
    document.addEventListener('click', (ev) => { if (ev.target.closest('.tap')) navigator.vibrate?.(15); });

    // Message flash : bouton de fermeture (pas de disparition automatique, pour laisser le temps de lire / annuler).
    // Délégué : le message peut être remplacé sans rechargement (envoi « doux » ci-dessous).
    document.querySelectorAll('[data-flash-close]').forEach((b) => { b.hidden = false; });

    // Disparition automatique (data-timeout, réglage d'Apparence) : compte à rebours mis en pause tant que le doigt,
    // la souris ou le focus clavier est sur le message (WCAG 2.2.1 : on n'est jamais coupé au moment d'agir).
    // La barre ne fait que montrer le temps restant ; c'est le minuteur JS qui décide (marche aussi sans animation).
    const armFlash = (el) => {
        const ms = Number(el?.dataset.timeout || 0);
        if (!el || !ms) return;
        const bar = el.querySelector('.flash-bar');
        if (bar) bar.hidden = false;
        let left = ms, started = Date.now(), timer = null, holds = 0;
        const end = () => {
            if (!el.isConnected) return;
            const hadFocus = el.contains(document.activeElement);
            el.remove();
            if (hadFocus) document.getElementById('contenu')?.focus();
        };
        const run = () => { started = Date.now(); timer = setTimeout(end, left); el.classList.remove('paused'); };
        const hold = () => { if (holds++ === 0) { clearTimeout(timer); left -= Date.now() - started; el.classList.add('paused'); } };
        const release = () => { if (--holds <= 0) { holds = 0; run(); } };
        el.addEventListener('pointerenter', hold);
        el.addEventListener('pointerleave', release);
        el.addEventListener('focusin', hold);
        el.addEventListener('focusout', (ev) => { if (!el.contains(ev.relatedTarget)) release(); });
        run();
    };
    armFlash(document.querySelector('[data-flash]'));
    window.batonsArmFlash = armFlash;
    document.addEventListener('click', (ev) => {
        if (!ev.target.closest('[data-flash-close]')) return;
        document.querySelector('[data-flash]')?.remove();
        document.getElementById('contenu')?.focus();
    });

    // Envoi « doux » (form[data-soft] : bâton éclair, Annuler, actions des œuvres) : le formulaire part en
    // arrière-plan ; si la réponse est la même page, on remplace le contenu et le message SANS revenir en haut.
    // Sinon (autre page) ou en cas d'échec : navigation normale. Sans JS : formulaire classique.
    const sameView = (a, b) => { const u = new URL(a, location.href), v = new URL(b, location.href);
        return u.pathname === v.pathname && u.search === v.search; };
    document.addEventListener('submit', async (ev) => {
        const form = ev.target.closest('form[data-soft]');
        if (!form || ev.defaultPrevented || !window.fetch || !window.DOMParser) return;
        ev.preventDefault();
        const btn = ev.submitter;
        // Pour remettre le focus au même endroit après remplacement (même bouton, repéré par son formulaire).
        const keyOf = (f) => f.getAttribute('action') + '|' + (f.querySelector('[name=id]')?.value || '');
        const key = keyOf(form);
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            if (!sameView(res.url, location.href)) { location.href = res.url; return; }
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const freshMain = doc.querySelector('main');
            if (!freshMain) throw new Error('page inattendue');
            const y = window.scrollY;
            document.querySelector('main').innerHTML = freshMain.innerHTML;
            const oldFlash = document.querySelector('[data-flash]');
            const newFlash = doc.querySelector('[data-flash]');
            if (newFlash) {
                newFlash.querySelectorAll('[data-flash-close]').forEach((b) => { b.hidden = false; });
                if (oldFlash) oldFlash.replaceWith(newFlash); else document.querySelector('main').before(newFlash);
                window.batonsArmFlash?.(newFlash);
            } else oldFlash?.remove();
            window.scrollTo(0, y);
            const again = [...document.querySelectorAll('main form[data-soft]')].find((f) => keyOf(f) === key);
            again?.querySelector('button')?.focus({ preventScroll: true });
        } catch (e) {
            form.removeAttribute('data-soft');
            form.submit();
        }
    });
})();

/* Graphique d'une catégorie : changer d'échelle (30 jours, 12 semaines, 12 mois) ou de mesure sans recharger
   la page, donc sans remonter en haut. Sans JS : lien normal, avec #graph pour revenir au graphique. */
(() => {
    const card = document.querySelector('[data-chart-card]');
    if (!card || !window.fetch || !window.DOMParser) return;
    card.addEventListener('click', async (ev) => {
        const a = ev.target.closest('.seg a');
        if (!a || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
        ev.preventDefault();
        if (a.getAttribute('aria-current')) return;
        const label = a.textContent;
        const group = a.closest('.seg')?.getAttribute('aria-label');
        try {
            const res = await fetch(a.href, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(String(res.status));
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const fresh = doc.querySelector('[data-chart-card]');
            if (!fresh) throw new Error('graphique absent');
            card.innerHTML = fresh.innerHTML;
            history.replaceState(null, '', a.href.replace(/#.*$/, ''));
            // Le focus reste sur le bouton choisi (lecteurs d'écran, clavier).
            [...card.querySelectorAll('.seg')].find((n) => n.getAttribute('aria-label') === group)
                ?.querySelectorAll('a').forEach((l) => { if (l.textContent === label) l.focus({ preventScroll: true }); });
        } catch (e) {
            location.href = a.href;      // en cas d'échec : navigation normale (avec #graph)
        }
    });
})();
