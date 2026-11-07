<?php
/**
 * Zentrales Layout. Im $bare-Modus (Anmeldeseite) wird nur eine Karte gerendert.
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
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · InfoCraft</title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <a class="brand" href="<?= url('/') ?>">
            <span class="brand-mark">🔨</span>
            <span class="brand-name">Info<strong>Craft</strong></span>
        </a>
        <nav class="nav">
            <a href="<?= url('/') ?>" class="nav-item<?= $active === 'home' ? ' active' : '' ?>"><span>🏠</span> Start</a>
            <?php foreach (Infocraft\ModuleController::modules() as $module): ?>
            <a href="<?= url('modul/' . $module['slug']) ?>" class="nav-item<?= $active === 'module-' . $module['slug'] ? ' active' : '' ?>"><span><?= $module['icon'] ?></span> <?= e($module['title']) ?></a>
            <?php endforeach; ?>
            <?php if ($user !== null && ($user->role === 'teacher' || $user->role === 'admin')): ?>
            <div class="nav-sep">Verwaltung</div>
            <a href="<?= url('admin/users') ?>" class="nav-item<?= $active === 'admin' ? ' active' : '' ?>"><span>👥</span> Nutzer</a>
            <a href="<?= url('admin/import') ?>" class="nav-item<?= $active === 'admin' ? ' active' : '' ?>"><span>📥</span> CSV-Import</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-foot">
            <span class="pill">LehrplanPLUS Bayern</span>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <h1 class="topbar-title"><?= e($title) ?></h1>
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
