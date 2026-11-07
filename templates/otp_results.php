<section class="hero">
    <div class="hero-emoji">🔑</div>
    <h1>Neue Einmalpasswörter</h1>
    <p>Die Passwörter gelten ab sofort und sind nur diesmal sichtbar. Bitte ausdrucken oder sicher notieren.</p>
</section>

<?php if ($rows === []): ?>
<div class="notice">
    <strong>Nichts anzuzeigen</strong>
    <p>Es liegen keine neuen Einmalpasswörter vor. Neue OTPs erzeugst du unter "Meine Klassen" oder beim CSV-Import.</p>
    <p><a class="btn btn-primary" href="<?= url('unterricht/klassen') ?>">Zu meinen Klassen</a></p>
</div>
<?php else: ?>
<div class="notice">
    <strong>💡 So funktioniert die Satzbaulogik</strong>
    <p>Aus dem Merksatz werden die Anfangsbuchstaben der vier Wörter genommen, dazu zwei Ziffern und ein Sonderzeichen.
    Beispiel: "Apfel, Berg, Katze und Wolke" ergibt <strong>Abkw42!</strong> (A, B, K, W plus Zufallsziffern und -zeichen).
    Der Merksatz ist die Eselsbrücke, damit sich Schülerinnen und Schüler das Einmalpasswort merken können, ohne es aufschreiben zu müssen.</p>
</div>
<p class="muted">Hinweis: Diese Übersicht wird nach dem Verlassen der Seite gelöscht und lässt sich nicht erneut abrufen.</p>
<table class="table otp-table">
    <thead><tr><th>Name</th><th>Klasse</th><th>Anmeldename</th><th>Einmalpasswort</th><th>Merksatz (Satzbaulogik)</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
    <tr>
        <td><?= e($row['name']) ?></td>
        <td><?= $row['class'] !== null ? e($row['class']) : '-' ?></td>
        <td class="mono"><?= e($row['login']) ?></td>
        <td class="mono"><strong><?= e($row['otp']) ?></strong></td>
        <td>"<?= e($row['sentence']) ?>"</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div class="btn-row">
    <button class="btn btn-ghost" type="button" onclick="window.print()">🖨️ Liste drucken</button>
    <a class="btn btn-primary" href="<?= url('unterricht/klassen') ?>">Fertig, zu meinen Klassen</a>
</div>
<?php endif; ?>

