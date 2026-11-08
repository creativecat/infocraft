<section class="hero">
    <div class="hero-emoji">📐</div>
    <h1>Diagramme</h1>
    <p>Struktur sichtbar machen: Klassendiagramme (Mermaid), Struktogramme (Nassi-Shneiderman) und Datenflussdiagramme zeichnen - oder Klassendiagramme automatisch aus Java-, Python- und SQL-Skripten erzeugen. Alles bleibt gespeichert und lässt sich exportieren.</p>
</section>

<section class="panel">
    <div class="panel-head">
        <h3>➕ Neues Diagramm anlegen</h3>
    </div>
    <form method="post" action="<?= url('diagramme/anlegen') ?>" class="form form-row">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Notenrechner"></label>
        <label class="field"><span>Typ</span>
            <select name="kind">
                <option value="manuell">📐 Klassendiagramm (Mermaid)</option>
                <option value="struktogramm">🧱 Struktogramm (Nassi-Shneiderman)</option>
                <option value="datenfluss">🌊 Datenflussdiagramm</option>
            </select>
        </label>
        <button class="btn btn-primary" type="submit" title="Anlegen und bearbeiten">➕</button>
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
    <p>Lege oben dein erstes Diagramm an: ein Klassendiagramm, ein Struktogramm oder ein Datenflussdiagramm. Klassendiagramme lassen sich zusätzlich aus einem Java- oder Python-Skript (Code-Werkstatt) bzw. aus einer SQL-Datenbank (SQL-Werkstatt) erzeugen.</p>
</div>
<?php else: ?>
<div class="cards">
    <?php foreach ($diagrams as $diagram): ?>
    <div class="card">
        <div class="card-icon"><?= e($diagram->kind === 'struktogramm' ? '🧱' : ($diagram->kind === 'datenfluss' ? '🌊' : '📐')) ?></div>
        <h2><?= e($diagram->title) ?></h2>
        <p class="card-desc muted"><?= e($diagram->kindLabel()) ?></p>
        <div class="card-meta">
            <a class="btn btn-primary" href="<?= url('diagramme/' . (int) $diagram->id) ?>" title="Bearbeiten">✏️</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
