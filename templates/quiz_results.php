<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Quiz: Ergebnisse für Lehrkräfte
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="hero">
    <div class="hero-emoji">🏆</div>
    <h1>Ergebnisse: <?= e($quiz->title) ?></h1>
    <p><a href="<?= url('quiz/' . $quiz->id) ?>">zum Quiz</a> · <a href="<?= url('quiz') ?>">zur Übersicht</a></p>
</section>

<?php if ($attempts === []): ?>
<div class="notice">
    <strong>Noch keine Runden</strong>
    <p>Noch niemand hat dieses Quiz gespielt.</p>
</div>
<?php else: ?>
<section class="panel">
    <table class="table">
        <thead>
            <tr><th>Person</th><th>Klasse</th><th>Punkte</th><th>Abgeschlossen</th></tr>
        </thead>
        <tbody>
            <?php foreach ($attempts as $attempt): ?>
            <tr>
                <td><?= e($attempt['userName']) ?></td>
                <td><?= $attempt['className'] !== null ? e($attempt['className']) : '-' ?></td>
                <td><strong><?= e($attempt['score']) ?></strong> / <?= e($attempt['total']) ?></td>
                <td class="muted"><?= e($attempt['finishedAt']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

