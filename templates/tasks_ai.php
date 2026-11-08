<?php /** @var \Infocraft\Models\User $user */ ?>
<section class="hero">
    <div class="hero-emoji">🤖</div>
    <h1>KI-Schnittstelle</h1>
    <p>Hinterlege einen KI-Zugang für die Korrekturhilfe: Die KI bewertet abgegebene Aufgaben vor und schlägt Note und Feedback vor. Es sieht nur anonymisierte Arbeiten (Pseudonyme statt Namen). Deine Zugangsdaten bleiben auf deinem Server.</p>
</section>

<section class="panel">
    <h3>Meine KI-Zugangsdaten</h3>
    <p class="muted">Gilt für deine eigenen Korrekturen. Ohne eigene Angaben gelten schulweite Zugangsdaten der Administration (falls hinterlegt).</p>
    <form method="post" action="<?= url('aufgaben/ki') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Modus</span>
            <select name="mode">
                <option value="chat"<?= ($own['mode'] ?? 'chat') === 'chat' ? ' selected' : '' ?>>Chat-API (OpenAI-/Mistral-kompatibel, z. B. api.mistral.ai/v1/chat/completions)</option>
                <option value="json"<?= ($own['mode'] ?? '') === 'json' ? ' selected' : '' ?>>Eigener JSON-Endpunkt (fester Vertrag)</option>
            </select>
        </label>
        <label class="field"><span>Endpunkt-URL</span>
            <input type="url" name="url" placeholder="https://api.mistral.ai/v1/chat/completions" value="<?= e($own['url'] ?? '') ?>">
        </label>
        <div class="two-col">
            <label class="field"><span>API-Schlüssel (leer lassen für öffentliche Endpunkte)</span>
                <input type="password" name="api_key" placeholder="Schlüssel" value="<?= e($own['key'] ?? '') ?>">
            </label>
            <label class="field"><span>Modell (z. B. mistral-small-latest)</span>
                <input type="text" name="model" placeholder="Modellname" value="<?= e($own['model'] ?? '') ?>">
            </label>
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit" title="Speichern">💾</button>
            <a class="btn btn-ghost" href="<?= url('aufgaben') ?>" title="Zurück zum Aufgabenpool">←</a>
        </div>
    </form>
    <p class="muted">Alle Felder leeren und speichern = eigene Angaben entfernen.</p>
</section>

<?php if ($user->role === 'admin'): ?>
<section class="panel">
    <h3>Schulweite KI-Zugangsdaten (Administration)</h3>
    <p class="muted">Gilt für alle Lehrkräfte ohne eigene Angaben. Die Schule entscheidet, welcher Dienst genutzt wird; Zugangsdaten der Schule müssen nicht an einzelne Lehrkräfte weitergegeben werden.</p>
    <form method="post" action="<?= url('aufgaben/ki/schule') ?>" class="form">
        <?= csrf_field() ?>
        <label class="field"><span>Modus</span>
            <select name="mode">
                <option value="chat"<?= ($school['mode'] ?? 'chat') === 'chat' ? ' selected' : '' ?>>Chat-API (OpenAI-/Mistral-kompatibel)</option>
                <option value="json"<?= ($school['mode'] ?? '') === 'json' ? ' selected' : '' ?>>Eigener JSON-Endpunkt (fester Vertrag)</option>
            </select>
        </label>
        <label class="field"><span>Endpunkt-URL</span>
            <input type="url" name="url" placeholder="https://api.mistral.ai/v1/chat/completions" value="<?= e($school['url'] ?? '') ?>">
        </label>
        <div class="two-col">
            <label class="field"><span>API-Schlüssel</span>
                <input type="password" name="api_key" placeholder="Schlüssel" value="<?= e($school['key'] ?? '') ?>">
            </label>
            <label class="field"><span>Modell</span>
                <input type="text" name="model" placeholder="Modellname" value="<?= e($school['model'] ?? '') ?>">
            </label>
        </div>
        <div class="btn-row"><button class="btn btn-primary" type="submit" title="Schulweit speichern (für alle Lehrkräfte)">💾</button></div>
    </form>
</section>
<?php endif; ?>
