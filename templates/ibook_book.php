<?php /* InfoBook: Buch mit Abschnitten (Lernbereich / Zusammenarbeit / Mein Bereich) und Seiten (#62). */
$areaNames = ['lernbereich' => 'Lernbereich', 'gemeinsam' => 'Zusammenarbeit', 'eigen' => 'Mein Bereich'];
$areaIcons = ['lernbereich' => '📖', 'gemeinsam' => '🤝', 'eigen' => '🔒'];
?>
<section class="hero">
    <div class="hero-emoji">📓</div>
    <h1><?= e($book->title) ?></h1>
    <p>Seiten mit Text, Bildern, Audio, Video, Einbettungen und Freihand-Notizen. <?= $book->classId !== null ? 'Der Lernbereich ist für alle zum Lesen da, in der Zusammenarbeit schreibt die ganze Klasse mit, und dein privater Bereich bleibt nur für dich und die Lehrkraft.' : 'Verteile Seiten daraus an deine Klassen.' ?></p>
</section>

<div class="btn-row ibook-topnav">
    <a class="btn btn-ghost" href="<?= url('infobuecher') ?>">← Alle InfoBooks</a>
    <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/praesentation' . ($activeSection !== null ? '?abschnitt=' . (int) $activeSection['id'] : '')) ?>" title="Alle Seiten als Präsentation anzeigen (ohne Kopie)">🎬 Als Präsentation anzeigen</a>
    <?php if (Auth::isTeacher()): ?>
    <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/als-praesentation') ?>" class="inline-form">
        <?= csrf_field() ?>
        <?php if ($activeSection !== null): ?><input type="hidden" name="section_id" value="<?= (int) $activeSection['id'] ?>"><?php endif; ?>
        <label class="ibook-inline-label"><span class="muted">Ab Jgst.</span>
            <select name="level">
                <?php foreach (valid_levels() as $i): ?>
                <option value="<?= $i ?>"<?= $i === 9 ? ' selected' : '' ?>><?= $i ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-primary" type="submit" title="Erstellt eine echte Präsentation unter Präsentationen: eine Folie je Seite, dort weiterbearbeitbar">🎬 → Präsentation erstellen</button>
    </form>
    <?php endif; ?>
</div>

<div class="ibook-layout">
    <aside class="ibook-side">
        <?php
        $currentArea = '';
        foreach ($sections as $section):
            $area = (string) $section['area'];
            if ($area !== $currentArea):
                $currentArea = $area;
                if ($area !== 'lernbereich') {
                    echo '</div>';
                }
                ?>
        <div class="ibook-area-head"><?= $areaIcons[$area] ?? '📄' ?> <?= e($areaNames[$area] ?? $area) ?></div>
        <div class="ibook-area-list">
            <?php endif; ?>
            <a class="ibook-section<?= $activeSection !== null && (int) $section['id'] === (int) $activeSection['id'] ? ' active' : '' ?>" href="<?= url('infobuecher/' . (int) $book->id . '?abschnitt=' . (int) $section['id']) ?>">
                <span class="ibook-section-title"><?= e($section['title']) ?></span>
                <?php if ($area === 'eigen'): ?><span class="pill">privat</span><?php endif; ?>
                <?php if ($area === 'lernbereich'): ?><span class="pill">nur lesen</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($currentArea !== ''): ?>
        </div>
        <?php endif; ?>

        <div class="ibook-side-forms">
            <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/abschnitt') ?>" class="form ibook-newsection">
                <?= csrf_field() ?>
                <?php if ($canManage && $book->classId !== null): ?>
                <label class="field"><span>Neuer Abschnitt</span>
                    <input type="text" name="title" required maxlength="160" placeholder="z. B. Schleifen">
                    <select name="area">
                        <option value="lernbereich">📖 Lernbereich (Schüler lesen)</option>
                        <option value="gemeinsam">🤝 Zusammenarbeit (alle schreiben)</option>
                    </select>
                </label>
                <?php elseif ($canManage): ?>
                <label class="field"><span>Neuer Abschnitt</span>
                    <input type="text" name="title" required maxlength="160" placeholder="z. B. Vererbung">
                    <input type="hidden" name="area" value="lernbereich">
                </label>
                <?php else: ?>
                <label class="field"><span>Neuer Abschnitt (Zusammenarbeit)</span>
                    <input type="text" name="title" required maxlength="160" placeholder="z. B. Unsere Sammlung">
                    <input type="hidden" name="area" value="gemeinsam">
                </label>
                <?php endif; ?>
                <button class="btn btn-ghost" type="submit">➕ Abschnitt</button>
            </form>
            <?php if ($canManage && $activeSection !== null && (string) $activeSection['area'] !== 'eigen'): ?>
            <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/abschnitt/' . (int) $activeSection['id'] . '/loeschen') ?>" class="inline-form" data-confirm="Abschnitt mit allen Seiten löschen?">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit" title="Aktuellen Abschnitt löschen">🗑 Abschnitt löschen</button>
            </form>
            <?php endif; ?>
        </div>
    </aside>

    <main class="ibook-main">
        <?php if ($activeSection === null): ?>
        <div class="notice">
            <strong>Noch keine Abschnitte sichtbar</strong>
            <p>Lege links einen Abschnitt an, dann erscheinen hier die Seiten.</p>
        </div>
        <?php else: ?>
        <div class="ibook-section-head">
            <h2><?= e($activeSection['title']) ?></h2>
            <span class="pill"><?= $areaIcons[(string) $activeSection['area']] ?? '📄' ?> <?= e($areaNames[(string) $activeSection['area']] ?? (string) $activeSection['area']) ?></span>
        </div>
        <?php if ($pages === []): ?>
        <div class="notice">
            <strong>Noch keine Seiten</strong>
            <p>Lege die erste Seite an: Text, Bilder, Audio, Videos, Einbettungen und Zeichnungen gehören auf eine InfoBook-Seite.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($pages as $page): ?>
        <a class="ibook-page-card" href="<?= url('infobuecher/' . (int) $book->id . '/seite/' . (int) $page['id']) ?>">
            <span class="ibook-page-title">📄 <?= e($page['title']) ?></span>
            <span class="muted"><?= count(\Infocraft\Models\InfoBook::pageBlocks($page)) ?> Baustein<?= count(\Infocraft\Models\InfoBook::pageBlocks($page)) === 1 ? '' : 'e' ?></span>
        </a>
        <?php endforeach; ?>
        <?php if ($canWriteSection): ?>
        <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/seite') ?>" class="form ibook-newpage">
            <?= csrf_field() ?>
            <input type="hidden" name="section_id" value="<?= (int) $activeSection['id'] ?>">
            <div class="form-row form">
                <label class="field"><span>Neue Seite</span><input type="text" name="title" maxlength="160" placeholder="Titel der Seite"></label>
            </div>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Seite anlegen">➕ Seite anlegen</button></div>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </main>
</div>
