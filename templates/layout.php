<?php
/**
 * Zentrales Layout. Im $bare-Modus (Anmeldeseite) wird nur eine Karte gerendert.
 * Die Navigation links lässt sich oben links verkleinern (mini) oder ausblenden.
 */
?>
<?php if ($bare): ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · InfoCraft</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <?php foreach (flash_all() as $flashMessage): ?>
        <div class="flash flash-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
        <?php endforeach; ?>
        <?= $content ?>
    </div>
</body>
</html>
<?php return; endif; ?>
<?php
$navModules = Infocraft\Controllers\ModuleController::visible($user);
$isTeacher = $user !== null && ($user->role === 'teacher' || $user->role === 'admin');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · InfoCraft</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body data-csrf="<?= e(csrf_token()) ?>">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= url('/') ?>" title="InfoCraft">
            <span class="brand-mark">🔨</span>
            <span class="brand-name">Info<strong>Craft</strong></span>
        </a>
        <nav class="nav">
            <a href="<?= url('/') ?>" class="nav-item<?= $active === 'home' ? ' active' : '' ?>" title="Start"><span class="nav-icon">🏠</span><span class="nav-label">Start</span></a>
            <div class="nav-sep">Werkstatt</div>
            <?php foreach ($navModules as $module): ?>
                <?php if ($module['slug'] === 'tasks' && $isTeacher) { continue; } ?>
                <?php $label = $module['slug'] === 'tasks' ? 'Meine Aufgaben' : $module['title']; ?>
                <a href="<?= url($module['route'] ?? 'modul/' . $module['slug']) ?>" class="nav-item<?= $active === 'module-' . $module['slug'] ? ' active' : '' ?>" title="<?= e($label) ?>"><span class="nav-icon"><?= $module['icon'] ?></span><span class="nav-label"><?= e($label) ?></span></a>
            <?php endforeach; ?>
            <?php if ($isTeacher): ?>
            <div class="nav-sep">Verwaltung</div>
            <a href="<?= url('unterricht/klassen') ?>" class="nav-item<?= $active === 'unterricht' ? ' active' : '' ?>" title="Meine Klassen"><span class="nav-icon">🏫</span><span class="nav-label">Meine Klassen</span></a>
            <a href="<?= url('aufgaben') ?>" class="nav-item<?= $active === 'tasks' ? ' active' : '' ?>" title="Aufgabenpool"><span class="nav-icon">📝</span><span class="nav-label">Aufgabenpool</span></a>
            <a href="<?= url('admin/users') ?>" class="nav-item<?= $active === 'admin' ? ' active' : '' ?>" title="Nutzer"><span class="nav-icon">👥</span><span class="nav-label">Nutzer</span></a>
            <a href="<?= url('admin/import') ?>" class="nav-item<?= $active === 'admin' ? ' active' : '' ?>" title="CSV-Import"><span class="nav-icon">📥</span><span class="nav-label">CSV-Import</span></a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot"><span class="pill">LehrplanPLUS Bayern</span></div>
    </aside>
    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button id="sidebar-toggle" class="icon-btn" type="button" title="Navigation verkleinern oder ausblenden" aria-label="Navigation umschalten">☰</button>
                <h1 class="topbar-title"><?= e($title) ?></h1>
            </div>
            <div class="topbar-actions">
                <button id="present-toggle" class="btn btn-ghost" type="button">🖥️ Präsentieren</button>
                <?php if ($user !== null): ?>
                <div class="user-chip">
                    <span class="user-name"><?= e($user->fullName()) ?></span>
                    <span class="role role-<?= e($user->role) ?>"><?= e($user->roleLabel()) ?></span>
                </div>
                <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Abmelden</button></form>
                <?php endif; ?>
            </div>
        </header>
        <main class="content">
            <?php foreach (flash_all() as $flashMessage): ?>
            <div class="flash flash-<?= e($flashMessage['type']) ?>"><?= e($flashMessage['message']) ?></div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
        <footer class="foot">InfoCraft · Digitale Informatik-Werkstatt · PHP <?= e(PHP_VERSION) ?></footer>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>

