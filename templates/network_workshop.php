<?php /* InfoCraft - offene Lernplattform für Informatik
   Datei: Netzwerk-Werkstatt: simuliertes Klassennetz (#220 bis #226)
   Autor: Matthias Glienke, letima development
   Lizenz: CC BY-NC-SA 4.0
   Runde 35 (#244): Toolbar mit ausklappbarem Geräte-Hinzufügen,
   PNG-Export, Drucken, einklappbare Missionen.
   Version: 0.35.0 */ ?>
<div class="net-layout">
    <aside class="net-side">
        <div class="net-card net-router-card">
            <h2><span class="icn ic-satellite-dish" aria-hidden="true"></span> Router</h2>
            <p class="net-muted">Das Zentrum des Netzes. Hier hängst du Geräte an freie Ports.</p>
            <div class="net-router-row"><span>Name</span><strong id="net-router-name">Router</strong></div>
            <div class="net-router-row"><span>IP</span><strong>192.168.0.1</strong></div>
            <div class="net-router-row"><span>WLAN</span><strong id="net-router-ssid">noch nicht eingerichtet</strong></div>
            <p class="net-muted">Neue Geräte fügst du oben über der Bühne hinzu: einfach „Gerät hinzufügen“ aufklappen.</p>
            <div class="net-netinfo" id="net-netinfo"></div>
            <p class="net-conn" id="net-conn" hidden></p>
        </div>

        <details class="net-card net-missions-card" id="net-missions-card" open>
            <summary><h2><span class="icn ic-target" aria-hidden="true"></span> Missionen</h2><span class="net-missions-toggle" aria-hidden="true">zum Ausklappen</span></summary>
            <ul class="net-missions" id="net-missions"></ul>
        </details>

        <?php if ($isTeacher): ?>
        <div class="net-card net-level-card">
            <h2><span class="icn ic-volume-low" aria-hidden="true"></span> Stufe</h2>
            <p class="net-muted" id="net-level-badge">Stufe 1 · Basis</p>
            <div class="net-add-row">
                <button type="button" class="btn net-level-btn net-level-active" id="net-level-1">1 · Basis</button>
                <button type="button" class="btn net-level-btn" id="net-level-2">2 · Oberstufe (Schichten)</button>
            </div>
            <p class="net-muted">Stufe 2 zeigt im Browser die Reise des Aufrufs durch die Schichten des OSI-Modells.</p>
        </div>
        <details class="net-card net-teacher-card" id="net-teacher-card">
            <summary><strong><span class="icn ic-user" aria-hidden="true"></span><span class="icn ic-office" aria-hidden="true"></span> Netzwerk-Manager</strong> <span class="net-muted">Klassennetz verwalten: sichern, wiederherstellen, zurücksetzen</span></summary>
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
                <a class="btn" href="<?= url('werkstatt/netzwerk/export' . ($classId !== null ? '?klasse=' . (int) $classId : '')) ?>"><span class="icn ic-floppy-disk" aria-hidden="true"></span> Netz sichern (JSON)</a>
                <button type="button" class="btn" id="net-import-btn"><span class="icn ic-folder-open" aria-hidden="true"></span> Netz wiederherstellen</button>
                <button type="button" class="btn net-danger" id="net-reset-btn"><span class="icn ic-loop2" aria-hidden="true"></span> Netz zurücksetzen</button>
            </div>
            <input type="file" id="net-import-file" accept="application/json" hidden>
            <p class="net-muted">Sichern vor Stundenende, wiederherstellen am nächsten Tag: So bleibt das Klassennetz über Unterrichtsstunden hinweg erhalten.</p>
        </details>
        <?php endif; ?>
    </aside>

    <main class="net-main">
        <div class="net-toolbar">
            <details class="net-add-panel" id="net-add-panel">
                <summary class="btn net-toolbar-add"><span class="icn ic-plus" aria-hidden="true"></span> Gerät hinzufügen</summary>
                <div class="net-add-row">
                    <select id="net-add-type" aria-label="Gerätetyp">
                        <option value="pc">Computer</option>
                        <option value="phone">Smartphone</option>
                        <option value="printer">Drucker</option>
                        <option value="webserver">Webserver</option>
                        <option value="dns">DNS-Server</option>
                        <option value="ap">Access Point</option>
                    </select>
                    <input type="text" id="net-add-name" maxlength="40" placeholder="Name (z. B. HP Pro Desk)">
                    <button type="button" class="btn net-add-btn" id="net-add-btn"><span class="icn ic-connection" aria-hidden="true"></span> Anschließen</button>
                </div>
            </details>
            <button type="button" class="btn net-tool-btn" id="net-png-btn" title="Topologie als Bilddatei herunterladen"><span class="icn ic-download2" aria-hidden="true"></span> PNG</button>
            <button type="button" class="btn net-tool-btn" id="net-print-btn" title="Topologie drucken"><span class="icn ic-printer" aria-hidden="true"></span> Drucken</button>
        </div>
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
