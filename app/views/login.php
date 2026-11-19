<div class="page-head"><h1><?= $setup ? 'Créer le premier compte' : 'Connexion' ?></h1></div>

<section class="card narrow-card">
    <?php if ($setup): ?>
        <p>Bienvenue ! Ce compte sera <strong>administrateur</strong> : il pourra ensuite créer les comptes des autres personnes.</p>
    <?php endif; ?>
    <form method="post" action="<?= url($setup ? 'setup' : 'login') ?>" novalidate>
        <?= csrf() ?>
        <label>Identifiant
            <input type="text" name="username" value="<?= e($username) ?>" required autocomplete="username"
                   autocapitalize="none" spellcheck="false" autofocus
                   <?= isset($errors['username']) ? 'aria-invalid="true" aria-describedby="err-username"' : '' ?>>
            <?php if (isset($errors['username'])): ?><span class="error" id="err-username"><?= e($errors['username']) ?></span><?php endif; ?>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required autocomplete="<?= $setup ? 'new-password' : 'current-password' ?>"
                   <?= isset($errors['password']) || $error ? 'aria-invalid="true" aria-describedby="' . ($error ? 'err-login' : 'err-password') . '"' : '' ?>>
            <?php if (isset($errors['password'])): ?><span class="error" id="err-password"><?= e($errors['password']) ?></span><?php endif; ?>
        </label>
        <?php if ($setup): ?>
            <label>Confirme le mot de passe <span class="muted">10 caractères minimum</span>
                <input type="password" name="password2" required autocomplete="new-password">
            </label>
        <?php endif; ?>
        <?php if ($error): ?><p class="error" id="err-login" role="alert"><?= e($error) ?></p><?php endif; ?>
        <button class="btn accent wide"><?= $setup ? 'Créer le compte' : 'Se connecter' ?></button>
    </form>
    <?php if (!$setup && Batons\Auth::has('header')): ?>
        <p class="muted small">Pas encore de mot de passe ? Ouvre Bâtons depuis ton accès habituel, puis
            Menu › Compte › « Mot de passe pour l'accès direct ».</p>
    <?php endif; ?>
</section>
