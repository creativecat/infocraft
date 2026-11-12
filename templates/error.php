<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Fehlerseite
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.15.0 */ ?>
<section class="panel center">
    <div class="hero-emoji">🧭</div>
    <h2><?= e($title) ?></h2>
    <p class="muted"><?= e($message) ?></p>
    <p><a class="btn btn-primary" href="<?= url('/') ?>">Zurück zum Start</a></p>
</section>
