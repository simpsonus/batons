<?php use Batons\Privacy; ?>
<div class="page-head"><h1>Éléments masqués</h1><div class="head-tools"><?= close_link(from_url(url()), 'Fermer') ?></div></div>

<?php if (!$hasPin): ?>
    <section class="card narrow-card">
        <h2>Choisis un code de déverrouillage</h2>
        <p>Les catégories et groupes que tu masques n'apparaissent nulle part (accueil, chronologie, archives, export)
            tant que tu ne les as pas déverrouillés avec ce code. Ensuite, tu pourras aussi utiliser
            Face ID ou Touch ID sur tes appareils.</p>
        <form method="post" action="<?= url('pin_save') ?>" novalidate>
            <?= csrf() ?>
            <label>Code à <?= Privacy::PIN_LENGTH ?> chiffres
                <input type="password" name="pin" inputmode="numeric" pattern="\d{<?= Privacy::PIN_LENGTH ?>}" maxlength="<?= Privacy::PIN_LENGTH ?>"
                       autocomplete="new-password" required class="pin-input" autofocus
                       <?= isset($pinErrors['pin']) ? 'aria-invalid="true" aria-describedby="err-pin"' : '' ?>>
                <?php if (isset($pinErrors['pin'])): ?><span class="error" id="err-pin"><?= e($pinErrors['pin']) ?></span><?php endif; ?>
            </label>
            <label>Confirme le code
                <input type="password" name="pin2" inputmode="numeric" pattern="\d{<?= Privacy::PIN_LENGTH ?>}" maxlength="<?= Privacy::PIN_LENGTH ?>"
                       autocomplete="new-password" required class="pin-input"
                       <?= isset($pinErrors['pin2']) ? 'aria-invalid="true" aria-describedby="err-pin2"' : '' ?>>
                <?php if (isset($pinErrors['pin2'])): ?><span class="error" id="err-pin2"><?= e($pinErrors['pin2']) ?></span><?php endif; ?>
            </label>
            <button class="btn accent">Enregistrer le code</button>
        </form>
        <p class="muted small">Garde ce code en tête : il n'est stocké que sous forme chiffrée irréversible et ne peut pas être affiché.</p>
    </section>
<?php else: ?>
    <section class="card narrow-card unlock-card">
        <span class="unlock-icon" aria-hidden="true"><?= ui_icon('lock') ?></span>
        <?php if ($blocked > 0): ?>
            <p class="error" role="alert">Trop d'essais incorrects. Réessaie dans <?= (int) ceil($blocked / 60) ?> min.</p>
        <?php else: ?>
            <div data-passkey-unlock hidden>
                <button type="button" class="btn accent wide" data-passkey-unlock-btn>
                    Déverrouiller avec Face ID / Touch ID
                </button>
                <p class="or"><span>ou</span></p>
            </div>
            <form method="post" action="<?= url('unlock') ?>" novalidate>
                <?= csrf() ?>
                <label>Code de déverrouillage
                    <input type="password" name="pin" inputmode="numeric" pattern="\d{<?= Privacy::PIN_LENGTH ?>}" maxlength="<?= Privacy::PIN_LENGTH ?>"
                           autocomplete="off" required class="pin-input" <?= $hasPasskeys ? '' : 'autofocus' ?>
                           <?= $error ? 'aria-invalid="true" aria-describedby="err-unlock"' : '' ?>>
                </label>
                <?php if ($error): ?><p class="error" id="err-unlock" role="alert"><?= e($error) ?></p><?php endif; ?>
                <button class="btn wide">Déverrouiller</button>
            </form>
            <p class="error" role="alert" data-passkey-error hidden></p>
        <?php endif; ?>
    </section>
    <?php if ($hasPasskeys): ?><span data-has-passkeys hidden></span><?php endif; ?>
<?php endif; ?>
