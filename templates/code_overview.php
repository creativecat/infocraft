<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Code-Werkstatt: Projektübersicht
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.19.0 */ ?>
<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<?php /* Ordner mit Unterordnern: Diese zeigen keinen 'Noch keine Projekte'-Platzhalter,
     * auch wenn sie selbst keine direkten Projekte enthalten (#45). */ $folderHasChildren = [];
foreach ($folderRows as $row) { if (($row['folder']->parentId ?? 0) > 0) { $folderHasChildren[(int) ($row['folder']->parentId ?? 0)] = true; } } ?>
<?php /* Projekt-Karte (#169/#171): Icon und Bezeichnung kommen aus den zentralen
     * Helfern in Support.php, damit jede Projektart ueberall ihr eigenes Icon zeigt. */ ?>
<?php
function ic_proj_row(object $project): string
{
    $id = (int) $project->id;
    $open = e(url('werkstatt/code/' . $id));
    $icon = e(ic_proj_icon((string) $project->type, (string) $project->language));
    $kind = e(ic_proj_kind((string) $project->type, (string) $project->language));
    $title = e($project->title);
    return '<div class="proj-row project-card" draggable="true" data-id="' . $id . '" title="Zum Verschieben in einen Ordner ziehen">'
        . '<span class="proj-icon">' . $icon . '</span>'
        . '<a class="proj-title" href="' . $open . '">' . $title . '</a>'
        . '<span class="pill proj-kind">' . $kind . '</span>'
        . '<a class="btn btn-primary proj-open" href="' . $open . '">Öffnen</a>'
        . '<form method="post" action="' . e(url('werkstatt/code/loeschen')) . '" class="form-inline" onsubmit="return confirm(\'Projekt wirklich löschen?\');">'
        . csrf_field()
        . '<input type="hidden" name="project_id" value="' . $id . '">'
        . '<button class="btn btn-ghost" type="submit" title="Löschen">🗑️</button>'
        . '</form>'
        . '</div>';
}
?>
<section class="hero">
    <div class="hero-emoji">💻</div>
    <h1>Code-Werkstatt</h1>
    <p>Deine Projekte, organisiert in einem Ordnerbaum mit Unterordnern. Zieh Projekte einfach per Drag &amp; Drop in einen Ordner.</p>
</section>

<div class="code-ov" data-move-url="<?= url('werkstatt/code/move') ?>" data-folder-move-url="<?= url('werkstatt/code/ordner/verschieben') ?>">
    <?php /* #175: Projekt verteilen sitzt wie im InfoBook als aufklappbarer
     * Button oben rechts, nicht mehr als großer Kasten in der Übersicht. */ ?>
    <?php if ($isTeacher && $projects !== []): ?>
    <div class="ov-topbar">
        <details class="ibook-distribute-details ov-distribute">
            <summary class="btn btn-ghost" title="Projekt an eine Klasse oder einzelne Person austeilen">📤 Projekt verteilen</summary>
            <form method="post" action="<?= url('werkstatt/code/verteilen') ?>" class="form ibook-distribute-panel ov-distribute-panel">
                <?= csrf_field() ?>
                <div class="three-col">
                    <label class="field"><span>Projekt</span>
                        <select name="project_id">
                            <?php foreach ($projects as $project): ?>
                            <option value="<?= (int) $project->id ?>"><?= e($project->title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field"><span>Ganze Klasse</span>
                        <select name="class_id">
                            <option value="0">- keine -</option>
                            <?php foreach ($classes as $class): ?>
                            <option value="<?= (int) $class->id ?>">Klasse <?= e($class->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field"><span>Einzelne Person</span>
                        <select name="user_id">
                            <option value="0">- keine -</option>
                            <?php foreach ($studentsByClass as $classId => $students): ?>
                            <optgroup label="Klasse <?= e($classNames[$classId] ?? '') ?>">
                                <?php foreach ($students as $student): ?>
                                <option value="<?= (int) $student->id ?>"><?= e($student->fullName()) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="btn-row"><button class="btn btn-primary" type="submit" title="Kopie austeilen">📤 Verteilen</button></div>
            </form>
        </details>
    </div>
    <?php endif; ?>
    <div class="ov-boxes">
        <section class="panel">
            <h3>➕ Neues Projekt</h3>
            <form method="post" action="<?= url('werkstatt/code/projekt') ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Projektart</span>
                    <select name="kind" id="new-kind">
                        <option value="java"<?= (($defaultLanguage ?? 'java') === 'java') ? ' selected' : '' ?>>☕ Java</option>
                        <option value="python"<?= (($defaultLanguage ?? 'java') === 'python') ? ' selected' : '' ?>>🐍 Python</option>
                        <option value="asm"<?= (($defaultLanguage ?? 'java') === 'asm') ? ' selected' : '' ?>>⚙️ Assembler</option>
                        <option value="blocks"<?= (($defaultLanguage ?? 'java') === 'blocks') ? ' selected' : '' ?>>🧩 Blöcke</option>
                        <option value="automaton">🤖 Automat (endliche Automaten)</option>
                        <option value="pda">🥞 Kellerautomat</option>
                        <option value="turing">🧾 Turingmaschine</option>
                        <option value="logic">🔌 Schaltwerk</option>
                        <option value="html">🌐 HTML-Seite</option>
                    </select>
                </label>
                <label class="field"><span>Projekttitel</span><input type="text" name="title" required placeholder="z. B. Mein erstes Programm"></label>
                <label class="field"><span>Ordner</span>
                    <select name="folder_id">
                        <?php foreach ($folderNames as $folderId => $folderName): ?>
                        <option value="<?= (int) $folderId ?>"><?= e($folderName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field" id="new-starter"><span>Startpaket (nur Python)</span>
                    <select name="starter">
                        <option value="">- leeres Blatt -</option>
                        <option value="zaehlen">🔢 Zählen mit Python (Schleifen)</option>
                        <option value="zeichnen">🎨 Zeichnen mit Zeichen (Turtle-Bild)</option>
                    </select>
                </label>
                <div class="btn-row"><button class="btn btn-primary" type="submit" title="Projekt anlegen">➕</button></div>
            </form>
        </section>
        <?php if ($isTeacher && $studentsByClass !== []): ?>
        <section class="panel">
            <h3>🎒 Schülerinnen und Schüler</h3>
            <?php foreach ($studentsByClass as $sClassId => $sStudents): ?>
                <p class="muted">Klasse <?= e($classNames[$sClassId] ?? '') ?>:</p>
                <p>
                    <?php foreach ($sStudents as $sStudent): ?>
                    <a class="pill" href="<?= url('werkstatt/code/schueler/' . (int) $sStudent->id) ?>">📂 <?= e($sStudent->fullName()) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endforeach; ?>
            <p class="muted">Jede Person zeigt ihre eigenen Projekte. Du kannst sie öffnen, den Zwischenstand ansehen und beim Arbeiten helfen.</p>
        </section>
        <?php endif; ?>
    </div>
    <section class="panel">
        <?php /* #176: Ordner anlegen (inkl. Hauptebene) gehört direkt hierher
             * in die Ordnerliste, nicht mehr als eigener Kasten oben. */ ?>
        <div class="panel-head">
            <h3>🗂️ Ordner &amp; Projekte</h3>
            <details class="folder-new">
                <summary class="btn btn-ghost" title="Neuen Ordner anlegen">📁 Neuer Ordner</summary>
                <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form form-inline folder-new-form">
                    <?= csrf_field() ?>
                    <label class="field"><span>Name</span><input type="text" name="name" required placeholder="z. B. Hausaufgaben"></label>
                    <label class="field"><span>Übergeordneter Ordner</span>
                        <select name="parent_id">
                            <option value="0">- Hauptebene -</option>
                            <?php foreach ($folderNames as $folderId => $folderName): ?>
                            <?php if ($folderId === 0) { continue; } ?>
                            <option value="<?= (int) $folderId ?>"><?= e($folderName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="btn-row"><button class="btn btn-primary" type="submit" title="Ordner anlegen">📁 Anlegen</button></div>
                </form>
            </details>
        </div>
        <p class="muted">Ordner bilden einen Baum mit Unterordnern. Projekte und Ordner per Drag &amp; Drop auf einen anderen Ordner ziehen, um sie zu verschieben.</p>
        <ul class="folder-list">
            <li class="folder-item" data-folder="0">
                <strong>📁 Ohne Ordner</strong> <span class="pill"><?= (int) ($counts[0] ?? 0) ?></span>
                <div class="folder-children">
                    <?php if (($projectsByFolder[0] ?? []) === []): ?>
                    <p class="muted proj-empty">Noch keine Projekte.</p>
                    <?php endif; ?>
                    <?php foreach ($projectsByFolder[0] ?? [] as $project): ?>
                    <?= ic_proj_row($project) ?>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php foreach ($folderRows as $row): ?>
            <?php $folder = $row['folder']; ?>
            <li class="folder-item" draggable="true" data-folder="<?= (int) $folder->id ?>" data-folder-drag="<?= (int) $folder->id ?>" title="Ordner per Drag &amp; Drop in einen anderen Ordner ziehen" style="padding-left: <?= (int) $row['depth'] * 18 ?>px">
                <strong>📁 <?= e($folder->name) ?></strong> <span class="pill"><?= (int) ($counts[$folder->id] ?? 0) ?></span>
                <details class="folder-sub">
                    <summary class="folder-add" title="Unterordner anlegen">＋ Unterordner</summary>
                    <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="parent_id" value="<?= (int) $folder->id ?>">
                        <input type="text" name="name" required placeholder="Unterordner">
                        <button class="btn btn-ghost" type="submit" title="Unterordner anlegen">📁</button>
                    </form>
                </details>
                <div class="folder-children">
                    <?php if (($projectsByFolder[$folder->id] ?? []) === [] && ($folder->parentId ?? 0) === 0 && empty($folderHasChildren[$folder->id])): ?>
                    <p class="muted proj-empty">Noch keine Projekte.</p>
                    <?php endif; ?>
                    <?php foreach ($projectsByFolder[$folder->id] ?? [] as $project): ?>
                    <?= ic_proj_row($project) ?>
                    <?php endforeach; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>


<script>
/* Formular „Neues anlegen“ (#171): Ein Feld 'Projektart' deckt Sprache und
 * HTML-Seite ab. Startpakete gibt es nur fuer Python, alle anderen Arten
 * brauchen keine zweite Nachfrage mehr. Laeuft ohne Abhaengigkeiten direkt
 * auf der Uebersicht. */
(function () {
  'use strict';
  var kindSel = document.getElementById('new-kind');
  var starter = document.getElementById('new-starter');
  if (!kindSel || !starter) { return; }
  function syncNewForm() {
    /* Startpaket nur bei Python anbieten */
    starter.style.display = (kindSel.value === 'python') ? '' : 'none';
    if (kindSel.value !== 'python') {
      starter.querySelectorAll('option').forEach(function (o) { if (o.value !== '') { o.selected = false; } });
      starter.querySelector('option[value=""]').selected = true;
    }
  }
  kindSel.addEventListener('change', syncNewForm);
  syncNewForm();
})();
</script>
