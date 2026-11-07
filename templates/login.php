<div class="auth-card">
    <div class="auth-brand"><span class="brand-mark">🔨</span> <span class="brand-name">Info<strong>Craft</strong></span></div>
    <p class="auth-sub">Digitale Informatik-Werkstatt</p>

    <?php if ($ssoEnabled): ?>
    <a class="btn btn-primary btn-block" href="<?= url('auth/sso/start') ?>">🔐 Mit Schulkonto anmelden (Microsoft 365)</a>
    <div class="auth-sep">oder</div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span>Anmeldename</span>
            <input type="text" name="login" required autofocus autocomplete="username" placeholder="vorname.nachname">
        </label>
        <label class="field">
            <span>Passwort</span>
            <input type="password" name="password" required autocomplete="current-password" placeholder="Passwort">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Anmelden</button>
    </form>

    <?php if (!empty($demoUsers)): ?>
    <div class="auth-sep">Demo-Zugänge (Schnelltest)</div>
    <div class="demo-grid">
        <?php foreach ($demoUsers as $entry): ?>
        <form method="post" action="<?= url('login/demo') ?>" class="demo-form">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int) $entry['user']->id ?>">
            <button class="btn btn-demo" type="submit">
                <strong><?= e($entry['user']->fullName()) ?></strong>
                <span class="role role-<?= e($entry['user']->role) ?>"><?= e($entry['user']->roleLabel()) ?></span>
                <?php if ($entry['class'] !== null): ?><span class="pill"><?= e($entry['class']) ?></span><?php endif; ?>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <p class="auth-hint">Demo-Modus: alle Passwörter lauten <code>demo1234</code>. Für den Produktivbetrieb deaktivieren und SSO nutzen.</p>
    <?php endif; ?>
</div>
