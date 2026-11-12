<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Datenschutzerklärung (aus den Admin-Einstellungen, #160)
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.16.0
 */
/** @var array<string, string> $legal */
$hasPrivacy = $legal['dsb'] !== '' || $legal['hosting'] !== '' || $legal['data'] !== '' || $legal['text'] !== '';
?>
<section class="hero">
    <div class="hero-emoji">🔒</div>
    <h1>Datenschutzerklärung</h1>
    <p>Informationen zur Verarbeitung personenbezogener Daten auf dieser Plattform. Die Angaben stammen aus den Admin-Einstellungen.</p>
</section>

<?php if (!$hasPrivacy): ?>
<section class="panel">
    <h3>Hinweis</h3>
    <p class="muted">Die Verwaltung hat hier noch keine Angaben hinterlegt. Ein Administrator trägt sie unter „Admin-Einstellungen → Impressum und Datenschutz“ ein.</p>
</section>
<?php else: ?>
<?php if ($legal['dsb'] !== ''): ?>
<section class="panel">
    <h3>Datenschutzbeauftragte Person</h3>
    <p><?= nl2br(e($legal['dsb'])) ?></p>
</section>
<?php endif; ?>

<?php if ($legal['hosting'] !== ''): ?>
<section class="panel">
    <h3>Hosting</h3>
    <p><?= nl2br(e($legal['hosting'])) ?></p>
</section>
<?php endif; ?>

<?php if ($legal['data'] !== ''): ?>
<section class="panel">
    <h3>Verarbeitete Daten</h3>
    <p><?= nl2br(e($legal['data'])) ?></p>
</section>
<?php endif; ?>

<?php if ($legal['text'] !== ''): ?>
<section class="panel">
    <h3>Ergänzende Hinweise</h3>
    <p><?= nl2br(e($legal['text'])) ?></p>
</section>
<?php endif; ?>
<?php endif; ?>
