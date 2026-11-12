<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: gemeinsame Seitennavigation (Partial für Buch- und Seitenansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.21.0 */ ?>
<?php /* #185: Buchansicht (Abschnittsansicht) und Seitenansicht teilen sich
 * diese Navigation, damit die beiden im Kern gleichen Ansichten nicht wieder
 * auseinanderlaufen. Inhalt (#184): Bereiche mit Abschnitten, aufgeklappt
 * allein die Seiten des aktiven Abschnitts, genau ein "Neue Seite"-Feld,
 * "Neuer Abschnitt" aufklappbar. Erwartet: $book, $nav, $activeSectionId,
 * $activePageId, $canWrite, $navNewSection, $preview. */ ?>
<?php $navAreas = ['lernbereich' => ['📖', 'Lernbereich'], 'gemeinsam' => ['🤝', 'Zusammenarbeit'], 'eigen' => ['🔒', 'Mein Bereich']]; ?>
<?php /* #70: Im Lesemodus (?lesen=1) behalten alle Seiten-Links das Suffix,
 * Abschnitts-Links fuehren auf die erste Seite des Abschnitts, wenn der
 * Abschnitt Seiten hat. */ ?>
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
    <?php /* #184/#185: Der aktive Abschnitt ist ein Label (kein Link auf sich
     * selbst), alle anderen stehen als eine Zeile in der Liste und fuehren
     * direkt auf ihre erste Seite (#185), sonst in die Abschnittsansicht. */ ?>
    <?php $navIsActive = (int) $navSec['id'] === (int) $activeSectionId; ?>
    <?php if ($navIsActive): ?>
    <span class="ibook-pagenav-section ibook-pagenav-section-active" aria-current="true"><?= e($navSec['title']) ?></span>
    <?php elseif (!empty($navEntry['pages'])): ?>
    <a class="ibook-pagenav-section" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $navEntry['pages'][0]['id'] . $navLesen) ?>" title="Erste Seite des Abschnitts öffnen"><?= e($navSec['title']) ?></a>
    <?php else: ?>
    <a class="ibook-pagenav-section" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $navSec['id']) ?>" title="Abschnitt öffnen"><?= e($navSec['title']) ?></a>
    <?php endif; ?>
    <?php if ($navIsActive): ?>
    <?php /* #101/#184: Seiten des aktiven Abschnitts; mit Schreibrecht per
     * Drag&Drop sortierbar, app.js schickt die neue Reihenfolge an
     * /seiten-reihenfolge. */ ?>
    <div class="ibook-pagenav-pages">
        <?php foreach ($navEntry['pages'] as $navPage): ?>
        <?php $navDrag = $canWrite ? ' draggable="true" data-page-id="' . (int) $navPage['id'] . '"' : ''; ?>
        <a class="ibook-pagenav-page<?= (int) $navPage['id'] === (int) $activePageId ? ' active' : '' ?><?= $canWrite ? ' ibook-pagenav-drag' : '' ?>" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $navPage['id'] . $navLesen) ?>"<?= $navDrag ?> title="Seite öffnen"><?= e((string) $navPage['title']) ?></a>
        <?php endforeach; ?>
        <?php /* #72/#184: Genau ein "Neue Seite"-Formular, unter den Seiten
         * des aktiven Abschnitts statt einem je Abschnitt. Nach dem Anlegen
         * oeffnet sich die neue Seite. */ ?>
        <?php if ($canWrite): ?>
        <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="ibook-navnew">
            <?= csrf_field() ?>
            <input type="hidden" name="section_id" value="<?= (int) $navSec['id'] ?>">
            <input type="text" name="title" maxlength="160" placeholder="Neue Seite" aria-label="Neue Seite in diesem Abschnitt anlegen">
            <button class="btn btn-ghost" type="submit" title="Neue Seite in diesem Abschnitt anlegen (öffnet sich danach direkt)">➕</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
    </details>
</aside>
