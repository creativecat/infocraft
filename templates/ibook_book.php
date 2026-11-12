<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: Buchansicht (Abschnittsansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.22.0 */ ?>
<?php /* #185: Die Buchansicht ist zur Abschnittsansicht geworden und
 * sieht aus wie die Seitenansicht: gleiche Kopfzeile (Icon-Knopf,
 * Breadcrumb, Knopfreihe rechts), gleiche Navigation (Partial
 * ibook_nav.php), im Hauptbereich die Seiten des Abschnitts als Karten.
 * Der Buch-Start selbst leitet auf die zuletzt besuchte Seite weiter
 * (#185), diese Ansicht bleibt ueber die Breadcrumb und den
 * abschnitt-Parameter erreichbar.
 * #187: Ein leerer Abschnitt zeigt das Karten-Grid mit einer Platzhalter-
 * Karte statt einer fremd aussehenden Hinweisbox; der Buchpaket-Import
 * sitzt als fester Eintrag unten in der Navigation (Partial). */ ?>
<?php $preview = false; ?>
<?php $areaNames = ['lernbereich' => 'Lernbereich', 'gemeinsam' => 'Zusammenarbeit', 'eigen' => 'Mein Bereich'];
$areaIcons = ['lernbereich' => '📖', 'gemeinsam' => '🤝', 'eigen' => '🔒']; ?>
<section class="hero ibook-hero-compact">
    <div class="btn-row ibook-topnav">
        <button type="button" class="ibook-navtoggle" id="ibook-pagenav-fold" aria-expanded="true" aria-label="Inhaltsnavigation ein- und ausklappen" title="Inhaltsnavigation einklappen"></button>
        <a class="btn btn-ghost" href="<?= url('infobuecher') ?>" title="Zur Übersicht aller InfoBooks">← Alle InfoBooks</a>
        <div class="ibook-topnav-title">
            <nav class="ibook-crumbs" aria-label="Position in diesem InfoBook">
                <?php if ($activeSection !== null): ?>
                <a class="ibook-crumb ibook-crumb-book" href="<?= url('infobuecher/' . (int) $book->id) ?>" title="Buch-Start: zuletzt besuchte Seite">📓 <?= e($book->title) ?></a>
                <span class="ibook-crumb-sep" aria-hidden="true">›</span>
                <span class="ibook-crumb ibook-crumb-here" aria-current="location">📁 <?= e((string) $activeSection['title']) ?></span>
                <?php else: ?>
                <span class="ibook-crumb ibook-crumb-here" aria-current="location">📓 <?= e($book->title) ?></span>
                <?php endif; ?>
            </nav>
            <p class="muted"><?= $activeSection !== null ? e($areaNames[(string) $activeSection['area']] ?? (string) $activeSection['area']) . ' · ' . count($pages) . ' Seite' . (count($pages) === 1 ? '' : 'n') : 'Noch keine Abschnitte' ?></p>
        </div>
        <div class="ibook-topnav-right">
            <?php if ($activeSection !== null): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/praesentation?abschnitt=' . (int) $activeSection['id']) ?>" title="Alle Seiten dieses Abschnitts als Präsentation anzeigen: Elemente erscheinen einzeln per Klick">🎬 Präsentation</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="ibook-page-layout">
<?php /* #185: Gemeinsame Navigation mit der Seitenansicht. */ ?>
<?php include __DIR__ . '/ibook_nav.php'; ?>
<div class="ibook-pagemain ibook-secview">
    <?php if ($activeSection === null): ?>
    <div class="notice">
        <strong>Noch keine Abschnitte</strong>
        <p>Lege links einen Abschnitt an, dann erscheinen hier die Seiten.</p>
    </div>
    <?php else: ?>
    <div class="ibook-section-head">
        <h2><?= e((string) $activeSection['title']) ?></h2>
        <span class="pill"><?= $areaIcons[(string) $activeSection['area']] ?? '📄' ?> <?= e($areaNames[(string) $activeSection['area']] ?? (string) $activeSection['area']) ?></span>
    </div>
    <?php /* #187: Der leere Abschnitt zeigt dasselbe Karten-Grid wie ein
     * gefuellter: eine Platzhalter-Karte mit Hinweis, statt einer fremd
     * aussehenden Hinweisbox. Mit Schreibrecht fuehrt die Karte zur
     * Seite-anlegen-Leiste, ohne bleibt es ein Hinweis. */ ?>
    <?php if ($pages === []): ?>
    <div class="ibook-secview-cards">
        <?php if ($canWriteSection): ?>
        <a class="ibook-page-card ibook-page-card-empty" href="#ibook-newpage" title="Zur Seite-anlegen-Leiste springen">
            <span class="ibook-page-title">➕ Erste Seite anlegen</span>
            <span class="ibook-page-card-hint" aria-hidden="true">Formular unten ↓</span>
        </a>
        <?php else: ?>
        <div class="ibook-page-card ibook-page-card-empty">
            <span class="ibook-page-title">📄 Noch keine Seiten</span>
            <span class="ibook-page-card-hint">Die Lehrkraft legt die Seiten an.</span>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="ibook-secview-cards">
        <?php foreach ($pages as $page): ?>
        <a class="ibook-page-card" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>">
            <span class="ibook-page-title">📄 <?= e((string) $page['title']) ?></span>
            <span class="ibook-page-card-hint" aria-hidden="true">Seite öffnen →</span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($canWriteSection): ?>
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="form ibook-newpage" id="ibook-newpage">
        <?= csrf_field() ?>
        <input type="hidden" name="section_id" value="<?= (int) $activeSection['id'] ?>">
        <div class="form-row form">
            <label class="field"><span>Neue Seite</span><input type="text" name="title" maxlength="160" placeholder="Titel der Seite"></label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Seite anlegen (öffnet sich danach direkt)">➕ Seite anlegen</button></div>
    </form>
    <?php endif; ?>
    <?php /* #185/#187: Abschnitts-Verwaltung im Hauptbereich; der
     * Buchpaket-Import sitzt als fester Eintrag unten in der Navigation
     * (Partial ibook_nav.php), auf jeder Ansicht am selben Ort. */ ?>
    <?php if ($canManage): ?>
    <div class="ibook-secview-admin">
        <?php if ((string) $activeSection['area'] !== 'eigen'): ?>
        <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/abschnitt/' . (int) $activeSection['id'] . '/loeschen') ?>" class="inline-form" data-confirm="Abschnitt mit allen Seiten löschen?">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit" title="Diesen Abschnitt löschen">🗑 Abschnitt löschen</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div>
