<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Modulseite
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="hero">
    <div class="hero-emoji"><?= $module['icon'] ?></div>
    <h1><?= e($module['title']) ?></h1>
    <p><?= e($module['description']) ?></p>
    <div class="card-meta" style="justify-content: center; margin-top: 10px;">
        <span class="status status-<?= e($module['status']) ?>"><?= e($module['status']) ?></span>
        <span class="muted"><?= e($module['phase']) ?></span>
    </div>
</section>

<div class="two-col">
    <section class="panel">
        <h3>Geplante Funktionen</h3>
        <ul class="feature-list">
            <?php foreach ($module['features'] as $feature): ?>
            <li><?= e($feature) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="notice">
            <strong>Baustelle 🚧</strong>
            <p>Dieser Arbeitsbereich entsteht in <?= e($module['phase']) ?>. Diese Seite ist der Platzhalter mit dem finalen Funktionsumfang, damit Struktur und Navigation jetzt schon nutzbar sind.</p>
        </div>
    </section>
    <section class="panel">
        <h3>🎓 Bezug zum LehrplanPLUS</h3>
        <p><?= e($module['lehrplan']) ?></p>
        <p class="muted">Die konkrete Zuordnung zu Kompetenzen und Jahrgangsstufen wird pro Funktion in docs/lehrplan.md gepflegt.</p>
    </section>
</div>
