<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: Seite
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.26.0 */ ?>
<?php /* InfoBook: Seite bearbeiten/ansehen (#62). Bloecke: Text, Bild, Audio, Video, Einbettung, Zeichnung (Apple Pencil). */ ?>
<?php /* #67: Autospeichern statt Speichern-Button, rechts Vorschau/Lesemodus
 * und kleines aufklappbares Verteilen. */ ?>
<?php /* #185: Gleiche Kopfzeile und gleiche Navigation wie die Abschnitts-
 * ansicht: Icon-Knopf fuer die Navigation, Breadcrumb Buch > Abschnitt >
 * Seite zeigt immer den Standort, das gemeinsame Partial ibook_nav.php
 * zeichnet die Seitenleiste.
 * #189: Die Vorschau (Lesemodus) oeffnet im gleichen Fenster, der Knopf
 * "Bearbeiten" fuehrt zurueck. #188: Der Praesentations-Link besagt,
 * dass Elemente einzeln per Klick erscheinen.
 * #193: Die Ueberschrift "Freie Flaeche" ist entfallen, die Flaeche
 * selbst spricht durch ihre Werkzeuge. */ ?>
<?php $preview = (bool) ($preview ?? false); ?>
<section class="hero ibook-hero-compact">
    <div class="btn-row ibook-topnav">
        <?php /* #82/#100/#185: Schmaler Icon-Knopf klappt die Seitenleiste
         * ein und aus, Zustand bleibt im Browser gespeichert (app.js). */ ?>
        <button type="button" class="ibook-navtoggle" id="ibook-pagenav-fold" aria-expanded="true" aria-label="Inhaltsnavigation ein- und ausklappen" title="Inhaltsnavigation einklappen"></button>
        <?php /* #201: Fuer Schuelerinnen und Schueler fuehrt die InfoBook-
         * Uebersicht sofort zurueck aufs eigene Klassenbuch, der Knopf
         * waere ins Leere gelaufen: Er zeigt deshalb auf den Buch-Start. */ ?>
        <?php if (\Infocraft\Auth::isTeacher()): ?>
        <a class="btn btn-ghost" href="<?= url('infobuecher') ?>" title="Zur Übersicht aller InfoBooks">← Alle InfoBooks</a>
        <?php else: ?>
        <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id) ?>" title="Zurück zum Start deines InfoBooks">← Buch-Start</a>
        <?php endif; ?>
        <?php /* #185: Breadcrumb als Standort-Anzeige: Buch startet auf der
         * zuletzt besuchten Seite, der Abschnitt fuehrt in die Abschnitts-
         * ansicht, die Seite ist das aktuelle Element (aria-current). */ ?>
        <div class="ibook-topnav-title">
            <nav class="ibook-crumbs" aria-label="Position in diesem InfoBook">
                <a class="ibook-crumb ibook-crumb-book" href="<?= url('infobuecher/' . (int) $book->id) ?>" title="Buch-Start: zuletzt besuchte Seite">📓 <?= e($book->title) ?></a>
                <span class="ibook-crumb-sep" aria-hidden="true">›</span>
                <a class="ibook-crumb" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $section['id']) ?>" title="Abschnitt mit allen Seiten anzeigen"><?= e($section['title']) ?></a>
                <span class="ibook-crumb-sep" aria-hidden="true">›</span>
                <span class="ibook-crumb ibook-crumb-here" aria-current="page">📄 <span id="ibook-page-title"><?= e((string) $page['title']) ?></span></span>
            </nav>
            <p class="muted" id="ibook-page-meta"><?= e($section['title']) ?> · Letzte Änderung: <?= e((string) ($page['updated_at'] ?? 'gerade eben')) ?></p>
        </div>
        <div class="ibook-topnav-right">
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/praesentation?abschnitt=' . (int) $section['id']) ?>" title="Alle Seiten dieses Abschnitts als Präsentation anzeigen: Schritte erscheinen einzeln per Klick, Reihenfolge im Editor festlegen">🎬 Präsentation</a>
            <?php if ($canWrite): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id'] . '?lesen=1') ?>" title="Seite im Lesemodus anzeigen">👀 Vorschau (Lesemodus)</a>
            <?php endif; ?>
            <?php if ($preview): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>" title="Zurück zur Bearbeitung">✏️ Bearbeiten</a>
            <?php endif; ?>
            <?php if (\Infocraft\Auth::isTeacher() && $canWrite): ?>
            <?php /* #202: Gemeinsames Verteilen-Panel mit Ziel-Abschnitt-Auswahl
                 * und Option auf einen neuen Abschnitt mit eigenem Titel. */ ?>
            <?php
            $distAction = url('infobuecher/' . (int) $book->id . '/verteilen');
            $distLabel = 'Seite an Klasse verteilen';
            $distTitle = 'Kopiert diese Seite mit allen Medien ins Klassen-InfoBook';
            $distHidden = [['page_id', (int) $page['id']]];
            ?>
            <?php include __DIR__ . '/ibook_distribute_panel.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="ibook-page-layout">
<?php /* #185: Gemeinsame Navigation mit der Abschnittsansicht. */ ?>
<?php include __DIR__ . '/ibook_nav.php'; ?>
<div class="ibook-pagemain">


<?php if (!$canWrite): ?>
<section class="panel">
    <h3>Seite lesen</h3>
    <?php /* #203: Der Hinweis sagt jetzt der Wahrheit: In der eigenen
         * Vorschau (Lesemodus) fuehrt "Bearbeiten" oben rechts zurueck in
         * den Editor, der alte Lernbereich-Text liess die Bearbeitung
         * dagegen verloren wirken. */ ?>
    <?php if ($preview): ?>
    <p class="muted">Du liest gerade die Vorschau deiner Seite. Oben rechts führt ✏️ Bearbeiten zurück in den Editor.</p>
    <?php elseif ((string) $section['area'] === 'gemeinsam'): ?>
    <p class="muted">Diese Seite gehört in den Bereich Zusammenarbeit: Schülerinnen und Schüler der Klasse schreiben hier gemeinsam.</p>
    <?php else: ?>
    <p class="muted">Diese Seite gehört zum Lernbereich: Lesen ist frei, Änderungen macht die Lehrkraft.</p>
    <?php endif; ?>
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
