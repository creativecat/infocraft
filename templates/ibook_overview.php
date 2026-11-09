<?php /* InfoBook-Übersicht (#62): eigene Bücher + Klassen-InfoBooks. */ ?>
<section class="hero">
    <div class="hero-emoji">📓</div>
    <h1>InfoBook</h1>
    <p>Notizbücher für den Unterricht: Seiten mit Text, Bildern, Audio, Video, Einbettungen und Freihand-Notizen. Aus jedem InfoBook wird mit einem Klick eine Präsentation.</p>
</section>

<section class="panel">
    <h3>Neues InfoBook anlegen</h3>
    <form method="post" action="<?= url('infobuecher/anlegen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row form">
            <label class="field"><span>Titel</span><input type="text" name="title" required maxlength="160" placeholder="z. B. Informatik 9 - Unterlagen"></label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="InfoBook anlegen">➕ InfoBook anlegen</button></div>
    </form>
</section>

<?php if ($personal !== []): ?>
<h2 class="level-heading">Deine InfoBooks <span class="pill"><?= count($personal) ?></span></h2>
<?php foreach ($personal as $book): ?>
<section class="panel ibook-panel">
    <div class="praes-row">
        <div class="praes-info">
            <a class="praes-title" href="<?= url('infobuecher/' . (int) $book->id) ?>">📓 <?= e($book->title) ?></a>
            <span class="muted praes-meta">
                <?php if ($book->ownerId === $user->id): ?>Dein Buch<?php else: ?>Von <?= e(\Infocraft\Models\User::find($book->ownerId)?->name ?? 'Lehrkraft') ?> geteilt<?php endif; ?>
                <?php if ($book->shared && $book->ownerId === $user->id): ?> &middot; 🤝 für alle Lehrkräfte geteilt<?php endif; ?>
            </span>
        </div>
        <div class="praes-actions">
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $book->id . '/praesentation') ?>" title="Seiten dieses Buchs als Präsentation anzeigen">🎬 Anzeigen</a>
            <?php if ($book->ownerId === $user->id): ?>
            <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/teilen') ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="shared" value="<?= $book->shared ? '0' : '1' ?>">
                <button class="btn btn-ghost" type="submit" title="<?= $book->shared ? 'Teilen zurücknehmen' : 'Für alle Lehrkräfte sichtbar teilen' ?>"><?= $book->shared ? '🔒 Privat machen' : '🤝 Mit Lehrkräften teilen' ?></button>
            </form>
            <form method="post" action="<?= url('infobuecher/' . (int) $book->id . '/loeschen') ?>" class="inline-form" data-confirm="InfoBook wirklich löschen? Alle Seiten und Medien verschwinden mit.">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit" title="InfoBook löschen">🗑</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>
<?php else: ?>
<div class="notice">
    <strong>Noch keine InfoBooks</strong>
    <p>Lege oben dein erstes InfoBook an: Abschnitte strukturieren den Stoff, Seiten tragen Text, Bilder, Audio, Videos und Zeichnungen. Klassen-InfoBooks entstehen automatisch, sobald du sie unten öffnest.</p>
</div>
<?php endif; ?>

<h2 class="level-heading">Klassen-InfoBooks <span class="pill"><?= count($classBooks) ?></span></h2>
<?php if ($classBooks === []): ?>
<div class="notice">
    <strong>Noch keine Klassen</strong>
    <p>Ordne deinem Konto unter <a href="<?= url('unterricht/klassen') ?>">Meine Klassen</a> eine Klasse zu, dann erscheint hier das zugehörige Klassen-InfoBook.</p>
</div>
<?php endif; ?>
<?php foreach ($classBooks as $entry): ?>
<section class="panel ibook-panel">
    <div class="praes-row">
        <div class="praes-info">
            <?php if ($entry['book'] !== null): ?>
            <a class="praes-title" href="<?= url('infobuecher/' . (int) $entry['book']->id) ?>">🏫 <?= e($entry['book']->title) ?></a>
            <span class="muted praes-meta">Lernbereich, Zusammenarbeit und ein privater Bereich je Schülerin und Schüler</span>
            <?php else: ?>
            <span class="praes-title">🏫 Klasse <?= e($entry['class']->name) ?></span>
            <span class="muted praes-meta">Noch nicht angelegt</span>
            <?php endif; ?>
        </div>
        <div class="praes-actions">
            <?php if ($entry['book'] !== null): ?>
            <a class="btn btn-ghost" href="<?= url('infobuecher/' . (int) $entry['book']->id . '/praesentation') ?>" title="Seiten als Präsentation anzeigen">🎬 Anzeigen</a>
            <?php else: ?>
            <a class="btn btn-primary" href="<?= url('infobuecher/klasse/' . (int) $entry['class']->id) ?>" title="Klassen-InfoBook anlegen und öffnen">Anlegen &amp; öffnen</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endforeach; ?>
