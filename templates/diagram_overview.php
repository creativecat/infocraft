<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Diagramme: Übersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="panel">
    <div class="panel-head">
        <h3><span class="icn ic-plus" aria-hidden="true"></span> Neues Diagramm anlegen</h3>
    </div>
    <form method="post" action="<?= url('diagramme/anlegen') ?>" class="form form-row">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="200" placeholder="z. B. Notenrechner"></label>
        <label class="field"><span>Typ</span>
            <div class="kind-picker">
                <label class="kind-btn"><input type="radio" name="kind" value="manuell" checked><span><span class="icn ic-design" aria-hidden="true"></span> Klassendiagramm</span></label>
                <label class="kind-btn"><input type="radio" name="kind" value="struktogramm"><span><span class="icn ic-cube" aria-hidden="true"></span> Struktogramm</span></label>
                <label class="kind-btn"><input type="radio" name="kind" value="datenfluss"><span><span class="icn ic-wave" aria-hidden="true"></span> Datenflussdiagramm</span></label>
            </div>
        </label>
        <button class="btn btn-primary" type="submit" title="Anlegen und bearbeiten"><span class="icn ic-plus" aria-hidden="true"></span></button>
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
        <div class="card-icon"><?= $diagram->kind === 'struktogramm' ? '<span class="icn ic-cube" aria-hidden="true"></span>' : ($diagram->kind === 'datenfluss' ? '<span class="icn ic-wave" aria-hidden="true"></span>' : '<span class="icn ic-design" aria-hidden="true"></span>') /* Runde 39: Icon-HTML darf nicht durch e() escapen, sonst steht der Span als Text in der Karte */ ?></div>
        <h2><?= e($diagram->title) ?></h2>
        <p class="card-desc muted"><?= e($diagram->kindLabel()) ?></p>
        <div class="card-meta">
            <a class="btn btn-primary" href="<?= url('diagramme/' . (int) $diagram->id) ?>" title="Bearbeiten"><span class="icn ic-pencil" aria-hidden="true"></span></a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
