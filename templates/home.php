<section class="hero">
    <div class="hero-emoji">🔨</div>
    <h1>Willkommen<?= $user !== null ? ', ' . e($user->firstName) : '' ?>!</h1>
    <p>InfoCraft ist die digitale Informatik-Werkstatt: SQL, HTML, Java/Python, Quiz, Wiki und Aufgaben, alles auf dem bayerischen LehrplanPLUS.</p>
</section>

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

<div class="two-col">
    <section class="panel">
        <h3>🚀 Roadmap</h3>
        <ul class="phase-list">
            <li><span class="status status-fertig">fertig</span> <strong>Phase 1:</strong> Grundgerüst, Anmeldung (SSO/CSV/Demo), Rollen</li>
            <li><span class="status status-fertig">fertig</span> <strong>Phase 2:</strong> SQL-Werkstatt (Shop-Modell, Instagram, Adventure, Rechte)</li>
            <li><span class="status status-geplant">geplant</span> <strong>Phase 3:</strong> HTML-WYSIWYG-Editor</li>
            <li><span class="status status-in-arbeit">in Arbeit</span> <strong>Phase 4:</strong> Code-Werkstatt (Projekte/Ordner fertig, IDE folgt)</li>
            <li><span class="status status-geplant">geplant</span> <strong>Phase 5:</strong> Quiz-Arena &amp; Prüfungsmodus</li>
            <li><span class="status status-in-arbeit">in Arbeit</span> <strong>Phase 6:</strong> Wiki &amp; Präsentationen (fertig), Lernvideos folgen</li>
            <li><span class="status status-in-arbeit">in Arbeit</span> <strong>Phase 7:</strong> Aufgabenpool &amp; Austeilen (Basis fertig)</li>
        </ul>
        <p class="muted">Details: docs/ROADMAP.md im Repository.</p>
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

