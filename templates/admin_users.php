<div class="stat-row">
    <div class="stat"><span class="stat-num"><?= (int) ($stats['student'] ?? 0) ?></span><span>Schülerinnen/Schüler</span></div>
    <div class="stat"><span class="stat-num"><?= (int) ($stats['teacher'] ?? 0) ?></span><span>Lehrkräfte</span></div>
    <div class="stat"><span class="stat-num"><?= (int) ($stats['admin'] ?? 0) ?></span><span>Administration</span></div>
    <div class="stat"><span class="stat-num"><?= count($classes) ?></span><span>Klassen</span></div>
</div>

<div class="panel">
    <div class="panel-head">
        <h3>Nutzerliste</h3>
        <input type="search" id="user-filter" class="input" placeholder="Suchen (Name, Klasse, Rolle)">
    </div>
    <table class="table" id="user-table">
        <thead>
            <tr><th>Name</th><th>Rolle</th><th>Klasse</th><th>Anmeldename</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $entry): $u = $entry['user']; ?>
            <tr>
                <td><?= e($u->fullName()) ?></td>
                <td><span class="role role-<?= e($u->role) ?>"><?= e($u->roleLabel()) ?></span></td>
                <td><?= $entry['class'] !== null ? '<span class="pill">' . e($entry['class']) . '</span>' : '<span class="muted">-</span>' ?></td>
                <td class="mono"><?= $u->loginName !== null ? e($u->loginName) : '<span class="muted">SSO</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($demo): ?>
    <p class="muted">Hinweis: Der Demo-Modus ist aktiv. In der Produktion sollte er deaktiviert und stattdessen Microsoft-SSO oder der CSV-Import genutzt werden.</p>
    <?php endif; ?>
</div>
