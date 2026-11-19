<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Modulseite
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.42.0 */ ?>
<section class="hero">
    <div class="hero-emoji"><?= $module['icon'] ?></div>
    <h1><?= e($module['title']) ?></h1>
    <p><?= e($module['description']) ?></p>
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
            <strong>Baustelle <span class="icn ic-construction" aria-hidden="true"></span></strong>
            <p>Dieser Arbeitsbereich ist noch im Aufbau. Diese Seite ist der Platzhalter mit dem finalen Funktionsumfang, damit Struktur und Navigation jetzt schon nutzbar sind.</p>
        </div>
    </section>
    <section class="panel">
        <h3><span class="icn ic-graduation" aria-hidden="true"></span> Bezug zum LehrplanPLUS</h3>
        <p><?= e($module['lehrplan']) ?></p>
        <p class="muted">Die konkrete Zuordnung zu Kompetenzen und Jahrgangsstufen wird pro Funktion in docs/lehrplan.md gepflegt.</p>
    </section>
</div>
