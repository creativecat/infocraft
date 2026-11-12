<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Startseite
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="hero">
    <div class="hero-emoji">🔨</div>
    <h1>Willkommen<?= $user !== null ? ', ' . e($user->firstName) : '' ?>!</h1>
    <p>InfoCraft ist die digitale Informatik-Werkstatt: SQL, HTML, Java/Python, Quiz, Wiki und Aufgaben, alles auf dem bayerischen LehrplanPLUS.</p>
</section>

<?php /* #183: Ankündigung der Administration, prominent über den Modulen. */ ?>
<?php if (($announcement ?? '') !== ''): ?>
<div class="notice ic-annc" role="status">
    <strong>📣 Ankündigung</strong>
    <p><?= nl2br(e($announcement)) ?></p>
</div>
<?php endif; ?>

<div class="cards">
    <?php foreach ($modules as $module): ?>
    <?php $cardTitle = ($user !== null && $user->role === 'student' && $module['slug'] === 'tasks') ? 'Meine Aufgaben' : $module['title']; ?>
    <a class="card" href="<?= url($module['route'] ?? 'modul/' . $module['slug']) ?>">
        <div class="card-icon"><?= $module['icon'] ?></div>
        <h2><?= e($cardTitle) ?></h2>
        <p class="card-desc"><?= e($module['description']) ?></p>
        <div class="card-meta">
            <span class="status status-<?= e($module['status']) ?>"><?= e($module['status']) ?></span>
            <span class="muted"><?= e($module['phase']) ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php /* #182: Startpaket- und Statuskästen sind reine Admin-Angelegenheiten,
     * Lehrkräfte und Schülerinnen und Schüler sehen sie nicht. */ ?>
<?php if ($user !== null && $user->role === 'admin'): ?>
<div class="two-col">
    <section class="panel">
        <h3>🎓 Jahrgangsstufe 10: komplett vorbereitet</h3>
        <p>Das Startpaket für Informatik 10 (NTG) ist fertig: Datenmodellierung, relationale Datenbanken, objektorientierte Programmierung und Projekt - alles nach LehrplanPLUS.</p>
        <ul class="phase-list">
            <li>🎬 <strong>InfoBook</strong> mit Präsentationsmodus (Animationen, Zeitsteuerung): <a href="<?= url('infobuecher') ?>">InfoBooks öffnen</a></li>
            <li>🏆 <strong>Quiz-Arena</strong> (nur für dich): pro Kapitel ein fertiges Quiz, austeilbereit: <a href="<?= url('quiz') ?>">Quiz-Arena öffnen</a></li>
            <li>📋 <strong>Aufgaben</strong> fürs ganze Schuljahr: <a href="<?= url('aufgaben?level=10') ?>">Katalog Jahrgangsstufe 10</a></li>
            <li>📝 <strong>Kurzarbeiten</strong> für den Prüfungsmodus: <a href="<?= url('pruefungen') ?>">Prüfungen &amp; Arbeiten</a></li>
        </ul>
        <p class="muted">Details und Stundenplan-Empfehlung: docs/JGST10.md im Repository.</p>
    </section>
    <section class="panel">
        <h3>📊 Systemstatus</h3>
        <table class="kv">
            <tr><th>Datenbanktreiber</th><td><?= e($system['driver']) ?></td></tr>
            <tr><th>Demo-Modus</th><td><?= $system['demo'] ? '<span class="status status-in-arbeit">aktiv</span>' : '<span class="status status-fertig">aus</span>' ?></td></tr>
            <tr><th>Microsoft-SSO</th><td><?= $system['sso'] ? '<span class="status status-fertig">konfiguriert</span>' : '<span class="status status-geplant">nicht konfiguriert</span>' ?></td></tr>
            <tr><th>PHP</th><td><?= e(PHP_VERSION) ?></td></tr>
        </table>
        <h3>🎓 LehrplanPLUS</h3>
        <p>Jedes Modul dokumentiert seinen Bezug zum bayerischen LehrplanPLUS Informatik. Sichtbar ist ein Arbeitsbereich für Schülerinnen und Schüler erst ab der Jahrgangsstufe, in der er dran ist.</p>
    </section>
</div>
<?php endif; ?>
