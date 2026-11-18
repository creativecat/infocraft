<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Verwaltung: Konten
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.19.0 */ ?>
<section class="hero">
    <div class="hero-emoji"><span class="icn ic-users2" aria-hidden="true"></span></div>
    <h1>Nutzerverwaltung</h1>
    <p><?= $isAdminView ? 'Alle Konten im Überblick. Für Schülerinnen und Schüler kannst du direkt ein neues Einmalpasswort (OTP) erzeugen.' : 'Die Konten deiner eigenen Klassen im Überblick. Für Schülerinnen und Schüler kannst du direkt ein neues Einmalpasswort (OTP) erzeugen.' ?></p>
</section>

<div class="stat-row">
    <div class="stat"><span class="stat-num"><?= (int) ($stats['student'] ?? 0) ?></span>Schülerinnen und Schüler</div>
    <div class="stat"><span class="stat-num"><?= (int) ($stats['teacher'] ?? 0) ?></span>Lehrkräfte</div>
    <div class="stat"><span class="stat-num"><?= (int) ($stats['admin'] ?? 0) ?></span>Administration</div>
    <?php if (! $isAdminView): ?>
    <div class="stat"><span class="stat-num"><?= count($users) ?></span>Konten deiner Klassen</div>
    <?php endif; ?>
</div>

<div class="notice">
    <strong><span class="icn ic-info" aria-hidden="true"></span> Einmalpasswörter nach Satzbaulogik</strong>
    <p>Passwörter werden nie frei gewählt: Jedes neue Passwort ist ein OTP aus vier Merkwörtern ("Apfel, Berg, Katze und Wolke" ergibt z. B. Abkw42!). Für ganze Klassen legst du OTPs unter "Meine Klassen" auf einmal an.</p>
</div>

<section class="panel">
    <div class="panel-head">
        <h3><span class="icn ic-clipboard" aria-hidden="true"></span> Konten<?= $isAdminView ? '' : ' deiner Klassen' ?></h3>
        <?php if ($isAdminView): ?><a class="btn btn-ghost" href="<?= url('admin/users/csv') ?>" title="Konten als CSV-Datei exportieren"><span class="icn ic-arrow-down" aria-hidden="true"></span> CSV-Export</a><?php endif; ?>
        <input type="search" id="user-filter" class="input" placeholder="Filtern nach Name, Klasse oder Rolle ...">
    </div>
    <table class="table" id="user-table">
        <thead><tr><th>Name</th><th>Anmeldename</th><th>Rolle</th><th>Klasse</th><th>Aktion</th></tr></thead>
        <tbody>
        <?php foreach ($users as $entry): ?>
        <?php $u = $entry['user']; ?>
        <tr>
            <td><?= e($u->fullName()) ?></td>
            <td class="mono"><?= $u->loginName !== null ? e($u->loginName) : '<span class="muted">-</span>' ?></td>
            <td><span class="role role-<?= e($u->role) ?>"><?= e($u->roleLabel()) ?></span></td>
            <td><?= $entry['class'] !== null ? e($entry['class']) : '-' ?></td>
            <td>
                <?php if ($u->role === 'student'): ?>
                <form method="post" action="<?= url('unterricht/passwort') ?>" class="form-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u->id ?>">
                    <button class="btn btn-ghost" type="submit" title="Neues OTP"><span class="icn ic-key" aria-hidden="true"></span></button>
                </form>
                <form method="post" action="<?= url('pruefungen/gruppenschalter') ?>" class="form-inline" title="Feste Prüfungsgruppe">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int) $u->id ?>">
                    <input type="hidden" name="back" value="/admin/users">
                    <select name="exam_group" onchange="this.form.submit()">
                        <option value=""<?= $u->examGroup === '' ? ' selected' : '' ?>>–</option>
                        <option value="A"<?= $u->examGroup === 'A' ? ' selected' : '' ?>>A</option>
                        <option value="B"<?= $u->examGroup === 'B' ? ' selected' : '' ?>>B</option>
                    </select>
                </form>
                <?php else: ?>
                <span class="muted">-</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

