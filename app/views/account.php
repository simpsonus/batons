<?php use Batons\Auth; use Batons\Time; ?>
<div class="page-head"><h1>Compte</h1></div>

<section class="card">
    <h2>Identité</h2>
    <p>Identifiant : <strong><?= e($me['username']) ?></strong><?php if ($me['display_name'] && $me['display_name'] !== $me['username']): ?> (<?= e($me['display_name']) ?>)<?php endif; ?></p>
    <p class="muted">
        <?= match ($method) {
            'header'  => 'Identifié automatiquement par le service qui donne accès à Bâtons.',
            'builtin' => 'Connecté avec ton mot de passe.',
            default   => 'Utilisateur unique, sans connexion.',
        } ?>
    </p>
</section>

<?php if (Auth::has('builtin')): ?>
    <section class="card">
        <h2><?= $method === 'header' ? 'Mot de passe pour l\'accès direct' : 'Mot de passe' ?></h2>
        <?php if ($method === 'header'): ?>
            <p>Bâtons est aussi accessible directement (sans passer par ton accès habituel) avec une page de connexion.
                Définis ici un mot de passe pour t'y connecter avec l'identifiant <strong><?= e($me['username']) ?></strong> :
                tu y retrouveras exactement les mêmes données.</p>
            <p class="muted"><?= $me['password_hash'] ? 'Un mot de passe est défini.' : 'Aucun mot de passe défini : l\'accès direct t\'est fermé.' ?></p>
        <?php endif; ?>
        <form method="post" action="<?= url('account_password') ?>" novalidate>
            <?= csrf() ?>
            <?php if ($method === 'builtin'): ?>
                <label>Mot de passe actuel
                    <input type="password" name="current" required autocomplete="current-password"
                           <?= isset($errors['current']) ? 'aria-invalid="true" aria-describedby="err-current"' : '' ?>>
                    <?php if (isset($errors['current'])): ?><span class="error" id="err-current"><?= e($errors['current']) ?></span><?php endif; ?>
                </label>
            <?php endif; ?>
            <div class="row">
                <label>Nouveau mot de passe <span class="muted">10 caractères min.</span>
                    <input type="password" name="password" required autocomplete="new-password"
                           <?= isset($errors['password']) ? 'aria-invalid="true" aria-describedby="err-password"' : '' ?>>
                    <?php if (isset($errors['password'])): ?><span class="error" id="err-password"><?= e($errors['password']) ?></span><?php endif; ?>
                </label>
                <label>Confirmation
                    <input type="password" name="password2" required autocomplete="new-password">
                </label>
            </div>
            <div class="form-actions">
                <button class="btn accent"><?= $me['password_hash'] ? 'Changer le mot de passe' : 'Définir le mot de passe' ?></button>
            </div>
        </form>
        <?php if ($method === 'header' && $me['password_hash']): ?>
            <form method="post" action="<?= url('account_password_remove') ?>" class="danger-zone" data-confirm="Supprimer ton mot de passe ? L'accès direct te sera fermé.">
                <?= csrf() ?>
                <button class="btn danger">Supprimer le mot de passe (fermer l'accès direct)</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($users !== null): ?>
    <section class="card">
        <h2>Comptes</h2>
        <ul class="order-list">
            <?php foreach ($users as $u): ?>
                <li>
                    <span class="grow"><strong><?= e($u['username']) ?></strong><?= $u['is_admin'] ? ' <span class="badge">admin</span>' : '' ?><br>
                        <span class="muted small">créé le <?= e(Time::longDate(Time::local($u['created_at']))) ?></span></span>
                    <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                        <form method="post" action="<?= url('user_delete') ?>" data-confirm="Supprimer le compte « <?= e($u['username']) ?> » et TOUTES ses données ? C'est définitif.">
                            <?= csrf() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="icon-btn danger" aria-label="Supprimer le compte <?= e($u['username']) ?>" title="Supprimer"><?= ui_icon('trash') ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3>Ajouter un compte</h3>
        <form method="post" action="<?= url('user_create') ?>" novalidate>
            <?= csrf() ?>
            <div class="row">
                <label>Identifiant
                    <input type="text" name="username" value="<?= e($newUsername) ?>" required autocomplete="off" autocapitalize="none" spellcheck="false"
                           <?= isset($errors['new_username']) ? 'aria-invalid="true" aria-describedby="err-nu"' : '' ?>>
                    <?php if (isset($errors['new_username'])): ?><span class="error" id="err-nu"><?= e($errors['new_username']) ?></span><?php endif; ?>
                </label>
                <label>Mot de passe provisoire <span class="muted">10 caractères min.</span>
                    <input type="password" name="password" required autocomplete="new-password"
                           <?= isset($errors['new_password']) ? 'aria-invalid="true" aria-describedby="err-np"' : '' ?>>
                    <?php if (isset($errors['new_password'])): ?><span class="error" id="err-np"><?= e($errors['new_password']) ?></span><?php endif; ?>
                </label>
            </div>
            <p class="muted small">Transmets-le à la personne : elle pourra le changer depuis sa page Compte.</p>
            <button class="btn ghost">Ajouter le compte</button>
        </form>
    </section>
<?php endif; ?>
