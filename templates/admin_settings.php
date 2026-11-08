<?php
/** @var string $defaultLanguage @var \Infocraft\Models\User $user */
?>
<section class="hero">
    <div class="hero-emoji">⚙️</div>
    <h1>Admin-Einstellungen</h1>
    <p>Schulweite Vorgaben für alle Lehrkräfte und Schülerinnen und Schüler. Änderungen greifen sofort.</p>
</section>

<section class="panel">
    <h3>☕ Standard-Programmiersprache</h3>
    <p class="muted">Gilt überall, wo Java und Python zur Wahl stehen: neue Projekte in der Code-Werkstatt, Aufgaben-Varianten und Sprach-Auswahlklappen. Empfehlung am bayerischen Gymnasium (NTG) ist <strong>Java</strong>; wer in der Unterstufe mit Python arbeitet, stellt hier um.</p>
    <form method="post" action="<?= url('admin/einstellungen') ?>" class="form">
        <?= csrf_field() ?>
        <div class="two-col">
            <label class="field"><span>Programmiersprache</span>
                <select name="default_language">
                    <option value="java"<?= $defaultLanguage === 'java' ? ' selected' : '' ?>>☕ Java</option>
                    <option value="python"<?= $defaultLanguage === 'python' ? ' selected' : '' ?>>🐍 Python</option>
                </select>
            </label>
            <div class="btn-row" style="align-items:flex-end;"><button class="btn btn-primary" type="submit">💾 Speichern</button></div>
        </div>
    </form>
</section>
