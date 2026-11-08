<?php
$isStruktur = $diagram->kind === 'struktogramm';
$isDatenfluss = $diagram->kind === 'datenfluss';
$isKlassendiagramm = !$isStruktur && !$isDatenfluss;
// Auto erzeugte Diagramme (aus Java, Python, SQL) behalten ihren Typ, alle
// selbst angelegten lassen sich ueber die Buttons oben umschalten.
$switchable = in_array($diagram->kind, ['manuell', 'struktogramm', 'datenfluss'], true);
$sourceLabels = [
    'manuell' => 'Klassendiagramm-Quelltext - die Vorschau aktualisiert sich beim Tippen',
    'struktogramm' => 'Struktogramm-Quelltext (anweisung, wenn/dann/sonst, solange, wiederhole/bis) - die Struktur erscheint live beim Tippen',
    'datenfluss' => 'Datenfluss-Quelltext (quelle "Name", senke "Name", speicher "Name", (funktionsName), Pfeile --> und -->|Daten|) - die Vorschau aktualisiert sich beim Tippen',
];
$hlLang = $isStruktur ? 'struktogramm' : ($isDatenfluss ? 'datenfluss' : 'mermaid');
$currentLabel = $isStruktur ? 'struktogramm' : ($isDatenfluss ? 'datenfluss' : 'manuell');
// Die Typzeile (classDiagram, struktogramm, datenfluss) tippt niemand mehr:
// Beim Speichern entfernt der Server sie, fuer den Export setzt er sie wieder dazu.
$exportSource = $diagram->mermaid;
if ($isKlassendiagramm && stripos(ltrim($exportSource), 'classDiagram') !== 0) {
    $exportSource = "classDiagram\n" . $exportSource;
}
?>
<section class="panel">
    <div class="panel-head">
        <a class="btn btn-ghost" href="<?= url('diagramme') ?>" title="Zurück">←</a>
        <h3>📐 <?= e($diagram->title) ?> <span class="pill" id="diagram-kind-pill"><?= e($diagram->kindLabel()) ?></span></h3>
    </div>
</section>

<div class="diagram-workbench">
<section class="panel">
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id) ?>" class="form" id="diagram-form">
        <?= csrf_field() ?>
        <?php if ($switchable): ?>
        <div class="field"><span>Diagrammtyp</span>
            <div class="kind-picker" data-kind-switch="1">
                <label class="kind-btn"><input type="radio" name="kind" value="struktogramm"<?= $isStruktur ? ' checked' : '' ?>><span>🧱 Struktogramm</span></label>
                <label class="kind-btn"><input type="radio" name="kind" value="manuell"<?= $isKlassendiagramm ? ' checked' : '' ?>><span>📐 Klassendiagramm</span></label>
                <label class="kind-btn"><input type="radio" name="kind" value="datenfluss"<?= $isDatenfluss ? ' checked' : '' ?>><span>🌊 Datenflussdiagramm</span></label>
            </div>
            <p class="muted">Der Typwechsel speichert sofort, die Vorschau rechts passt sich direkt an. Alles andere speichert sich automatisch beim Tippen.</p>
        </div>
        <?php endif; ?>
        <label class="field"><span>Titel</span><input type="text" name="title" id="diagram-title" value="<?= e($diagram->title) ?>" required maxlength="200"></label>
        <label class="field">
            <span<?php if ($switchable): ?> data-source-label="1"<?php endif; ?>>
                <?php foreach ($sourceLabels as $labelKind => $labelText): ?>
                <span data-label-kind="<?= e($labelKind) ?>"<?= $labelKind === $currentLabel ? '' : ' hidden' ?>><?= e($labelText) ?></span>
                <?php endforeach; ?>
            </span>
            <div class="hlwrap" data-hl="<?= e($hlLang) ?>">
                <textarea name="mermaid" id="diagram-source" rows="18" class="mono" spellcheck="false"><?= e($diagram->mermaid) ?></textarea>
            </div>
        </label>
        <div class="syntax-help" data-syntax-help="1">
            <details class="syntax-help-block" data-help-kind="struktogramm"<?= $isStruktur ? '' : ' hidden' ?>>
                <summary class="muted">So funktioniert die Struktogramm-Syntax</summary>
                <table class="table syntax-table">
                    <thead><tr><th>Schlüsselwort</th><th>Bedeutung</th><th>Beispiel</th></tr></thead>
                    <tbody>
                        <tr><td><strong>anweisung</strong></td><td>Ein Schritt (Rechteck)</td><td>anweisung "Note eintragen"</td></tr>
                        <tr><td><strong>wenn</strong> ... <strong>dann</strong> ... <strong>sonst</strong> ... <strong>ende</strong></td><td>Verzweigung (sonst darf fehlen)</td><td>wenn "Note &gt; 2" dann / anweisung "Lob" / sonst / anweisung "Üben" / ende</td></tr>
                        <tr><td><strong>solange</strong> ... <strong>ende</strong></td><td>Schleife mit Prüfung am Anfang</td><td>solange "Zähler &lt; 10" / anweisung "zählen" / ende</td></tr>
                        <tr><td><strong>wiederhole</strong> ... <strong>bis</strong></td><td>Schleife mit Prüfung am Ende</td><td>wiederhole / anweisung "würfeln" / bis "Sechs gewürfelt"</td></tr>
                        <tr><td>%% oder //</td><td>Kommentar</td><td>%% Hier geht es los</td></tr>
                    </tbody>
                </table>
                <p class="muted">Beispiel komplett:</p>
                <pre class="code"><code>anweisung "Start"
wenn "regnet?" dann
  anweisung "Regenschirm mitnehmen"
sonst
  anweisung "Sonne genießen"
ende</code></pre>
            </details>
            <details class="syntax-help-block" data-help-kind="manuell"<?= $isStruktur || $isDatenfluss ? ' hidden' : '' ?>>
                <summary class="muted">So funktioniert die Klassendiagramm-Syntax</summary>
                <table class="table syntax-table">
                    <thead><tr><th>Element</th><th>Bedeutung</th><th>Beispiel</th></tr></thead>
                    <tbody>
                        <tr><td><strong>class Name { }</strong></td><td>Klasse anlegen, Attribute und Methoden in die Klammern</td><td>class Tier { +String name +gibLaut() }</td></tr>
                        <tr><td><strong>+</strong> und <strong>-</strong></td><td>öffentlich und privat (Datenkapselung)</td><td>-double note +getNote()</td></tr>
                        <tr><td>A <strong>&lt;|--</strong> B</td><td>Vererbung: B erbt von A</td><td>Tier &lt;|-- Hund</td></tr>
                        <tr><td>A <strong>--</strong> B</td><td>Beziehung (kennt / benutzt)</td><td>Kunde --&gt; Bestellung</td></tr>
                        <tr><td>A <strong>o--</strong> B / A <strong>*--</strong> B</td><td>Aggregation (hat) / Komposition (gehört fest dazu)</td><td>Fahrradverleih o-- Fahrrad</td></tr>
                        <tr><td>&lt;&lt;interface&gt;&gt; / &lt;&lt;abstract&gt;&gt;</td><td>Schnittstelle / abstrakte Klasse (Zeile in der Klasse)</td><td>class Tier { &lt;&lt;abstract&gt;&gt; }</td></tr>
                        <tr><td>%% oder //</td><td>Kommentar</td><td>%% Mein erstes Diagramm</td></tr>
                    </tbody>
                </table>
                <p class="muted">Beispiel komplett:</p>
                <pre class="code"><code>class Tier {
  +String name
  +gibLaut()
}
class Hund {
  +gibLaut()
}
Tier &lt;|-- Hund</code></pre>
            </details>
            <details class="syntax-help-block" data-help-kind="datenfluss"<?= $isDatenfluss ? '' : ' hidden' ?>>
                <summary class="muted">So funktioniert die Datenfluss-Syntax</summary>
                <table class="table syntax-table">
                    <thead><tr><th>Schlüsselwort</th><th>Bedeutung</th><th>Beispiel</th></tr></thead>
                    <tbody>
                        <tr><td><strong>quelle</strong> "Name"</td><td>Quelle (Rechteck)</td><td>quelle "Lehrkraft"</td></tr>
                        <tr><td><strong>senke</strong> "Name"</td><td>Senke (Rechteck)</td><td>senke "Zeugnis"</td></tr>
                        <tr><td><strong>speicher</strong> "Name"</td><td>Datenspeicher (Zylinder)</td><td>speicher "Schuelerliste"</td></tr>
                        <tr><td><strong>(funktionsName)</strong></td><td>Funktion (Kreis)</td><td>(notenErfassen)</td></tr>
                        <tr><td><strong>--&gt;</strong></td><td>Pfeil verbindet zwei Elemente (fließt von oben nach unten)</td><td>quelle "Eingabe" --&gt; (verarbeiten)</td></tr>
                        <tr><td><strong>--&gt;|Daten|</strong></td><td>Pfeil mit Datenbeschriftung</td><td>(verarbeiten) --&gt;|Ergebnis| senke "Ausgabe"</td></tr>
                        <tr><td>%% oder //</td><td>Kommentar</td><td>%% Notenverwaltung</td></tr>
                    </tbody>
                </table>
                <p class="muted">Beispiel komplett:</p>
                <pre class="code"><code>quelle "Lehrkraft" --> (notenErfassen)
(notenErfassen) --> speicher "Schuelerliste"
speicher "Schuelerliste" -->|Noten| senke "Zeugnis"</code></pre>
            </details>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Speichern">💾</button></div>
    </form>
    <form method="post" action="<?= url('diagramme/' . (int) $diagram->id . '/loeschen') ?>" class="form-inline" onsubmit="return confirm('Diagramm wirklich löschen?');">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" type="submit" title="Diagramm löschen">🗑️</button>
    </form>
</section>

<section class="panel" data-diagram-page="1" data-diagram-kind="<?= e($isStruktur ? 'struktogramm' : ($isDatenfluss ? 'datenfluss' : 'manuell')) ?>">
    <div class="panel-head">
        <h3 id="diagram-preview-title">👁️ <?= $isStruktur ? 'Vorschau (Struktogramm)' : 'Vorschau' ?></h3>
        <div class="btn-row">
            <button class="btn btn-ghost" id="diagram-fit" type="button"<?= $isStruktur ? ' hidden' : '' ?> title="An Fenster anpassen (Umschalten zur Originalgröße)">⤢</button>
            <button class="btn btn-ghost" id="diagram-fullscreen" type="button" title="Vollbild (Esc beendet)">⛶</button>
            <button class="btn btn-ghost" id="diagram-refresh" type="button" title="Neu zeichnen">↻</button>
            <button class="btn btn-primary" id="diagram-png" type="button"<?= $isStruktur ? ' hidden' : '' ?> data-png-name="<?= e(preg_replace('/[^\w.-]+/', '_', $diagram->title)) ?>">🖼️ PNG herunterladen</button>
        </div>
    </div>
    <?php if ($isStruktur): ?>
        <?php if ($struktogramm['error'] !== null): ?>
        <div class="notice" id="diagram-nassi-error">
            <strong>Struktogramm kann nicht gezeichnet werden</strong>
            <p><?= e($struktogramm['error']) ?></p>
        </div>
        <?php else: ?>
        <div class="nassi-wrap" id="nassi-preview"><?= $struktogramm['html'] /* Text wird beim Bauen maskiert (src/DiagramDsl.php) */ ?></div>
        <?php endif; ?>
        <p class="muted">Zum Mitnehmen oder Austeilen: Seite oder Struktogramm drucken (Strg+P).</p>
    <?php else: ?>
    <div class="diagram-preview" id="diagram-preview">
        <p class="muted">Wird gezeichnet ...</p>
    </div>
    <?php if ($isDatenfluss && ($datenfluss['error'] ?? null) !== null): ?>
    <div class="notice">
        <strong>Datenflussdiagramm kann nicht gezeichnet werden</strong>
        <p><?= e($datenfluss['error']) ?></p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <p class="muted" id="diagram-status" hidden></p>
    <p class="muted diagram-savestatus" id="diagram-savestatus" hidden></p>
    <details>
        <summary class="muted"><?= $isDatenfluss ? 'Datenfluss-Quelltext anzeigen' : ($isStruktur ? 'Struktogramm-Quelltext anzeigen' : 'Klassendiagramm-Quelltext anzeigen') ?> (zum Weiterverwenden, z. B. in Präsentationen)</summary>
        <pre class="code"><code class="language-<?= e($isStruktur ? 'struktogramm' : 'mermaid') ?>"><?= e($isDatenfluss ? ($datenfluss['mermaid'] !== '' ? $datenfluss['mermaid'] : $diagram->mermaid) : ($isStruktur ? $diagram->mermaid : $exportSource)) ?></code></pre>
    </details>
</section>
</div>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: false }); }</script>
<script src="https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11/highlight.min.js"></script>
