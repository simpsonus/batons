<?php use Batons\Privacy; use Batons\Time; ?>
<div class="page-head">
    <h1>Confidentialité</h1>
    <div class="head-tools">
        <form method="post" action="<?= url('lock') ?>"><?= csrf() ?><button class="btn"><?= ui_icon('lock') ?> Verrouiller</button></form>
        <?= close_link(from_url(url()), 'Fermer') ?>
    </div>
</div>

<section class="card">
    <h2>Éléments masqués</h2>
    <p><?= $hiddenCount ?> catégorie<?= $hiddenCount > 1 ? 's' : '' ?> masquée<?= $hiddenCount > 1 ? 's' : '' ?> (directement ou via leur groupe).
        Elles sont visibles en ce moment, avec le symbole <span class="hidden-mark" aria-hidden="true"><?= ui_icon('eye-off') ?></span><span class="sr">« masqué »</span>,
        et se reverrouillent après <?= intdiv($timeout, 60) ?> min d'inactivité.</p>
    <ul class="help-list">
        <li>Masquer une <strong>catégorie</strong> : case « Masquer » dans « Modifier ».</li>
        <li>Masquer un <strong>groupe</strong> entier : bouton <span aria-hidden="true">œil</span> dans <a href="<?= url('category_order') ?>">Réorganiser</a>.</li>
    </ul>
    <p class="muted small">C'est un écran de confidentialité, pas un coffre-fort : les données ne sont pas chiffrées
        et restent lisibles par qui a accès au serveur.</p>
</section>

<section class="card">
    <h2>Face ID, Touch ID et équivalents</h2>
    <?php if ($passkeys): ?>
        <ul class="order-list">
            <?php foreach ($passkeys as $pk): ?>
                <li>
                    <span class="grow"><strong><?= e($pk['name']) ?></strong><br>
                        <span class="muted small">ajouté le <?= e(Time::longDate(Time::local($pk['created_at']))) ?><?php if ($pk['last_used_at']): ?>, utilisé <?= e(Time::ago($pk['last_used_at'])) ?><?php endif; ?></span>
                    </span>
                    <form method="post" action="<?= url('passkey_delete') ?>" data-confirm="Retirer « <?= e($pk['name']) ?> » ? Le code restera utilisable.">
                        <?= csrf() ?>
                        <input type="hidden" name="id" value="<?= (int) $pk['id'] ?>">
                        <button class="icon-btn danger" aria-label="Retirer l'appareil <?= e($pk['name']) ?>" title="Retirer"><?= ui_icon('trash') ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Aucun appareil enregistré.</p>
    <?php endif; ?>

    <div data-passkey-register hidden>
        <label>Désignation de cet appareil
            <input type="text" maxlength="60" data-passkey-name autocomplete="off">
        </label>
        <button type="button" class="btn accent" data-passkey-register-btn>Enregistrer cet appareil</button>
        <p class="error" role="alert" data-passkey-error hidden></p>
    </div>
    <p class="muted small" data-passkey-unsupported>
        Pour utiliser la biométrie, ouvre Bâtons dans le navigateur de l'appareil (Safari, Chrome…), à une adresse
        en <strong>https</strong>. Certaines applications qui affichent des pages web ne l'autorisent pas :
        le code reste alors le moyen de déverrouiller.
    </p>
</section>

<section class="card">
    <h2>Changer le code</h2>
    <form method="post" action="<?= url('pin_save') ?>" novalidate>
        <?= csrf() ?>
        <div class="row">
            <label>Nouveau code (<?= Privacy::PIN_LENGTH ?> chiffres)
                <input type="password" name="pin" inputmode="numeric" maxlength="<?= Privacy::PIN_LENGTH ?>" autocomplete="new-password" required class="pin-input"
                       <?= isset($pinErrors['pin']) ? 'aria-invalid="true" aria-describedby="err-pin"' : '' ?>>
                <?php if (isset($pinErrors['pin'])): ?><span class="error" id="err-pin"><?= e($pinErrors['pin']) ?></span><?php endif; ?>
            </label>
            <label>Confirmation
                <input type="password" name="pin2" inputmode="numeric" maxlength="<?= Privacy::PIN_LENGTH ?>" autocomplete="new-password" required class="pin-input"
                       <?= isset($pinErrors['pin2']) ? 'aria-invalid="true" aria-describedby="err-pin2"' : '' ?>>
                <?php if (isset($pinErrors['pin2'])): ?><span class="error" id="err-pin2"><?= e($pinErrors['pin2']) ?></span><?php endif; ?>
            </label>
        </div>
        <button class="btn ghost">Changer le code</button>
    </form>
</section>
