<?php /* InfoBook: Seite bearbeiten/ansehen (#62). Bloecke: Text, Bild, Audio, Video, Einbettung, Zeichnung (Apple Pencil). */
?>
<?php /* #67: Zurueck ganz links, rechts Vorschau/Lesemodus und kleines aufklappbares Verteilen, Autospeichern statt Speichern-Button. */ ?>
<?php /* #68: Links steht immer die Navigation des InfoBooks (Abschnitte und
 * Seiten), rechts daneben Titelzeile, Editor bzw. Lesemodus. */ ?>
<?php $preview = (bool) ($preview ?? false); ?>
<?php $navAreas = ['lernbereich' => ['📖', 'Lernbereich'], 'gemeinsam' => ['🤝', 'Zusammenarbeit'], 'eigen' => ['🔒', 'Mein Bereich']]; ?>
<?php /* #70: Im Lesemodus (?lesen=1) bleibt die linke Navigation im Lesemodus:
 * Seiten-Links haengen das Suffix an, Abschnitts-Links fuehren auf die erste
 * Seite des Abschnitts, wenn der Abschnitt Seiten hat. */ ?>
<?php $navLesen = $preview ? '?lesen=1' : ''; ?>
<?php /* #100: Kopfzeile ueber die volle Breite: Inhalt-Knopf (#100) direkt
 * neben dem Zurueck-Button, darunter Seitenleiste neben dem Hauptbereich,
 * damit die Seitenuebersicht direkt neben dem "Freie Flaeche"-Fenster sitzt. */ ?>
<section class="hero ibook-hero-compact">
    <div class="btn-row ibook-topnav">
        <?php /* #82/#100: Knopf klappt die Seitenleiste ein und aus, Zustand
         * bleibt im Browser gespeichert (app.js). Beschriftung "Inhalt". */ ?>
        <button type="button" class="btn btn-ghost ibook-pagenav-fold" id="ibook-pagenav-fold" aria-expanded="true" title="Seitenübersicht einklappen">Inhalt ◂</button>
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $section['id']) ?>">← <?= e($book->title) ?></a>
        <?php /* #83: Titel und Meta rutschen in die Kopfzeile zu den Buttons,
         * die Seite verliert vertikalen Platz. IDs bleiben (Autospeichern). */ ?>
        <div class="ibook-topnav-title">
            <h1>📄 <span id="ibook-page-title"><?= e((string) $page['title']) ?></span></h1>
            <p class="muted" id="ibook-page-meta"><?= e($section['title']) ?> · Letzte Änderung: <?= e((string) ($page['updated_at'] ?? 'gerade eben')) ?></p>
        </div>
        <div class="ibook-topnav-right">
            <?php if ($canWrite): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id'] . '?lesen=1') ?>" target="_blank" rel="noopener" title="Seite im Lesemodus in einem neuen Tab öffnen">👀 Vorschau (Lesemodus)</a>
            <?php endif; ?>
            <?php if ($preview): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" title="Zurück zur Bearbeitung">✏️ Bearbeiten</a>
            <?php endif; ?>
            <?php if (\Infocraft\Auth::isTeacher() && $canWrite): ?>
            <details class="ibook-distribute-details">
                <summary class="btn btn-ghost">📤 An Klasse verteilen</summary>
                <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/verteilen') ?>" class="form ibook-distribute-panel">
                    <?= csrf_field() ?>
                    <input type="hidden" name="page_id" value="<?= (int) $page['id'] ?>">
                    <div class="form-row form">
                        <label class="field"><span>Klasse</span>
                            <select name="class_id" required>
                                <?php $classes = \Infocraft\Models\SchoolClass::forTeacher($user->id, $user->role === 'admin'); ?>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field"><span>Zielbereich</span>
                            <select name="bereich">
                                <option value="lernbereich">📖 Lernbereich (Schülerinnen und Schüler lesen)</option>
                                <option value="gemeinsam">🤝 Zusammenarbeit (alle schreiben mit)</option>
                            </select>
                        </label>
                    </div>
                    <div class="btn-row"><button class="btn btn-primary" type="submit" title="Kopiert diese Seite mit allen Medien ins Klassen-InfoBook">📤 Seite verteilen</button></div>
                </form>
            </details>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="ibook-page-layout">
<aside class="ibook-pagenav" id="ibook-pagenav" aria-label="Navigation dieses InfoBooks">
    <?php /* #71: Die Navigation klappt auf und zu: Kopfzeile antippen. Ohne
     * Javascript nutzbar (details/summary), Standardzustand offen. */ ?>
    <details class="ibook-pagenav-box" open>
    <summary class="ibook-pagenav-head" title="Navigation ein- und ausklappen">📓 <?= e($book->title) ?></summary>
    <?php
    $navArea = null;
    foreach ($nav as $navEntry):
        $navSec = $navEntry['section'];
        $navAreaKey = (string) $navSec['area'];
        if ($navAreaKey !== $navArea):
            $navArea = $navAreaKey;
            $navIcon = $navAreas[$navAreaKey][0] ?? '📄';
            $navName = $navAreas[$navAreaKey][1] ?? $navAreaKey;
    ?>
    <div class="ibook-pagenav-area"><?= $navIcon ?> <?= e($navName) ?></div>
    <?php endif; ?>
    <?php $navSecHref = ($preview && !empty($navEntry['pages'])) ? url('infobuecher/' . (int) $book->id . '/seite/' . (int) $navEntry['pages'][0]['id'] . '?lesen=1') : url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $navSec['id']); ?>
    <a class="ibook-pagenav-section<?= (int) $section['id'] === (int) $navSec['id'] ? ' active' : '' ?>" href="<?= $navSecHref ?>"><?= e($navSec['title']) ?></a>
    <?php foreach ($navEntry['pages'] as $navPage): ?>
    <?php /* #101: Seiten mit Schreibrecht sind per Drag&Drop sortierbar,
     * app.js schickt die neue Reihenfolge an /seiten-reihenfolge. */ ?>
    <?php $navDrag = !empty($navEntry['canWrite']) ? ' draggable="true" data-page-id="' . (int) $navPage['id'] . '"' : ''; ?>
    <a class="ibook-pagenav-page<?= (int) $navPage['id'] === (int) $page['id'] ? ' active' : '' ?><?= !empty($navEntry['canWrite']) ? ' ibook-pagenav-drag' : '' ?>" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $navPage['id'] . $navLesen) ?>"<?= $navDrag ?> title="Seite öffnen"><?= e((string) $navPage['title']) ?></a>
    <?php endforeach; ?>
    <?php /* #72: Seite direkt im Abschnitt anlegen, ohne Umweg ueber die
     * Buch-Uebersicht. Nach dem Anlegen oeffnet sich die neue Seite. */ ?>
    <?php if (!empty($navEntry['canWrite'])): ?>
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="ibook-navnew">
        <?= csrf_field() ?>
        <input type="hidden" name="section_id" value="<?= (int) $navSec['id'] ?>">
        <input type="text" name="title" maxlength="160" placeholder="Neue Seite" aria-label="Neue Seite in diesem Abschnitt anlegen">
        <button class="btn btn-ghost" type="submit" title="Neue Seite in diesem Abschnitt anlegen (öffnet sich danach direkt)">➕</button>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php /* #72: Abschnitt direkt hier anlegen; Lehrkraefte waehlen den Bereich. */ ?>
    <?php if ($canWrite): ?>
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/abschnitt') ?>" class="form ibook-navnewsec">
        <?= csrf_field() ?>
        <label class="field"><span>Neuer Abschnitt</span>
            <input type="text" name="title" maxlength="160" placeholder="z. B. Schleifen">
            <?php if (\Infocraft\Auth::isTeacher()): ?>
            <select name="area">
                <option value="lernbereich">📖 Lernbereich</option>
                <option value="gemeinsam">🤝 Zusammenarbeit</option>
                <option value="eigen">🔒 Mein Bereich</option>
            </select>
            <?php else: ?>
            <input type="hidden" name="area" value="gemeinsam">
            <?php endif; ?>
        </label>
        <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Abschnitt anlegen">➕ Abschnitt anlegen</button></div>
    </form>
    <?php endif; ?>
    </details>
</aside>
<div class="ibook-pagemain">


<?php if (!$canWrite): ?>
<section class="panel">
    <h3>Seite lesen</h3>
    <p class="muted">Diese Seite gehört zum Lernbereich: Lesen ist frei, Änderungen macht die Lehrkraft.</p>
</section>
<?php foreach ($blocks as $block): ?>
<?= (string) $block['html'] ?>
<?php endforeach; ?>
<?php if ($blocks === []): ?>
<div class="notice"><strong>Noch keine Inhalte</strong></div>
<?php endif; ?>
<?php else: ?>

<form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" id="ibook-saveform" class="form">
    <?= csrf_field() ?>
    <section class="panel ibook-editor-panel">
        <h3>Titel</h3>
        <label class="field"><input type="text" name="title" id="ibook-title" maxlength="160" value="<?= e((string) $page['title']) ?>" placeholder="Titel der Seite"></label>

        <h3>Freie Fläche</h3>
        <div id="ibook-editor" data-book-id="<?= (int) $book->id ?>" data-page-id="<?= (int) $page['id'] ?>" data-nav="<?= e(json_encode($navPages, JSON_UNESCAPED_UNICODE)) ?>" data-diagrams="<?= e(json_encode($diagrams, JSON_UNESCAPED_UNICODE)) ?>" data-dbtemplates="<?= e(json_encode($dbTemplates, JSON_UNESCAPED_UNICODE)) ?>" data-codeclasses="<?= e(json_encode($codeClasses, JSON_UNESCAPED_UNICODE)) ?>" data-blocks="<?= e(json_encode($blocks, JSON_UNESCAPED_UNICODE)) ?>"></div>
        <p class="muted ibook-hint">Alles auf einer Fläche: Bilder per Drag &amp; Drop ablegen, mit dem Stift überall zeichnen (auch über Bildern), Textfelder frei platzieren. Oben in der Symbolleiste wechselst du zwischen Auswahl, Stift und Radierer und fügst Text, Bild, Audio, Video und Einbettungen hinzu. Änderungen speichern sich automatisch, ein Speichern-Button ist nicht nötig.</p>

        <input type="hidden" name="blocks" id="ibook-blocks-json" value="[]">
    </section>
</form>
<?php endif; ?>
</div>
</div>
<?php /* #77: Mermaid nur laden, wenn die Seite Diagramm-Elemente hat oder
 * gerade bearbeitet wird (der Editor rendert die Vorschau live). */ ?>
<?php $hasDiagram = $canWrite; ?>
<?php if (!$hasDiagram): ?>
    <?php foreach ($blocks as $block): ?>
        <?php $dgItems = is_array($block) && isset($block['items']) && is_array($block['items']) ? $block['items'] : []; ?>
        <?php foreach ($dgItems as $dgItem): ?>
            <?php if (is_array($dgItem) && (string) ($dgItem['kind'] ?? '') === 'diagram') { $hasDiagram = true; break 2; } ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($hasDiagram): ?>
<script src="<?= vendor_asset('assets/vendor/mermaid.min.js', 'https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js', 3000000) ?>"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: false }); }</script>
<?php endif; ?>
<?php /* #81: Die Java-VM laedt nur, wenn die Seite Code-Fenster hat oder
 * gerade bearbeitet wird (Standard-Sprache Java, System.out funktioniert). */ ?>
<?php $hasCode = $canWrite; ?>
<?php if (!$hasCode): ?>
    <?php foreach ($blocks as $block): ?>
        <?php $codeItems = is_array($block) && isset($block['items']) && is_array($block['items']) ? $block['items'] : []; ?>
        <?php foreach ($codeItems as $codeItem): ?>
            <?php if (is_array($codeItem) && (string) ($codeItem['kind'] ?? '') === 'code') { $hasCode = true; break 2; } ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($hasCode): ?>
<script src="<?= asset_url('assets/js/java-vm.js') ?>"></script>
<?php endif; ?>
<?php /* #105: highlight.js nur laden, wenn Code- oder Diagramm-Fenster
 * vorhanden sind - die Module haengen das Overlay selbst an (Java im
 * Code-Fenster, Mermaid im Diagramm-Quelltext). */ ?>
<?php if ($hasCode || $hasDiagram): ?>
<script src="<?= vendor_asset('assets/vendor/highlight.min.js', 'https://cdn.jsdelivr.net/npm/@highlightjs/cdn-assets@11.12.0/highlight.min.js', 100000) ?>"></script>
<?php endif; ?>
