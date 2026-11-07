<section class="hero">
    <div class="hero-emoji">📐</div>
    <h1>Klassendiagramme</h1>
    <p>Klassen sichtbar machen: Zeichne selbst Diagramme oder erzeuge sie automatisch aus Java-, Python- und SQL-Skripten. Alles bleibt gespeichert und lässt sich als PNG exportieren.</p>
</section>

<section class="panel">
    <div class="panel-head">
        <h3>➕ Neues Diagramm anlegen</h3>
    </div>
    <form method="post" action="<?= url('diagramme/anlegen') ?>" class="form form-row">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Schulverwaltung"></label>
        <button class="btn btn-primary" type="submit">Anlegen und bearbeiten</button>
    </form>
    <?php if (!$diagramAuto): ?>
    <div class="notice">
        <strong>Hinweis deiner Lehrkraft</strong>
        <p>Das automatische Erzeugen von Klassendiagrammen aus Skripten ist für deine Klasse deaktiviert. Eigene Diagramme kannst du jederzeit anlegen.</p>
    </div>
    <?php endif; ?>
</section>

<?php if ($diagrams === []): ?>
<div class="notice">
    <strong>Noch keine Diagramme</strong>
    <p>Lege oben dein erstes Klassendiagramm an oder erzeuge eins aus einem Java- oder Python-Skript (Code-Werkstatt) bzw. aus einer SQL-Datenbank (SQL-Werkstatt).</p>
</div>
<?php else: ?>
<div class="cards">
    <?php foreach ($diagrams as $diagram): ?>
    <div class="card">
        <div class="card-icon">📐</div>
        <h2><?= e($diagram->title) ?></h2>
        <p class="card-desc muted"><?= e($diagram->kindLabel()) ?></p>
        <div class="card-meta">
            <a class="btn btn-primary" href="<?= url('diagramme/' . (int) $diagram->id) ?>">Bearbeiten</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
