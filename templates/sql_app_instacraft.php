<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: SQL-Anwendung InstaCraft
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<?php $appUrl = url('werkstatt/sql/' . e($template->slug) . '/app'); ?>
<section class="panel">
    <div class="panel-head">
        <div>
            <h3>📱 <?= e($template->title) ?>-App</h3>
            <p class="muted">Ein Mobil-Frontend auf deiner SQL-Datenbank (#47): Likes, Kommentare und Follower landen in denselben Tabellen, die du in der Werkstatt mit SELECT abfragst - und umgekehrt.</p>
        </div>
        <div class="btn-row">
            <a class="btn btn-ghost" href="<?= url('werkstatt/sql/' . e($template->slug)) ?>">← Werkstatt</a>
            <a class="btn btn-ghost" href="<?= $appUrl ?>">🔄 Aktualisieren</a>
        </div>
    </div>
    <?php if (!$canWrite): ?>
    <div class="notice">Nur Leserechte: Du kannst den Feed anschauen, aber nicht liken oder kommentieren. Deine Lehrkraft kann dir Schreibrechte geben.</div>
    <?php endif; ?>
    <div class="phone-frame">
        <div class="app-bar">📸 InstaCraft <span class="app-bar-sub">Angemeldet als</span></div>
        <form method="post" action="<?= $appUrl ?>" class="app-who">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="nutzer">
            <label class="field">
                <span class="sr-only">Profil wählen</span>
                <select name="nutzer_id" onchange="this.form.submit()">
                    <?php foreach ($nutzer as $n): ?>
                    <option value="<?= (int) $n['id'] ?>"<?= (int) $n['id'] === (int) $ich['id'] ? ' selected' : '' ?>>@<?= e($n['benutzername']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <noscript><button class="btn btn-ghost" type="submit">übernehmen</button></noscript>
        </form>
        <div class="app-body">
            <?php foreach ($posts as $p): ?>
            <div class="app-card">
                <div class="app-card-title">@<?= e($p['benutzername']) ?><?= !empty($p['folgt']) ? ' · folgt dir' : '' ?></div>
                <div class="app-post-text"><?= e($p['beschreibung']) ?></div>
                <div class="muted app-card-sub"><?= e($p['datum']) ?></div>
                <div class="app-actions">
                    <span>❤️ <?= (int) $p['likes'] ?></span>
                    <span>💬 <?= (int) $p['kommentare'] ?></span>
                </div>
                <?php if ($canWrite): ?>
                <div class="app-actions">
                    <?php if (!empty($p['geliked'])): ?>
                    <form method="post" action="<?= $appUrl ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="unlike">
                        <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-ghost" type="submit">💙 Liked (zurückziehen)</button>
                    </form>
                    <?php else: ?>
                    <form method="post" action="<?= $appUrl ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="like">
                        <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-primary" type="submit">🤍 Gefällt mir</button>
                    </form>
                    <?php endif; ?>
                    <?php if ((int) $p['nutzer_id'] !== (int) $ich['id']): ?>
                        <?php if (!empty($p['folgt'])): ?>
                        <form method="post" action="<?= $appUrl ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="entfolgen">
                            <input type="hidden" name="nutzer_id" value="<?= (int) $p['nutzer_id'] ?>">
                            <button class="btn btn-ghost" type="submit">Entfolgen</button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="<?= $appUrl ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="folgen">
                            <input type="hidden" name="nutzer_id" value="<?= (int) $p['nutzer_id'] ?>">
                            <button class="btn btn-ghost" type="submit">➕ Folgen</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="app-comments">
                    <?php foreach ($kommentare as $k): ?>
                        <?php if ((int) $k['post_id'] === (int) $p['id']): ?>
                        <div class="app-comment"><strong>@<?= e($k['benutzername']) ?></strong> <?= e($k['text']) ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($canWrite): ?>
                <form method="post" action="<?= $appUrl ?>" class="app-buy">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="kommentar">
                    <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
                    <label class="field"><span class="sr-only">Kommentar</span>
                        <input type="text" name="text" maxlength="500" placeholder="Kommentar schreiben ...">
                    </label>
                    <button class="btn btn-primary" type="submit">Posten</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($posts === []): ?>
            <p class="muted app-pad">Keine Posts (mehr) in der Tabelle posts.</p>
            <?php endif; ?>
        </div>
    </div>
    <p class="muted">Tipp für den Unterricht: Nach dem Liken zeigst du in der Werkstatt, was die App getan hat:
        <code>SELECT * FROM likes;</code> oder die beliebtesten Posts mit <code>SELECT post_id, COUNT(*) FROM likes GROUP BY post_id ORDER BY 2 DESC;</code></p>
</section>
