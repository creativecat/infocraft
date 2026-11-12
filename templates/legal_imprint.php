<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Impressum (aus den Admin-Einstellungen, #160)
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.16.0
 */
/** @var array<string, string> $legal */
$isPrivate = ($legal['kind'] ?? 'school') === 'private';
$hasOwner = $legal['operator'] !== '' || $legal['email'] !== '' || $legal['person'] !== '';
?>
<section class="hero">
    <div class="hero-emoji">🏛️</div>
    <h1>Impressum</h1>
    <p>Angaben gemäß § 5 DDG. Diese Seite füllt sich aus den Feldern der Admin-Einstellungen.</p>
</section>

<?php if (!$hasOwner && $legal['extra'] === ''): ?>
<section class="panel">
    <h3>Hinweis</h3>
    <p class="muted">Die Verwaltung hat hier noch keine Angaben hinterlegt. Ein Administrator trägt sie unter „Admin-Einstellungen → Impressum und Datenschutz“ ein.</p>
</section>
<?php else: ?>
<section class="panel">
    <?php if ($legal['operator'] !== ''): ?><h3><?= e($legal['operator']) ?></h3><?php endif; ?>
    <?php if ($isPrivate && $legal['operator'] === '' && $legal['person'] !== ''): ?><h3><?= e($legal['person']) ?></h3><?php endif; ?>
    <?php if (!$isPrivate && $legal['person'] !== ''): ?><p><strong>Vertreten durch:</strong> <?= e($legal['person']) ?></p><?php endif; ?>
    <?php if ($isPrivate): ?><p class="muted">Angebot einer Privatperson.</p><?php endif; ?>
    <?php if ($legal['street'] !== '' || $legal['zip'] !== '' || $legal['city'] !== ''): ?>
    <p><?= e(trim($legal['street'] . ($legal['street'] !== '' && ($legal['zip'] !== '' || $legal['city'] !== '') ? ', ' : '') . $legal['zip'] . ($legal['zip'] !== '' && $legal['city'] !== '' ? ' ' : '') . $legal['city'])) ?></p>
    <?php endif; ?>
    <?php if ($legal['phone'] !== ''): ?><p><strong>Telefon:</strong> <?= e($legal['phone']) ?></p><?php endif; ?>
    <?php if ($legal['email'] !== ''): ?><p><strong>E-Mail:</strong> <?= e($legal['email']) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($legal['extra'] !== ''): ?>
<section class="panel">
    <h3>Ergänzende Angaben</h3>
    <p><?= nl2br(e($legal['extra'])) ?></p>
</section>
<?php endif; ?>

<?php if ($legal['hosting'] !== ''): ?>
<section class="panel">
    <h3>Hosting</h3>
    <p><?= nl2br(e($legal['hosting'])) ?></p>
</section>
<?php endif; ?>
