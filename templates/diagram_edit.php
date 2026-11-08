<?php
$isStruktur = $diagram->kind === 'struktogramm';
$isDatenfluss = $diagram->kind === 'datenfluss';
$sourceLabel = 'Mermaid-Quelltext (classDiagram), die Vorschau aktualisiert sich beim Tippen';
if ($isStruktur) {
    $sourceLabel = 'Struktogramm-Quelltext (anweisung, wenn/dann/sonst, solange, wiederhole/bis) - die Struktur erscheint nach dem Speichern';
} elseif ($isDatenfluss) {
    $sourceLabel = 'Datenfluss-Quelltext (quelle "Name", senke "Name", speicher "Name", (funktionsName), Pfeile --> und -->|Daten|) - die Vorschau aktualisiert sich beim Tippen';
}
?>
<section class="panel">
    <div class="panel-head">
        <h3>📐 <?= e($diagram->title) ?> <span class="pill"><?= e($diagram->kindLabel()) ?></span></h3>
        <a class="btn btn-ghost" href="<?= url('diagramme') ?>" title="Zurück">←</a>
    </div>
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Titel</span><input type="text" name="title" value="<?= e($diagram->title) ?>" required maxlength="200"></label>
        <label class="field"><span><?= e($sourceLabel) ?></span>
            <div class="hlwrap" data-hl="mermaid">
                <textarea name="mermaid" id="diagram-source" rows="14" class="mono" spellcheck="false"><?= e($diagram->mermaid) ?></textarea>
            </div>
        </label>
        <?php if ($isStruktur): ?>
        <details>
            <summary class="muted">So funktioniert die Struktogramm-Syntax</summary>
            <p class="muted">Jede Zeile ist ein Schritt: <strong>anweisung</strong> "Text" ist eine Anweisung. <strong>wenn</strong> "Bedingung" <strong>dann</strong> ... <strong>sonst</strong> ... <strong>ende</strong> ist eine Verzweigung (sonst darf fehlen), <strong>solange</strong> "Bedingung" ... <strong>ende</strong> eine kopfgesteuerte und <strong>wiederhole</strong> ... <strong>bis</strong> "Bedingung" eine fußgesteuerte Schleife. Einrückungen sind erlaubt, die Struktur entsteht aber über die Schlüsselwörter. Kommentare beginnen mit %% oder //.</p>
        </details>
        <?php elseif ($isDatenfluss): ?>
        <details>
            <summary class="muted">So funktioniert die Datenfluss-Syntax</summary>
            <p class="muted">Quellen und Senken sind Rechtecke (<strong>quelle</strong> "Lehrkraft", <strong>senke</strong> "Zeugnis"), Funktionen sind Kreise in Klammern (<strong>(notenErfassen)</strong>), Datenspeicher sind Zylinder (<strong>speicher</strong> "Schuelerliste"). Pfeile verbinden alles: <strong>--></strong> oder mit Datenbeschriftung <strong>-->|Noten|</strong>. Kommentare beginnen mit %% oder //.</p>
        </details>
        <?php endif; ?>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
    </form>
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id . '/loeschen') ?>" class="form-inline" onsubmit="return confirm('Diagramm wirklich löschen?');">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit" title="Diagramm löschen">🗑️</button>
    </form>
</section>

<?php if ($isStruktur): ?>
<section class="panel">
    <div class="panel-head">
        <h3>👁️ Vorschau (Struktogramm)</h3>
    </div>
    <?php if ($struktogramm['error'] !== null): ?>
    <div class="notice">
        <strong>Struktogramm kann nicht gezeichnet werden</strong>
        <p><?= e($struktogramm['error']) ?></p>
    </div>
    <?php else: ?>
    <div class="nassi-wrap"><?= $struktogramm['html'] /* Text wird beim Bauen maskiert (src/DiagramDsl.php) */ ?></div>
    <?php endif; ?>
    <p class="muted">Die Vorschau aktualisiert sich nach dem Speichern. Zum Mitnehmen oder Austeilen: Seite oder Struktogramm drucken (Strg+P).</p>
    <details>
        <summary class="muted">Struktogramm-Quelltext anzeigen (zum Weiterverwenden)</summary>
        <pre class="code"><code class="language-mermaid"><?= e($diagram->mermaid) ?></code></pre>
    </details>
</section>
<?php else: ?>
<section class="panel" data-diagram-page="1"<?php if ($isDatenfluss): ?> data-diagram-kind="datenfluss"<?php endif; ?>>
    <div class="panel-head">
        <h3>👁️ Vorschau</h3>
        <div class="btn-row">
            <button class="btn btn-ghost" id="diagram-refresh" type="button" title="Aktualisieren">↻</button>
            <button class="btn btn-primary" id="diagram-png" type="button" data-png-name="<?= e(preg_replace('/[^\w.-]+/', '_', $diagram->title)) ?>">🖼️ PNG herunterladen</button>
        </div>
    </div>
    <div class="diagram-preview" id="diagram-preview"<?php if ($isDatenfluss): ?> data-mermaid="<?= e($datenfluss['mermaid'] ?? '') ?>"<?php endif; ?>>
        <p class="muted">Wird gezeichnet ...</p>
    </div>
    <?php if ($isDatenfluss && ($datenfluss['error'] ?? null) !== null): ?>
    <div class="notice">
        <strong>Datenflussdiagramm kann nicht gezeichnet werden</strong>
        <p><?= e($datenfluss['error']) ?></p>
    </div>
    <?php endif; ?>
    <details>
        <summary class="muted"><?= $isDatenfluss ? 'Datenfluss-Quelltext anzeigen' : 'Mermaid-Quelltext anzeigen' ?> (zum Weiterverwenden, z. B. in Präsentationen)</summary>
        <pre class="code"><code class="language-mermaid"><?= e($isDatenfluss ? ($datenfluss['mermaid'] !== '' ? $datenfluss['mermaid'] : $diagram->mermaid) : $diagram->mermaid) ?></code></pre>
    </details>
</section>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: false }); }</script>
<?php endif; ?>
