<?php $classNames = []; foreach ($classes as $class) { $classNames[$class->id] = $class->name; } ?>
<section class="hero">
    <div class="hero-emoji">💻</div>
    <h1>Code-Werkstatt</h1>
    <p>Deine Projekte, organisiert in Ordnern. Zieh Projekte einfach per Drag &amp; Drop in einen Ordner.</p>
</section>

<div class="two-col">
    <section class="panel">
        <h3>➕ Neues anlegen</h3>
        <form method="post" action="<?= url('werkstatt/code/projekt') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Projekttitel</span><input type="text" name="title" required placeholder="z. B. Mein erstes Programm"></label>
            <label class="field"><span>Programmiersprache</span>
                <select name="language">
                    <option value="java"<?= (($defaultLanguage ?? 'java') === 'java') ? ' selected' : '' ?>>☕ Java</option>
                    <option value="python"<?= (($defaultLanguage ?? 'java') === 'python') ? ' selected' : '' ?>>🐍 Python</option>
                    <option value="asm"<?= (($defaultLanguage ?? 'java') === 'asm') ? ' selected' : '' ?>>⚙️ Assembler</option>
                </select>
            </label>
            <label class="field"><span>Art</span>
                <select name="type">
                    <option value="code" selected>☕ Java-Programm</option>
                    <option value="html">🌐 HTML-Seite</option>
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
            <label class="field"><span>Startpaket (nur Python)</span>
                <select name="starter">
                    <option value="">- leeres Blatt -</option>
                    <option value="zaehlen">🔢 Zählen mit Python (Schleifen)</option>
                    <option value="zeichnen">🎨 Zeichnen mit Zeichen (Turtle-Bild)</option>
                </select>
            </label>
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Projekt anlegen">➕</button></div>
        </form>
        <form method="post" action="<?= url('werkstatt/code/ordner') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Neuer Ordner</span><input type="text" name="name" required placeholder="z. B. Hausaufgaben"></label>
            <div class="btn-row"><button class="btn btn-ghost" type="submit" title="Ordner anlegen">📁</button></div>
        </form>
        <?php if ($isTeacher && $projects !== []): ?>
        <h3>📤 Projekt an Klasse oder Person verteilen</h3>
        <form method="post" action="<?= url('werkstatt/code/verteilen') ?>" class="form">
            <?= csrf_field() ?>
            <label class="field"><span>Projekt</span>
                <select name="project_id">
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= (int) $project->id ?>"><?= e($project->title) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="two-col">
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
            <div class="btn-row"><button class="btn btn-primary" type="submit" title="Kopie austeilen">📤</button></div>
        </form>
        <?php endif; ?>
        <?php if ($isTeacher && $studentsByClass !== []): ?>
        <h3>🎒 Projekte meiner Schülerinnen und Schüler</h3>
        <?php foreach ($studentsByClass as $sClassId => $sStudents): ?>
            <p class="muted">Klasse <?= e($classNames[$sClassId] ?? '') ?>:</p>
            <p>
                <?php foreach ($sStudents as $sStudent): ?>
                <a class="pill" href="<?= url('werkstatt/code/schueler/' . (int) $sStudent->id) ?>">📂 <?= e($sStudent->fullName()) ?></a>
                <?php endforeach; ?>
            </p>
        <?php endforeach; ?>
        <p class="muted">Jede Person zeigt ihre eigenen Projekte. Du kannst sie öffnen, den Zwischenstand ansehen und beim Arbeiten helfen.</p>
        <?php endif; ?>
    </section>
    <section class="panel">
        <h3>🗂️ Ordner</h3>
        <p class="muted">Projekte per Drag &amp; Drop hierher ziehen.</p>
        <ul class="folder-list">
            <li class="folder-item" data-folder="0">📁 <strong>Ohne Ordner</strong> <span class="pill"><?= (int) ($counts[0] ?? 0) ?></span></li>
            <?php foreach ($folders as $folder): ?>
            <li class="folder-item" data-folder="<?= (int) $folder->id ?>">📁 <strong><?= e($folder->name) ?></strong> <span class="pill"><?= (int) ($counts[$folder->id] ?? 0) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="proj-groups" data-move-url="<?= url('werkstatt/code/move') ?>">
    <?php
    $groupedProjects = [];
    foreach ($projects as $project) {
        $groupedProjects[(int) ($project->folderId ?? 0)][] = $project;
    }
    $groups = [];
    foreach ($folders as $folder) { $groups[] = ['id' => (int) $folder->id, 'name' => $folder->name]; }
    $groups[] = ['id' => 0, 'name' => 'Ohne Ordner'];
    ?>
    <?php foreach ($groups as $group): ?>
    <div class="proj-group">
        <h3 class="proj-group-title">📁 <?= e($group['name']) ?> <span class="pill"><?= count($groupedProjects[$group['id']] ?? []) ?></span></h3>
        <?php if (($groupedProjects[$group['id']] ?? []) === []): ?>
        <p class="muted proj-empty">Noch keine Projekte in diesem Ordner.</p>
        <?php else: ?>
        <div class="proj-list">
            <?php foreach ($groupedProjects[$group['id']] as $project): ?>
            <div class="proj-row project-card" draggable="true" data-id="<?= (int) $project->id ?>" title="Zum Verschieben in einen Ordner ziehen">
                <span class="proj-icon"><?= $project->type === 'html' ? '🌐' : ($project->language === 'java' ? '☕' : ($project->language === 'asm' ? '⚙️' : '🐍')) ?></span>
                <a class="proj-title" href="<?= url('werkstatt/code/' . (int) $project->id) ?>"><?= e($project->title) ?></a>
                <span class="pill proj-kind"><?= $project->type === 'html' ? 'HTML' : ($project->language === 'java' ? 'Java' : ($project->language === 'asm' ? 'Assembler' : 'Python')) ?></span>
                <a class="btn btn-primary proj-open" href="<?= url('werkstatt/code/' . (int) $project->id) ?>">Öffnen</a>
                <form method="post" action="<?= url('werkstatt/code/loeschen') ?>" class="form-inline" onsubmit="return confirm('Projekt wirklich löschen?');">
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

