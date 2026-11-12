<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Prüfung: Korrekturübersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php /** @var \Infocraft\Models\Exam $exam */ ?>
<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('pruefungen/' . $exam->id) ?>" title="Verwaltung">←</a>
        <h3>Korrektur: <?= e($exam->title) ?> <span class="status status-<?= e($exam->phase === 'herausgegeben' ? 'fertig' : 'in-arbeit') ?>"><?= e($exam->phase) ?></span></h3>
        <span class="muted"><?= count($tasks) ?> Teilaufgaben · <?= count($summary) ?> Arbeiten</span>
    </div>
</section>

<?php if ($exam->phase === 'korrektur'): ?>
<section class="panel">
    <h3>API-Korrektur (anonymisiert)</h3>
    <p class="muted">Ein Klick bewertet alle Arbeiten dieser Prüfung über die konfigurierte API. Die API sieht nur Pseudonyme wie Arbeit A und Arbeit B, keine Namen. Jeder Vorschlag muss anschließend gegengeprüft und gespeichert werden, erst dann zählt er als geprüft.</p>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/api-korrektur') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"<?= $apiConfigured ? '' : ' disabled title="In der Konfiguration fehlt exam.api (url)"' ?>>🤖</button>
        <?php if (!$apiConfigured): ?>
        <span class="muted"> Nicht konfiguriert: in config/config.php unter exam.api die url (und optional mode: json oder chat) eintragen.</span>
        <?php endif; ?>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h3>Alle Arbeiten</h3>
    <?php if ($summary === []): ?>
    <div class="notice"><strong>Keine Arbeiten</strong><p>Es hat keine Person teilgenommen.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>Person</th><th>Klasse</th><th>BE</th><th>Note</th><th>Bewertet</th><th>API-Vorschläge</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($summary as $row): ?>
        <tr>
            <td><?= e($row['work']['name']) ?></td>
            <td><?= e($row['work']['className'] ?? '-') ?></td>
            <td><?= e(number_format($row['totals']['reached'], 1, ',', '')) ?> / <?= e($row['totals']['total']) ?></td>
            <td><?= e($row['note']) ?></td>
            <td><?= $row['unchecked'] > 0 ? '<span class="status status-in-arbeit">' . e($row['graded']) . ' von ' . e(count($tasks)) . ' (ungeprüft: ' . e($row['unchecked']) . ')</span>' : '<span class="badge-solved">' . e($row['graded']) . ' von ' . e(count($tasks)) . '</span>' ?></td>
            <td><?= $row['apiCount'] > 0 ? e($row['apiCount']) : '-' ?></td>
            <td><a class="btn btn-ghost" href="<?= url('pruefungen/' . $exam->id . '/korrektur/' . $row['work']['userId']) ?>" title="Korrigieren">✅</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<?php if ($exam->phase === 'korrektur'): ?>
<section class="panel">
    <h3>Herausgabe der Ergebnisse</h3>
    <p class="muted">Herausgegeben wird erst, wenn jede Arbeit vollständig bewertet und jede Bewertung geprüft ist. Danach sehen alle Teilnehmer ihr Ergebnis mit Note und Musterlösungs-Vergleich.</p>
    <form method="post" action="<?= url('pruefungen/' . $exam->id . '/herausgeben') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"<?= $allGraded ? '' : ' disabled title="Noch nicht vollständig bewertet und geprüft"' ?>>📤</button>
        <?php if (!$allGraded): ?>
        <span class="muted"> Noch nicht alle Arbeiten sind vollständig bewertet und gegengeprüft.</span>
        <?php endif; ?>
    </form>
</section>
<?php endif; ?>
