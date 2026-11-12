<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: gemeinsame Seitennavigation (Partial für Buch- und Seitenansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.22.0 */ ?>
<?php /* #185: Buchansicht (Abschnittsansicht) und Seitenansicht teilen sich
 * diese Navigation, damit die beiden im Kern gleichen Ansichten nicht wieder
 * auseinanderlaufen. Inhalt: Bereiche mit Abschnitten, genau ein
 * "Neue Seite"-Feld am aktiven Abschnitt, "Neuer Abschnitt" aufklappbar.
 * #186: Jeder Abschnitt ist ein eigener aufklappbarer Eintrag (details),
 * der aktive Abschnitt steht offen, alle anderen zu; aufgeklappt zeigen
 * auch sie ihre Seiten direkt in der Navigation. Das Neue-Seite-Feld
 * bleibt allein am aktiven Abschnitt, egal wie viele Abschnitte offen
 * sind. Der Aufklappzustand bleibt je Browser gespeichert (app.js).
 * #187: Der Buchpaket-Import sitzt als fester aufklappbarer Eintrag unten
 * in der Navigation (nur Verwalter), auf jeder Ansicht desselben Orts.
 * Erwartet: $book, $nav, $activeSectionId, $activePageId, $canWrite,
 * $canManage, $navNewSection, $preview. */ ?>
<?php $navAreas = ['lernbereich' => ['📖', 'Lernbereich'], 'gemeinsam' => ['🤝', 'Zusammenarbeit'], 'eigen' => ['🔒', 'Mein Bereich']]; ?>
<?php /* #70: Im Lesemodus (?lesen=1) behalten alle Seiten-Links das Suffix,
 * Abschnitts-Links fuehren in die Abschnittsansicht. */ ?>
<?php $navLesen = $preview ? '?lesen=1' : ''; ?>
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
    <?php /* #184/#185/#186: Der Abschnittstitel klappt seine Seiten auf
     * und zu; der aktive Abschnitt traegt die bekannte Aktiv-Klasse und
     * steht standardmaessig offen (aria-current). Ohne Javascript bleibt
     * details/summary bedienbar. */ ?>
    <?php $navIsActive = (int) $navSec['id'] === (int) $activeSectionId; ?>
    <details class="ibook-pagenav-secdetails<?= $navIsActive ? ' ibook-pagenav-secdetails-active' : '' ?>" data-secid="<?= (int) $navSec['id'] ?>"<?= $navIsActive ? ' open' : '' ?>>
    <summary class="ibook-pagenav-section<?= $navIsActive ? ' ibook-pagenav-section-active' : '' ?>" title="Seiten dieses Abschnitts ein- und ausklappen"<?= $navIsActive ? ' aria-current="true"' : '' ?>><?= e($navSec['title']) ?></summary>
    <?php /* #101/#184/#186: Seiten des Abschnitts; Drag&Drop-Sortieren
     * bleibt dem aktiven Abschnitt mit Schreibrecht vorbehalten,
     * app.js schickt die neue Reihenfolge an /seiten-reihenfolge. */ ?>
    <div class="ibook-pagenav-pages<?= $navIsActive ? '' : ' ibook-pagenav-pages-sub' ?>">
        <?php foreach ($navEntry['pages'] as $navPage): ?>
        <?php $navDrag = ($navIsActive && $canWrite) ? ' draggable="true" data-page-id="' . (int) $navPage['id'] . '"' : ''; ?>
        <a class="ibook-pagenav-page<?= (int) $navPage['id'] === (int) $activePageId ? ' active' : '' ?><?= ($navIsActive && $canWrite) ? ' ibook-pagenav-drag' : '' ?>" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $navPage['id'] . $navLesen) ?>"<?= $navDrag ?> title="Seite öffnen"><?= e((string) $navPage['title']) ?></a>
        <?php endforeach; ?>
        <?php /* #186: Leere nicht-aktive Abschnitte zeigen im aufgeklappten
         * Zustand einen Hinweis; mit Schreibrecht geht der Link in die
         * Abschnittsansicht, dort liegt das Formular fuer die erste Seite.
         * Ohne Schreibrecht gibt es keinen Link (keine "Neue Seite"-
         * Texte fuer Nur-Lese-Zugaenge, #185). */ ?>
        <?php if (!$navIsActive && $navEntry['pages'] === []): ?>
        <span class="ibook-pagenav-empty">Noch keine Seiten</span>
        <?php if ($navEntry['canWrite']): ?>
        <a class="ibook-pagenav-secopen" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $navSec['id']) ?>" title="Abschnittsansicht öffnen">Abschnitt öffnen →</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php /* #72/#184/#186: Genau ein "Neue Seite"-Formular, allein am
         * aktiven Abschnitt, egal wie viele Abschnitte aufgeklappt sind.
         * Nach dem Anlegen oeffnet sich die neue Seite. */ ?>
        <?php if ($navIsActive && $canWrite): ?>
        <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="ibook-navnew">
            <?= csrf_field() ?>
            <input type="hidden" name="section_id" value="<?= (int) $navSec['id'] ?>">
            <input type="text" name="title" maxlength="160" placeholder="Neue Seite" aria-label="Neue Seite in diesem Abschnitt anlegen">
            <button class="btn btn-ghost" type="submit" title="Neue Seite in diesem Abschnitt anlegen (öffnet sich danach direkt)">➕</button>
        </form>
        <?php endif; ?>
    </div>
    </details>
    <?php endforeach; ?>
    <?php /* #72/#184/#185: Neuer Abschnitt unten aufklappbar statt als
     * Dauerformular; Lehrkraefte waehlen den Bereich. In der Abschnitts-
     * ansicht steht das Formular auch ohne Schreibrecht am aktiven
     * Abschnitt bereit, wenn das Buch Sectionen erlaubt ($navNewSection). */ ?>
    <?php if ($canWrite || $navNewSection): ?>
    <details class="ibook-navnewsec-details">
        <summary class="btn btn-ghost" title="Abschnitt anlegen">＋ Neuer Abschnitt</summary>
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
    </details>
    <?php endif; ?>
    <?php /* #187: Buchpaket-Import als fester aufklappbarer Eintrag unten
     * in der Navigation, auf jeder Buch- und Seitenansicht am selben Ort
     * (nur Verwalter des Buchs, $canManage). Die Abschnitte mit Seiten
     * haengen sich ans Ende des Buchs. */ ?>
    <?php if ($canManage): ?>
    <details class="ibook-navimport-details">
        <summary class="btn btn-ghost" title="Abschnitte und Seiten aus einem JSON-Paket anlegen">📥 Buchpaket importieren</summary>
        <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/import') ?>" class="form ibook-navimport">
            <?= csrf_field() ?>
            <label class="field"><span>JSON-Paket (Abschnitte + Seiten)</span>
                <textarea name="paket" rows="4" placeholder='{"sections":[{"title":"SQL","area":"lernbereich","pages":[{"title":"SELECT","blocks":[]}]}]}'></textarea>
            </label>
            <p class="muted">Fügt Abschnitte mit Seiten ans Ende des Buchs an. Texte, DB-Abfrage-Fenster, Code, Diagramme und Einbettungen (YouTube, LearningApps, H5P) kommen mit; Bilder, Audio und Video müssen danach neu hochgeladen werden.</p>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Abschnitte und Seiten aus dem JSON anlegen">📥 Importieren</button></div>
        </form>
    </details>
    <?php endif; ?>
    </details>
</aside>
