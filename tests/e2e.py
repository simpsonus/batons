#!/usr/bin/env python3
"""Tests de parcours de Bâtons dans un vrai navigateur (Chromium, via Playwright).

    pip install playwright && playwright install chromium
    python3 tests/e2e.py

Démarre le serveur PHP intégré sur une base neuve, joue les parcours principaux, puis vérifie la mise en page
(aucun débordement horizontal de 320 px à l'écran d'ordinateur, en clair et en sombre) et l'absence d'erreurs
JavaScript ou de violations de la politique de sécurité (CSP). Code de sortie non nul en cas d'échec.
"""
import os, re, shutil, socket, subprocess, sys, tempfile, time, traceback
from datetime import datetime, timedelta
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
failures = []
console_errors = []


def free_port():
    s = socket.socket(); s.bind(('127.0.0.1', 0)); p = s.getsockname()[1]; s.close(); return p


def check(cond, what):
    if not cond:
        failures.append(what)
        print('  ÉCHEC :', what)


def step(name):
    def deco(fn):
        def run(*a):
            print('•', name)
            try:
                fn(*a)
            except Exception as e:  # noqa
                failures.append(f'{name} : {e}')
                print('  ERREUR :', e)
                traceback.print_exc(limit=2)
        return run
    return deco


def main():
    data = tempfile.mkdtemp(prefix='batons-e2e-')
    port = free_port()
    base = f'http://127.0.0.1:{port}/'
    env = dict(os.environ, BATONS_DATA_DIR=data, BATONS_AUTH='none', BATONS_TIMEZONE='Europe/Paris',
               BATONS_HTTP_FIXTURES=os.path.join(ROOT, 'tests/fixtures.php'))
    srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', os.path.join(ROOT, 'app/public')],
                           env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                socket.create_connection(('127.0.0.1', port), 0.2).close(); break
            except OSError:
                time.sleep(0.1)
        with sync_playwright() as p:
            browser = p.chromium.launch()
            ctx = browser.new_context(viewport={'width': 402, 'height': 874}, is_mobile=True, has_touch=True,
                                      accept_downloads=True)
            page = ctx.new_page()
            page.on('console', lambda m: m.type == 'error' and 'Failed to load resource' not in m.text
                    and console_errors.append(m.text))
            page.on('pageerror', lambda e: console_errors.append(str(e)))
            # Ressources en erreur (hors vignettes de catalogue, absentes des réponses figées des tests).
            page.on('response', lambda r: r.status >= 400 and r.request.resource_type != 'document'
                    and 'r=thumb' not in r.url and 'r=poster' not in r.url
                    and console_errors.append(f'{r.status} {r.url}'))
            run_flows(page, base)
            run_layout(browser, base)
            browser.close()
    finally:
        srv.terminate()
        shutil.rmtree(data, ignore_errors=True)

    errs = [e for e in console_errors if 'favicon' not in e]
    check(not errs, 'aucune erreur JavaScript ni violation CSP : ' + '; '.join(errs[:5]))
    print()
    if failures:
        print(f'{len(failures)} échec(s).')
        sys.exit(1)
    print('Tous les parcours passent.')


def create_category(page, base, name, group='', unit='', count_future=None, kind=None):
    page.goto(base + '?r=category_new')
    page.fill('input[name=label]', name)
    if kind:
        page.check(f'input[name=kind][value={kind}]')
    if group:
        page.fill('input[name=group]', group)
    if unit:
        page.fill('input[name=unit]', unit)
    if count_future is not None:
        page.set_checked('#f-future', count_future)
    page.click('.form-actions button.accent')


def run_flows(page, base):
    @step('Créer des catégories et des groupes')
    def categories():
        create_category(page, base, 'Café', 'Maison')
        create_category(page, base, 'Fioul', 'Maison', unit='l')
        create_category(page, base, 'Course', 'Sport')
        page.goto(base)
        check(page.locator('.tile').count() == 3, '3 tuiles sur l\'accueil')
    categories()

    @step('Un appui sur une tuile ajoute un bâton')
    def tap():
        page.goto(base)
        page.locator('.tile', has_text='Café').locator('button.tap').click()
        check('Café' in page.inner_text('.flash'), 'message de confirmation')
        check('1' in page.locator('.tile', has_text='Café').inner_text(), 'compteur du jour à 1')
    tap()

    @step('Bâton à venir : non compté, annoncé comme « Prochain bâton »')
    def future():
        page.goto(base)
        page.locator('.tile', has_text='Fioul').locator('a.more').click()
        later = (datetime.now() + timedelta(days=2)).strftime('%Y-%m-%dT%H:%M')
        page.fill('input[name=occurred_at]', later)
        page.fill('input[name=value]', '1000')
        page.click('form.add-form button[type=submit], form.add-form button.accent')
        text = page.inner_text('.stats')
        check('prochain bâton' in text.lower(), f'« Prochain bâton » affiché ({page.url} : {text[:120]!r})')
        check('à venir' in page.inner_text('body'), 'étiquette « à venir » dans l\'historique')
    future()

    @step('Filtrer la chronologie par groupe')
    def timeline_filter():
        page.goto(base + '?r=timeline')
        page.click('[data-filter-open]')
        page.locator('[data-filter-sheet] [data-group]', has_text='Maison').locator('[data-group-box]').check()
        page.click('[data-filter-sheet] [data-filter-submit]')
        check(re.search(r'[?&]g=\d', page.url) is not None, 'adresse courte avec g=')
        check('Maison' in page.inner_text('.tl-sum'), 'résumé du filtre')
        check(page.locator('.fs-x').count() == 1, 'croix dans la pastille')
        page.click('.fs-x')
        check('Toute la chronologie' in page.inner_text('.tl-sum'), 'filtre effacé')
    timeline_filter()

    @step('Chronologie : recherche dans les notes et période')
    def timeline_search():
        page.goto(base + '?r=timeline')
        page.locator('.events a.icon-btn').first.click()
        page.fill('input[name=note]', 'Vidange à l\'École')
        page.click('button:has-text("Mettre à jour")')
        page.goto(base + '?r=timeline')
        page.click('[data-filter-open]')
        page.fill('[data-filter-sheet] [data-filter-q]', 'ecole')
        page.locator('[data-filter-sheet] .chip', has_text='30 jours').click()
        check(page.inner_text('[data-filter-sheet] [data-filter-submit]') == 'Afficher', 'bouton sans nombre avec une recherche')
        page.click('[data-filter-sheet] [data-filter-submit]')
        check('q=ecole' in page.url and 'p=30' in page.url, 'adresse courte avec q= et p=')
        check(page.locator('.events li').count() == 1, 'un seul bâton trouvé')
        check(page.inner_text('.events mark') == 'École', 'mot surligné malgré l\'accent')
        check('« ecole »' in page.inner_text('.tl-sum') and '30 jours' in page.inner_text('.tl-sum'), 'résumé : recherche et période')
        check('· 2' in page.inner_text('.fs-main'), 'pastille : 2 critères')
        page.click('[data-filter-open]')
        page.locator('[data-filter-sheet] .chip', has_text='Dates').click()
        check(page.locator('[data-filter-sheet] [data-filter-dates]').is_visible(), 'champs de dates affichés')
        page.goto(base + '?r=timeline&p=dates&du=2000-01-02&au=2000-01-01')
        check('Aucun bâton' in page.inner_text('main'), 'intervalle sans bâton')
        page.click('.fs-x')
        check('Toute la chronologie' in page.inner_text('.tl-sum'), 'tout effacé')
    timeline_search()

    @step('Graphique : changer d\'échelle sans remonter en haut')
    def chart_scale():
        page.goto(base)
        page.locator('.tile', has_text='Fioul').locator('.more').click()
        page.set_viewport_size({'width': 390, 'height': 700})
        page.locator('[data-chart-card]').scroll_into_view_if_needed()
        y = page.evaluate('scrollY')
        page.click('[data-chart-card] .seg a:has-text("12 mois")')
        page.locator('[data-chart-card] .seg a[aria-current]', has_text='12 mois').wait_for()
        check(page.evaluate('scrollY') == y and y > 0, 'la page reste à la même hauteur')
        check('scale=month' in page.url, 'adresse mise à jour')
        page.set_viewport_size({'width': 1280, 'height': 900})
    chart_scale()

    @step('Réorganiser au clavier')
    def reorder():
        page.goto(base + '?r=category_order')
        h = page.locator('li[data-name="Fioul"] [data-handle]')
        h.focus()
        page.keyboard.press('ArrowUp')
        page.wait_for_function("document.querySelector('[data-order-status]').textContent.length > 0")
        check('Fioul' in page.inner_text('[data-order-status]'), 'déplacement annoncé')
        page.reload()
        names = page.eval_on_selector_all('li[data-id]', 'els => els.map(e => e.dataset.name)')
        check(names.index('Fioul') < names.index('Café'), 'ordre enregistré')
        if page.locator('[data-fold-view]').count():
            page.click('[data-fold-view-btn=groups]')
            check(page.locator('section.order-group.folded').count() > 0, '« Groupes seuls » replie les groupes')
            check(page.locator('section.order-group.folded [data-count]').first.is_visible(), 'nombre de catégories visible sur un groupe plié')
            page.reload()
            check(page.get_attribute('[data-fold-view-btn=groups]', 'aria-pressed') == 'true', 'choix mémorisé')
            page.click('[data-fold-view-btn=all]')
            check(page.locator('section.order-group.folded').count() == 0, '« Tout » déplie les groupes')
    reorder()

    @step('Apparence : style Dégradé appliqué')
    def appearance():
        page.goto(base + '?r=appearance')
        page.click('.app-opt:has-text("Dégradé")')
        page.click('.form-actions button.accent')
        page.goto(base)
        bg = page.evaluate("getComputedStyle(document.querySelector('.grid .tile')).backgroundImage")
        check('gradient' in bg, 'dégradé visible sur les tuiles')
        page.goto(base + '?r=appearance')
        page.click('.app-opt:has-text("Liseré")')
        page.click('.form-actions button.accent')
    appearance()

    @step('Importer (Chronicling) : vérification puis import')
    def import_flow():
        f = tempfile.NamedTemporaryFile('w', suffix='_events_.csv', delete=False)
        f.write('DateTime,Category,Value,Note\n2026-01-10T08:00:00.000Z,Thé,,\n2026-01-11T08:00:00.000Z,Thé,,\n')
        f.close()
        page.goto(base + '?r=import')
        page.click('a.choice-card:has-text("Chronicling")')
        page.set_input_files('[data-file-input]', f.name)
        page.click('button:has-text("Vérifier")')
        check('2' in page.inner_text('.summary'), '2 bâtons annoncés')
        other = page.context.new_page()
        other.goto(base)
        check(other.locator('.tile', has_text='Thé').count() == 0, 'rien importé avant confirmation')
        other.close()
        page.click('button:has-text("Importer 2")')
        page.goto(base)
        check(page.locator('.tile', has_text='Thé').count() == 1, 'catégorie importée')
        os.unlink(f.name)
    import_flow()

    @step('Exporter une catégorie')
    def export():
        page.goto(base + '?r=export')
        page.check('#x-one')
        page.select_option('[data-export-cat]', label='Fioul')
        with page.expect_download() as d:
            page.click('button:has-text("Télécharger")')
        content = open(d.value.path(), encoding='utf-8-sig').read()
        check(content.startswith('date_locale;'), 'en-tête CSV')
        check('Fioul' in content and 'Café' not in content, 'seulement la catégorie choisie')
    export()

    @step('Série : recherche au catalogue, épisode, puis « ▶ épisode suivant »')
    def series():
        create_category(page, base, 'Séries', kind='serie')
        page.goto(base)
        page.locator('.tile', has_text='Séries').locator('a.tap').click()
        page.fill('input[name=q]', 'bear')
        page.press('input[name=q]', 'Enter')
        page.locator('button.work-pick', has_text='The Bear').first.click()
        page.click('button:has-text("Ajouter le bâton")')
        page.goto(base)
        page.locator('.tile', has_text='Séries').locator('a.tap').click()
        nxt = page.locator('button.next-btn').first
        check(nxt.count() == 1 and 'E02' in nxt.inner_text(), '« Continuer » propose l\'épisode suivant')
        nxt.click()
        check('The Bear' in page.inner_text('.flash'), 'épisode suivant enregistré')
    series()

    @step('Pages du menu : croix pour fermer')
    def close_links():
        for r in ['archives', 'import', 'export', 'account', 'appearance', 'unlock', 'menu']:
            page.goto(base + '?r=' + r)
            check(page.locator('.close-btn').count() == 1, f'croix sur {r}')
    close_links()


def run_layout(browser, base):
    print('• Mise en page : pas de débordement horizontal (320 → 1280 px, clair et sombre)')
    pages = ['', '?r=timeline', '?r=category_order', '?r=appearance', '?r=import', '?r=export', '?r=account',
             '?r=category_new', '?r=archives', '?r=menu']
    for width, mobile in [(320, True), (402, True), (1280, False)]:
        for scheme in ['light', 'dark']:
            ctx = browser.new_context(viewport={'width': width, 'height': 800}, is_mobile=mobile, has_touch=mobile,
                                      color_scheme=scheme)
            pg = ctx.new_page()
            pg.on('pageerror', lambda e: console_errors.append(str(e)))
            pg.on('console', lambda m: m.type == 'error' and 'Failed to load resource' not in m.text
                  and console_errors.append(m.text))
            # catégories : aussi la page de chaque catégorie
            pg.goto(base)
            links = pg.eval_on_selector_all('.tile a.more', 'els => els.map(e => e.getAttribute("href"))')
            for u in pages + [l.lstrip('./') for l in links[:2]]:
                pg.goto(base + u)
                sw = pg.evaluate('document.documentElement.scrollWidth')
                check(sw <= width, f'débordement {sw}px > {width}px sur « {u or "accueil"} » ({scheme})')
            ctx.close()


if __name__ == '__main__':
    main()
