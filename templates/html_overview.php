<?php
/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: HTML-Werkstatt: Übersicht
 *
 * @author  Matthias Glienke, letima development
 * @license CC BY-NC-SA 4.0
 * @version 0.15.0
 */
$groupedProjects = [];
foreach ($projects as $project) {
    $groupedProjects[(int) ($project->folderId ?? 0)][] = $project;
}
?>
<section class="hero">
    <div class="hero-emoji">🎨</div>
    <h1>HTML-Werkstatt</h1>
    <p>Webseiten gestalten mit drei Ansichten: Gestalten, Quelltext und Vorschau – deine Seiten, organisiert in Ordnern.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Neue Seite</h3>
        <form method="post" action="<?= url('werkstatt/html/projekt') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Titel</span><input type="text" name="title" required placeholder="z. B. Meine Hobbyseite"></label>
            <label class="field"><span>Vorlage</span>
                <select name="template">
                    <option value="grund">Grundgerüst mit Text und Liste</option>
                    <option value="galerie">Bildergalerie</option>
                    <option value="formular">Kontaktformular</option>
                    <option value="blank">Leere Seite</option>
                </select>
            </label>
            <label class="field"><span>Ordner</span>
                <select name="folder_id">
                    <option value="0">Ohne Ordner</option>
                    <?php foreach ($folders as $folder): ?>
                    <option value="<?= (int) $folder->id ?>"><?= e($folder->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Seite anlegen">📄</button></div>
        </form>
        <h3>🗂️ Neuer Ordner</h3>
        <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Ordnername</span><input type="text" name="name" required placeholder="z. B. Hausaufgaben"></label>
            <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Ordner anlegen">📁</button></div>
        </form>
    </section>
    <section class="panel">
        <h3>💡 So arbeitest du hier</h3>
        <ul class="feature-list">
            <li>Gestalten, Quelltext und Vorschau: drei Ansichten, eine Seite – der Quelltext bleibt die Quelle der Wahrheit</li>
            <li>Deine Seiten liegen in Ordnern: Zieh sie einfach per Drag &amp; Drop in einen Ordner</li>
            <li>Mit Strg + S speicherst du direkt</li>
            <li>Mit ⬇ HTML herunterladen speicherst du die Datei auf deinen Computer, mit ⬆ HTML öffnen lädst du sie wieder hoch</li>
            <?php if ($isTeacher): ?><li>Seiten teilst du wie Code-Projekte über die Code-Werkstatt an Klassen aus</li><?php endif; ?>
        </ul>
        <h3>🗂️ Ordner</h3>
        <p class="muted">Seiten per Drag &amp; Drop hierher ziehen.</p>
        <ul class="folder-list">
            <li class="folder-item" data-folder="0">📁 <strong>Ohne Ordner</strong> <span class="pill"><?= count($groupedProjects[0] ?? []) ?></span></li>
            <?php foreach ($folders as $folder): ?>
            <li class="folder-item" data-folder="<?= (int) $folder->id ?>">📁 <strong><?= e($folder->name) ?></strong> <span class="pill"><?= count($groupedProjects[(int) $folder->id] ?? []) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="proj-groups" data-move-url="<?= url('werkstatt/code/move') ?>">
    <?php
    $groups = [];
    foreach ($folders as $folder) { $groups[] = ['id' => (int) $folder->id, 'name' => $folder->name]; }
    $groups[] = ['id' => 0, 'name' => 'Ohne Ordner'];
    ?>
    <?php foreach ($groups as $group): ?>
    <div class="proj-group">
        <h3 class="proj-group-title">📁 <?= e($group['name']) ?> <span class="pill"><?= count($groupedProjects[$group['id']] ?? []) ?></span></h3>
        <?php if (($groupedProjects[$group['id']] ?? []) === []): ?>
        <p class="muted proj-empty">Noch keine Seiten in diesem Ordner.</p>
        <?php else: ?>
        <div class="proj-list">
            <?php foreach ($groupedProjects[$group['id']] as $project): ?>
            <div class="proj-row project-card" draggable="true" data-id="<?= (int) $project->id ?>" title="Zum Verschieben in einen Ordner ziehen">
                <span class="proj-icon">🌐</span>
                <a class="proj-title" href="<?= url('werkstatt/html/' . (int) $project->id) ?>"><?= e($project->title) ?></a>
                <span class="pill proj-kind">HTML</span>
                <a class="btn btn-primary proj-open" href="<?= url('werkstatt/html/' . (int) $project->id) ?>">Öffnen</a>
                <form method="post" action="<?= url('werkstatt/code/loeschen') ?>" class="form-inline" onsubmit="return confirm('Seite wirklich löschen?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= (int) $project->id ?>">
                    <button class="btn btn-ghost" type="submit" title="Löschen">🗑️</button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
