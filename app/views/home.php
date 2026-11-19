<?php
use Batons\Chart;
use Batons\Time;

$currentGroup = false;
?>
<h1 class="tab-title">Catégories</h1>

<?php if (!$categories): ?>
    <div class="empty">
        <svg class="tally-big" aria-hidden="true" focusable="false" viewBox="0 0 32 32"><g stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M7 7v18M12 7v18M17 7v18M22 7v18"/><path d="M4 21 26 11"/></g></svg>
        <p><strong>Rien à compter pour l'instant.</strong></p>
        <p>Crée une catégorie (« Café », « Arrosage », « Migraine »…), puis touche sa tuile à chaque fois que ça arrive.</p>
        <a class="btn" href="<?= url('category_new') ?>">Créer ma première catégorie</a>
    </div>
<?php endif; ?>

<?php foreach ($categories as $c): ?>
    <?php if ($c['group_name'] !== $currentGroup): ?>
        <?php if ($currentGroup !== false): ?></div><?php endif; ?>
        <?php $currentGroup = $c['group_name']; ?>
        <?php if ($currentGroup !== null): ?><h2 class="group"><?= e($currentGroup) ?><?php if ($c['group_hidden']): ?> <span class="hidden-mark" title="Groupe masqué"><?= ui_icon('eye-off') ?><span class="sr">(masqué)</span></span><?php endif; ?></h2>
        <?php elseif ($categories[0]['group_name'] !== null): ?><h2 class="group">Sans groupe</h2><?php endif; ?>
        <div class="grid">
    <?php endif; ?>

    <div class="tile" style="--c: <?= e($c['color']) ?>">
        <?php if ($c['kind'] !== 'simple'):
            $lastEv = $c['last_event_id'] ? Batons\Events::find($uid, (int) $c['last_event_id']) : null; ?>
        <a class="tap" href="<?= url('work_add', ['id' => $c['id']]) ?>">
            <span class="icon" aria-hidden="true"><?= icon($c['icon']) ?></span>
            <span class="name"><span class="sr">Ajouter à </span><?= e($c['name']) ?><?php if ($c['hidden']): ?> <span class="hidden-mark" title="Catégorie masquée"><?= ui_icon('eye-off') ?><span class="sr">(masquée)</span></span><?php endif; ?></span>
            <span class="meta">
                <?php if ($lastEv && $lastEv['work_title']): ?>
                    <span class="sr">dernier : </span><span class="meta-work"><?= e(trim($lastEv['work_title'] . ' ' . Batons\Works::progress($lastEv['season'], $lastEv['episode'], $lastEv['volume']))) ?></span>
                    <?= e(Time::ago($lastEv['occurred_at'])) ?>
                <?php else: ?>
                    <?= e(Time::ago($c['last_at'])) ?>
                <?php endif; ?>
            </span>
        </a>
        <?php else: ?>
        <form method="post" action="<?= url('tap') ?>">
            <?= csrf() ?>
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="tap">
                <span class="icon" aria-hidden="true"><?= icon($c['icon']) ?></span>
                <span class="name"><span class="sr">Ajouter un bâton : </span><?= e($c['name']) ?><?php if ($c['hidden']): ?> <span class="hidden-mark" title="Catégorie masquée"><?= ui_icon('eye-off') ?><span class="sr">(masquée)</span></span><?php endif; ?></span>
                <span class="meta">
                    <?php $openP = $c['periods'] ? Batons\Periods::open((int) $c['id']) : null; ?>
                    <?php if ($openP): ?>
                        <b>En cours</b> depuis <?= e(Batons\Works::daysLabel((int) $openP['days'])) ?><span class="sr"> ; toucher pour terminer</span>
                    <?php elseif ($c['periods'] && !$openP && (int) $c['today_count'] === 0): ?>
                        <?php if ($c['last_at']): ?><span class="sr">dernier bâton </span><?php endif; ?><?= e(Time::ago($c['last_at'])) ?><span class="sr"> ; toucher pour commencer</span>
                    <?php elseif ((int) $c['today_count'] > 0): ?>
                        <b><?= (int) $c['today_count'] ?></b> aujourd'hui<?php if ($c['unit'] && $c['today_sum'] !== null): ?> · <?= e(Chart::num($c['today_sum']) . ' ' . $c['unit']) ?><?php endif; ?>
                    <?php else: ?>
                        <?php if ($c['last_at']): ?><span class="sr">dernier bâton </span><?php endif; ?><?= e(Time::ago($c['last_at'])) ?>
                    <?php endif; ?>
                </span>
            </button>
        </form>
        <?php endif; ?>
        <a class="more" href="<?= url('category', ['id' => $c['id']]) ?>" title="Détails, saisie avec date, valeur ou note">
            <?= ui_icon('chevron') ?><span class="sr">Détails de <?= e($c['name']) ?></span>
        </a>
    </div>
<?php endforeach; ?>
<?php if ($currentGroup !== false): ?>
    <?php if ($categories): ?>
        <a class="tile add-tile" href="<?= url('category_new') ?>"><?= ui_icon('plus') ?><span>Nouvelle catégorie</span></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($archivedCount): ?>
    <p class="muted center"><a href="<?= url('archives') ?>"><?= $archivedCount ?> catégorie<?= $archivedCount > 1 ? 's' : '' ?> archivée<?= $archivedCount > 1 ? 's' : '' ?></a></p>
<?php endif; ?>
