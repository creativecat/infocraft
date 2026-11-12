<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: InfoBook: Buchansicht (Abschnittsansicht)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.21.0 */ ?>
<?php /* #185: Die Buchansicht ist zur Abschnittsansicht geworden und
 * sieht aus wie die Seitenansicht: gleiche Kopfzeile (Icon-Knopf,
 * Breadcrumb, Knopfreihe rechts), gleiche Navigation (Partial
 * ibook_nav.php), im Hauptbereich die Seiten des Abschnitts als Karten.
 * Der Buch-Start selbst leitet auf die zuletzt besuchte Seite weiter
 * (#185), diese Ansicht bleibt ueber die Breadcrumb und den
 * abschnitt-Parameter erreichbar. */ ?>
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
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/praesentation?abschnitt=' . (int) $activeSection['id']) ?>" title="Alle Seiten dieses Abschnitts als Präsentation anzeigen: Folienwechsel mit Animation und Zeitsteuerung">🎬 Präsentation</a>
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
    <?php if ($pages === []): ?>
    <div class="notice">
        <strong>Noch keine Seiten</strong>
        <p>Hier stehen die Seiten dieses Abschnitts als Karten.<?= $canWriteSection ? ' Lege die erste Seite an: Text, Bilder, Audio, Videos, Einbettungen und Zeichnungen gehören auf eine InfoBook-Seite.' : ' Die Lehrkraft legt die Seiten an.' ?></p>
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
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="form ibook-newpage">
        <?= csrf_field() ?>
        <input type="hidden" name="section_id" value="<?= (int) $activeSection['id'] ?>">
        <div class="form-row form">
            <label class="field"><span>Neue Seite</span><input type="text" name="title" maxlength="160" placeholder="Titel der Seite"></label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Seite anlegen (öffnet sich danach direkt)">➕ Seite anlegen</button></div>
    </form>
    <?php endif; ?>
    <?php /* #185: Verwaltung des Buchs sitzt im Hauptbereich der
     * Abschnittsansicht statt in der Navigation. */ ?>
    <?php if ($canManage): ?>
    <div class="ibook-secview-admin">
        <details class="ibook-import-details">
            <summary class="btn btn-ghost">📥 Buchpaket importieren</summary>
            <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/import') ?>" class="form ibook-import-panel">
                <?= csrf_field() ?>
                <label class="field"><span>JSON-Paket (Abschnitte + Seiten)</span>
                    <textarea name="paket" rows="6" placeholder='{"sections":[{"title":"SQL","area":"lernbereich","pages":[{"title":"SELECT","blocks":[{"type":"canvas","w":1600,"h":1000,"items":[]}]}]}]}'></textarea>
                </label>
                <p class="muted">Fügt Abschnitte mit Seiten ans Ende des Buchs an. Texte, DB-Abfrage-Fenster, Code, Diagramme und Einbettungen (YouTube, LearningApps, H5P) kommen mit; Bilder, Audio und Video müssen danach neu hochgeladen werden.</p>
                <div class="btn-row"><button class="btn btn-primary" type="submit" title="Abschnitte und Seiten aus dem JSON anlegen">📥 Importieren</button></div>
            </form>
        </details>
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
