<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Netzwerk-Werkstatt: simuliertes Klassennetz (#220 bis #226)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Version: 0.29.0 */ ?>
<section class="hero net-hero">
    <div class="hero-emoji">🌐</div>
    <h1>Netzwerk-Werkstatt</h1>
    <p>Baue ein Netz auf: Router, Geräte, Webserver und DNS. Alles, was du hier baust, sehen deine Mitspielerinnen und Mitspieler aus der Klasse sofort.</p>
</section>

<div class="net-layout">
    <aside class="net-side">
        <div class="net-card net-router-card">
            <h2>📡 Router</h2>
            <p class="net-muted">Das Zentrum des Netzes. Hier hängst du Geräte an freie Ports.</p>
            <div class="net-router-row"><span>Name</span><strong id="net-router-name">Router</strong></div>
            <div class="net-router-row"><span>IP</span><strong>192.168.0.1</strong></div>
            <div class="net-router-row"><span>WLAN</span><strong id="net-router-ssid">noch nicht eingerichtet</strong></div>
            <label class="net-label" for="net-add-type">Gerät anschließen</label>
            <div class="net-add-row">
                <select id="net-add-type">
                    <option value="pc">💻 Computer</option>
                    <option value="phone">📱 Smartphone</option>
                    <option value="printer">🖨️ Drucker</option>
                    <option value="webserver">🖥️ Webserver</option>
                    <option value="dns">🧭 DNS-Server</option>
                    <option value="ap">📶 Access Point</option>
                </select>
                <input type="text" id="net-add-name" maxlength="40" placeholder="Name (z. B. HP Pro Desk)">
                <button type="button" class="btn net-add-btn" id="net-add-btn">＋ Anschließen</button>
            </div>
            <div class="net-netinfo" id="net-netinfo"></div>
        </div>

        <div class="net-card net-missions-card">
            <h2>🎯 Missionen</h2>
            <ul class="net-missions" id="net-missions"></ul>
        </div>

        <?php if ($isTeacher): ?>
        <details class="net-card net-teacher-card" id="net-teacher-card">
            <summary><strong>🧑‍🏫 Netzwerk-Manager</strong> (Lehrkraft)</summary>
            <?php if (!empty($classes)): ?>
            <form method="get" action="<?= url('werkstatt/netzwerk') ?>" class="net-class-pick">
                <label for="net-class-select">Klasse (gemeinsames Klassennetz):</label>
                <div class="net-add-row">
                    <select id="net-class-select" name="klasse">
                        <option value="0">Mein eigenes Übungsnetz</option>
                        <?php foreach ($classes as $cls): ?>
                        <option value="<?= (int) $cls->id ?>"<?= $classId === (int) $cls->id ? ' selected' : '' ?>><?= e($cls->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn">Öffnen</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="net-teacher-actions">
                <a class="btn" href="<?= url('werkstatt/netzwerk/export' . ($classId !== null ? '?klasse=' . (int) $classId : '')) ?>">💾 Netz sichern (JSON)</a>
                <button type="button" class="btn" id="net-import-btn">📂 Netz wiederherstellen</button>
                <button type="button" class="btn net-danger" id="net-reset-btn">♻️ Netz zurücksetzen</button>
            </div>
            <input type="file" id="net-import-file" accept="application/json" hidden>
            <p class="net-muted">Sichern vor Stundenende, wiederherstellen am nächsten Tag: So bleibt das Klassennetz über Unterrichtsstunden hinweg erhalten.</p>
        </details>
        <?php endif; ?>
    </aside>

    <main class="net-main">
        <div class="net-stage-wrap">
            <svg id="net-svg" class="net-svg" viewBox="0 0 900 560" role="img" aria-label="Netz-Topologie"></svg>
            <div class="net-modal" id="net-modal" hidden>
                <div class="net-modal-box" id="net-modal-box"></div>
            </div>
        </div>
    </main>
</div>

<form id="net-import-form" method="post" action="<?= url('werkstatt/netzwerk/aktion') ?>" hidden>
    <?= csrf_field() ?>
    <input type="hidden" name="aktion" value="import">
    <input type="hidden" name="params" id="net-import-params">
</form>

<script
    id="net-boot"
    data-csrf="<?= e(csrf_token()) ?>"
    data-stand="<?= e(url('werkstatt/netzwerk/stand' . ($classId !== null ? '?klasse=' . (int) $classId : ''))) ?>"
    data-aktion="<?= e(url('werkstatt/netzwerk/aktion')) ?>"
    data-klasse="<?= $classId !== null ? (int) $classId : '' ?>"
    data-ist-lehrkraft="<?= $isTeacher ? '1' : '0' ?>"
    data-net="<?= e(json_encode($net, JSON_UNESCAPED_UNICODE)) ?>"
></script>
<script src="<?= asset_url('assets/js/network.js') ?>"></script>
