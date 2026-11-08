<section class="hero">
    <div class="hero-emoji">🎒</div>
    <h1>Projekte von <?= e($student->fullName()) ?></h1>
    <p><?= $className !== '' ? 'Klasse ' . e($className) : 'Ohne Klasse' ?> · Als Lehrkraft kannst du jedes Projekt öffnen, ansehen und bearbeiten.</p>
</section>

<p><a class="btn btn-ghost" href="<?= url('werkstatt/code') ?>" title="Zurück zur Code-Werkstatt">←</a></p>

<div class="cards">
    <?php foreach ($projects as $project): ?>
    <div class="card project-card">
        <div class="card-icon"><?= $project->type === 'html' ? '🌐' : ($project->language === 'java' ? '☕' : '🐍') ?></div>
        <h2><?= e($project->title) ?></h2>
        <p class="card-desc muted"><?= $project->type === 'html' ? 'HTML-Seite' : e(strtoupper((string) $project->language)) ?></p>
        <div class="card-meta">
            <a class="btn btn-primary" href="<?= url('werkstatt/code/' . (int) $project->id) ?>">Öffnen</a>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($projects === []): ?>
    <p class="muted"><?= e($student->fullName()) ?> hat noch keine eigenen Projekte angelegt.</p>
    <?php endif; ?>
</div>
