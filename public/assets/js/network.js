/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Netzwerk-Werkstatt (#220 bis #226)
 *
 * Simuliertes Netz im Browser: Topologie als SVG, Router-Fenster,
 * Geräte (Computer, Smartphone, Drucker, Webserver, DNS-Server,
 * Access Point), Browser mit DNS-Auflösung und Fehlermeldungen,
 * Webseiten mit eigenem HTML, Missionen für die 7. Jahrgangsstufe.
 * Provider-Netze mit Website-Anbietern, Messenger zwischen Geräten,
 * Phishing-Demo für die Lehrkraft und sichtbarer Paketflug (#227).
 * Die Engine ist DOM-frei (Node-Tests), attach() baut die Oberfläche.
 *
 * Node-Tests: module.exports; Browser: window.IC_NET.
 *
 * @version 0.37.0
 * @license CC BY-NC-SA 4.0 (InfoCraft)
 */
(function () {
  'use strict';

  /* =================== Konstanten =================== */

  var TYPES = {
    pc: { ic: 'ic-display', label: 'Computer' },
    phone: { ic: 'ic-mobile', label: 'Smartphone' },
    printer: { ic: 'ic-printer', label: 'Drucker' },
    webserver: { ic: 'ic-cloud', label: 'Webserver' },
    dns: { ic: 'ic-compass', label: 'DNS-Server' },
    ap: { ic: 'ic-connection', label: 'Access Point' }
  };

  /* Styles, die der PNG-Export mit ins SVG schreibt (#244): Die Maske der
   * Icon-Spans wird im standalone-SVG nicht mitgerendert, sie bleiben leer. */
  var NET_PNG_CSS = ''
    + '.net-router-box { fill: #0f766e; stroke: #115e59; stroke-width: 2; }'
    + '.net-router-text { fill: #fff; font-size: 17px; font-weight: 600; }'
    + '.net-sub { fill: #475569; font-size: 13px; }'
    + '.net-sub-dim { fill: #94a3b8; }'
    + '.net-link { fill: none; stroke: #16a34a; stroke-width: 2.5; }'
    + '.net-link-off { stroke: #d1d5db; stroke-dasharray: 6 5; }'
    + '.net-device rect { fill: #fff; stroke: #cbd5e1; stroke-width: 1.5; }'
    + '.net-dev-text { fill: #1f2937; font-size: 16px; font-weight: 600; }'
    + '.net-port { fill: #e2e8f0; stroke: #94a3b8; stroke-width: 1; }'
    + '.net-port-on { fill: #34d399; stroke: #0f766e; stroke-width: 1; }'
    + '.net-provider-box { fill: #e0f2fe; stroke: #0284c7; stroke-width: 2; }'
    + '.net-provider-trunk { stroke: #0284c7; fill: none; stroke-dasharray: 8 6; }'
    + '.net-provider-name { fill: #075985; font-size: 16px; font-weight: 700; }'
    + '.net-provider-ip { fill: #075985; font-size: 13px; }'
    + '.net-router-sub { fill: #d1fae5; font-size: 13px; }'
    + 'text { font-family: system-ui, sans-serif; }';

  var NEWSPAPER = '<h1>Schulzeitung</h1>'
    + '<p>Die Zeitung der Klasse: Hier schreiben wir über alles, was in der Schule passiert.</p>'
    + '<h2>Ausgabe 1</h2>'
    + '<ul><li>Sportfest: Die 7. Klasse gewinnt den Staffelpokal</li>'
    + '<li>Neuer Informatikraum: 25 Rechner ans Netz</li>'
    + '<li>Wetterbericht: Morgen sonnig</li></ul>';

  /* Beispielseiten für den Webserver (#229): Schlüssel wie im PHP-Katalog. */
  var EXAMPLES = [
    { key: 'zeitung', ic: 'ic-file-text', title: 'Schulzeitung' },
    { key: 'sport', ic: 'ic-star-full', title: 'Sportclub Eichenwald' },
    { key: 'wetter', ic: 'ic-cloud', title: 'Wetterdienst Schulnet' },
    { key: 'kochen', ic: 'ic-coffee', title: 'Kochblog Klassenküche' }
  ];

  /* Schichten des OSI-Modells (#232): von oben nach unten. */
  var LAYERS = [
    { n: 7, name: 'Anwendungsschicht' },
    { n: 6, name: 'Darstellungsschicht' },
    { n: 5, name: 'Sitzungsschicht' },
    { n: 4, name: 'Transportschicht' },
    { n: 3, name: 'Vermittlungsschicht' },
    { n: 2, name: 'Sicherungsschicht' },
    { n: 1, name: 'Bitübertragungsschicht' }
  ];

  /* =================== Engine (DOM-frei) =================== */

  function defaultNet() {
    return {
      v: 1, level: 1,
      router: { name: 'Router', ssid: '', pass: '' },
      provider: { name: 'Schulprovider', ip: '62.153.20.1' },
      devices: [], pages: [], dns: [], logs: [], messages: [], htmlProjects: []
    };
  }

  /** Netz gegen kaputte oder historische Stände absichern (#233).
   * Listen, die keine Arrays sind, oder kaputte Einträge dürfen den
   * ersten Renderdurchlauf nie töten: die Bühne bleibt nutzbar. */
  function saneNet(raw) {
    var src = (raw && typeof raw === 'object') ? raw : {};
    var out = defaultNet();
    out.v = typeof src.v === 'number' ? src.v : out.v;
    out.level = src.level === 2 ? 2 : 1;
    if (src.router && typeof src.router === 'object') { out.router = src.router; }
    if (src.provider && typeof src.provider === 'object') { out.provider = src.provider; }
    var lists = ['devices', 'pages', 'dns', 'logs', 'messages', 'htmlProjects'];
    for (var i = 0; i < lists.length; i++) {
      if (Object.prototype.toString.call(src[lists[i]]) === '[object Array]') {
        out[lists[i]] = src[lists[i]].filter(function (e) { return e && typeof e === 'object'; });
      }
    }
    return out;
  }

  /** Gerät online: Kabel dran oder im WLAN (#231). Smartphones nur per WLAN. */
  function online(d) {
    if (!d) { return false; }
    if (d.type === 'phone') { return d.wifi === true; }
    return (d.cable !== false) || d.wifi === true;
  }

  /**
   * Schichtenansicht für die Oberstufe (#232): die Reise des Aufrufs durch
   * das OSI-Modell mit konkreten Werten. Nur im Level 2, sonst [].
   */
  function layerTrace(net, device, url, res) {
    if (!net || (net.level || 1) !== 2 || !res) { return []; }
    var out = [];
    var viaDns = res.viaDns === true;
    var host = String(url || '').trim().replace(/^https?:\/\//i, '');
    out.push({ n: 7, name: LAYERS[0].name, text: (viaDns ? 'DNS: ' : '') + 'HTTP: GET http://' + host + ' (Port 80)' });
    out.push({ n: 6, name: LAYERS[1].name, text: res.kind === 'page' ? 'HTML-Dokument als Text' : 'Fehlermeldung (Code ' + res.code + ')' });
    out.push({ n: 5, name: LAYERS[2].name, text: 'Verbindung: ' + device.name + ' ↔ ' + (res.server ? res.server.name : 'Ziel') });
    out.push({ n: 4, name: LAYERS[3].name, text: 'TCP an Port 80 von ' + device.ip });
    out.push({ n: 3, name: LAYERS[4].name, text: 'IP-Paket: ' + device.ip + ' → ' + (res.ip || 'unbekannte IP') + ' · Router 192.168.0.1 leitet weiter' });
    out.push({ n: 2, name: LAYERS[5].name, text: device.wifi ? 'WLAN-Frames über "' + (device.ssid || net.router.ssid || 'WLAN') + '"' : 'Ethernet-Frames über das Kabel' });
    out.push({ n: 1, name: LAYERS[6].name, text: 'Signale auf der Leitung: Bits' });
    return out;
  }

  /** Kabel wie eine Schnur (#244): Durchhang (Schnur-Pendel) statt gerader
   * Linie. Der Ruhe-Durchhang waechst mit der Distanz, extra gibt die
   * Schwingung beim Ziehen vor (gedaempftes Pendel). */
  function cableD(x1, y1, x2, y2, extra) {
    var dx = x2 - x1;
    var dy = y2 - y1;
    var dist = Math.sqrt(dx * dx + dy * dy);
    var sag = Math.min(12 + dist * 0.14, 55) + (extra || 0);
    var mx = (x1 + x2) / 2;
    var my = (y1 + y2) / 2 + sag;
    return 'M ' + x1.toFixed(1) + ' ' + y1.toFixed(1)
      + ' Q ' + mx.toFixed(1) + ' ' + my.toFixed(1)
      + ' ' + x2.toFixed(1) + ' ' + y2.toFixed(1);
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>\"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function findDevice(net, id) {
    for (var i = 0; i < net.devices.length; i++) {
      if (String(net.devices[i].id) === String(id)) { return net.devices[i]; }
    }
    return null;
  }

  function findPage(net, id) {
    for (var i = 0; i < net.pages.length; i++) {
      if (String(net.pages[i].id) === String(id)) { return net.pages[i]; }
    }
    return null;
  }

  function hasDns(net) {
    for (var i = 0; i < net.devices.length; i++) {
      if (net.devices[i].type === 'dns') { return true; }
    }
    return false;
  }

  function dnsLookup(net, domain) {
    var d = String(domain).toLowerCase().trim();
    for (var i = 0; i < net.dns.length; i++) {
      if (String(net.dns[i].domain).toLowerCase() === d) { return String(net.dns[i].ip); }
    }
    return null;
  }

  function isIp(url) {
    return /^(?:\d{1,3}\.){3}\d{1,3}$/.test(url);
  }

  /**
   * Ein URL-Aufruf simulieren: IP direkt oder Domain über die DNS-Tabelle.
   * Ergebnis: {kind:'page', page, server, viaDns, ip, domain}
   * oder {kind:'error', code, message} mit realitätsnahen Meldungen.
   */
  function resolve(net, url) {
    var raw = String(url || '').trim().replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
    if (raw === '') {
      return { kind: 'error', code: 'empty', message: 'Bitte eine Adresse eingeben, z. B. 192.168.0.2 oder schulzeitung.net.' };
    }
    var domain = null;
    var ip = null;
    if (isIp(raw)) {
      ip = raw;
    } else {
      if (!hasDns(net)) {
        return { kind: 'error', code: 'nodns', message: 'DNS-Server antwortet nicht: Im Netz hängt kein DNS-Server, der Name "' + raw + '" kann nicht in eine IP-Adresse übersetzt werden.' };
      }
      ip = dnsLookup(net, raw);
      if (ip === null) {
        return { kind: 'error', code: 'dns', message: 'DNS-Fehler: Der Name "' + raw + '" ist in der DNS-Tabelle nicht eingetragen.' };
      }
      domain = raw;
    }
    var server = null;
    for (var i = 0; i < net.devices.length; i++) {
      if (net.devices[i].type === 'webserver' && String(net.devices[i].ip) === ip) { server = net.devices[i]; }
    }
    if (server === null) {
      return { kind: 'error', code: 'nohost', message: 'Verbindung fehlgeschlagen: Kein Webserver mit der IP ' + ip + ' im Netz.' };
    }
    var pages = [];
    for (var p = 0; p < net.pages.length; p++) {
      if (net.pages[p].server === server.id && net.pages[p].published) { pages.push(net.pages[p]); }
    }
    var page = null;
    for (var q = 0; q < pages.length; q++) {
      if (domain !== null && pages[q].domain === domain) { page = pages[q]; }
    }
    if (page === null && pages.length > 0) { page = domain === null ? pages[0] : null; }
    if (page === null) {
      return { kind: 'error', code: 404, message: '404 Webseite nicht gefunden: Auf dem Webserver ' + server.name + ' ist keine freigeschaltete Seite erreichbar.' };
    }
    return { kind: 'page', page: page, server: server, ip: ip, domain: domain, viaDns: domain !== null };
  }

  /** Volldokument für den abgeschotteten iframe einer Webseite. */
  function pageDocument(page) {
    return '<!DOCTYPE html><html><head><meta charset="utf-8">'
      + '<style>body{font-family:system-ui,-apple-system,sans-serif;padding:14px;color:#1f2937;margin:0}'
      + 'h1,h2{color:#0f766e}img{max-width:100%}</style></head><body>'
      + String(page.html || '')
      + '</body></html>';
  }

  /** WLANs im Netz: Router (falls eingerichtet) und Access Points. */
  function wlanSsids(net) {
    var out = [];
    if (net.router && net.router.ssid) { out.push(net.router.ssid); }
    for (var i = 0; i < net.devices.length; i++) {
      if (net.devices[i].type === 'ap' && out.indexOf(net.devices[i].name) < 0) { out.push(net.devices[i].name); }
    }
    return out;
  }

  /* =================== Missionen (#226) =================== */

  /* Missionen mit Lernprogression (#226, #245): grad staffelt die
   * Schwierigkeit (1 = leicht, 2 = mittel, 3 = anspruchsvoll), tip erklärt
   * das Fachwort kindgerecht für die 7. Jahrgangsstufe. */
  var GRADS = { 1: 'leicht', 2: 'mittel', 3: 'anspruchsvoll' };

  var MISSIONS = [
    { id: 'm1', grad: 1, title: 'Computer anschließen', text: 'Hänge deinen ersten Computer an den Router.', tip: 'Der Router ist der Verteiler im Netz: Er gibt jedem Gerät eine Adresse (IP) und leitet Anfragen weiter.', check: function (net) { return net.devices.some(function (d) { return d.type === 'pc'; }); } },
    { id: 'm2', grad: 1, title: 'Erster Seitenaufruf', text: 'Öffne am Computer den Browser und rufe eine Seite über die IP-Adresse auf.', tip: 'Eine IP-Adresse ist die Hausnummer eines Geräts im Netz, z. B. 192.168.0.2.', flag: 'browse-ip' },
    { id: 'm7', grad: 1, title: 'Drucker im Netz', text: 'Schließe einen Drucker an den Router an.', tip: 'Per Kabel oder WLAN: Geräte im selben Netz können den Drucker gemeinsam nutzen.', check: function (net) { return net.devices.some(function (d) { return d.type === 'printer'; }); } },
    { id: 'm3', grad: 2, title: 'WLAN einrichten', text: 'Richte am Router ein WLAN mit Namen und Passwort ein.', tip: 'Der WLAN-Name heißt SSID, wie ein Klingelschild an der Tür.', check: function (net) { return !!(net.router && net.router.ssid); } },
    { id: 'm10', grad: 2, title: 'Sicheres WLAN', text: 'Gib dem WLAN ein Passwort mit mindestens 8 Zeichen und prüfe es an einem Gerät.', tip: 'Kurze Passwörter kann man schnell erraten, wie ein Schlüssel, der fast ins Schloss passt.', check: function (net) { return !!(net.router && net.router.pass && String(net.router.pass).length >= 8); } },
    { id: 'm4', grad: 2, title: 'Smartphone online', text: 'Bring dein Smartphone mit dem WLAN-Passwort ins Netz.', tip: 'Das Smartphone prüft das Passwort beim Router, genau wie ein Türsteher.', check: function (net) { return net.devices.some(function (d) { return d.type === 'phone' && d.wifi; }); } },
    { id: 'm5', grad: 2, title: 'Schulzeitung frei', text: 'Hänge einen Webserver an, lege die Vorlage Schulzeitung an und schalte sie frei.', tip: 'Ein Webserver ist ein Computer, der Webseiten bereithält und verschickt, wie ein Archiv.', check: function (net) { return net.pages.some(function (p) { return p.published; }); } },
    { id: 'm6', grad: 3, title: 'Per Domain surfen', text: 'Hänge einen DNS-Server an und rufe die Schulzeitung über ihren Domainnamen auf.', tip: 'Der DNS-Server ist das Telefonbuch des Netzes: Er übersetzt Namen wie schulzeitung.net in IP-Adressen.', flag: 'browse-domain' },
    { id: 'm11', grad: 3, title: 'Website-Anbieter beim Provider', text: 'Zieh einen Webserver zur Provider-Zone um und rufe die Seite aus dem Schulnetz auf.', tip: 'Der Provider verbindet das Schulnetz mit dem Internet, wie die Post zwischen zwei Städten.', check: function (net) { return net.devices.some(function (d) { return (d.type === 'webserver' || d.type === 'dns') && d.zone === 'wan' && online(d); }); } },
    { id: 'm8', grad: 3, title: 'Eigene Webseite', text: 'Baue auf dem Webserver eine eigene Seite mit deinem HTML.', tip: 'HTML ist die Sprache der Webseiten: Überschriften, Absätze und Listen.', flag: 'own-page' },
    { id: 'm9', grad: 3, title: 'Nachricht im Netz', text: 'Schicke einem anderen Gerät im Messenger eine Nachricht.', tip: 'Nachrichten reisen als Pakete durchs Netz, Stück für Stück, und werden wieder zusammengesetzt.', flag: 'msg-sent' }
  ];

  function missionsState(net, flags) {
    var out = [];
    for (var i = 0; i < MISSIONS.length; i++) {
      var m = MISSIONS[i];
      var done = m.flag ? flags.indexOf(m.flag) >= 0 : (m.check ? !!m.check(net) : false);
      out.push({ id: m.id, title: m.title, text: m.text, grad: m.grad || 2, gradLabel: GRADS[m.grad || 2] || 'mittel', tip: m.tip || '', done: done });
    }
    // Klare Lernprogression: leicht vor mittel vor anspruchsvoll (#245)
    out.sort(function (a, b) { return a.grad - b.grad; });
    return out;
  }

  /* =================== Messenger (#227) =================== */

  /** Nachrichten eines Geräts: empfangen und gesendet, älteste zuerst. */
  function messagesFor(net, deviceId) {
    var out = [];
    var msgs = (net && Object.prototype.toString.call(net.messages) === '[object Array]') ? net.messages : [];
    for (var i = 0; i < msgs.length; i++) {
      if (msgs[i] && (msgs[i].to === deviceId || msgs[i].from === deviceId)) { out.push(msgs[i]); }
    }
    return out;
  }

  /** Messenger läuft auf Computern und Smartphones, beide online. */
  function messengerReady(net, d) {
    return !!d && (d.type === 'pc' || d.type === 'phone') && online(d);
  }

  /* =================== Provider-Netze & Paketweg (#227) =================== */

  /** Weg eines Aufrufs als Hüpfer-Kette: Geräte im LAN laufen über den
   * Router, Server in der Provider-Zone zusätzlich über die Provider-Cloud.
   * Rückgabe: ['dev:<id>', 'router', 'provider', 'dev:<id>'] o. ä. (DOM-frei testbar). */
  function routeHops(net, fromId, toId) {
    var a = findDevice(net, fromId);
    var b = findDevice(net, toId);
    if (!a || !b || a.id === b.id) { return []; }
    var wan = (b.zone === 'wan') || (a.zone === 'wan');
    var hops = ['dev:' + a.id, 'router'];
    if (wan) { hops.push('provider'); }
    hops.push('dev:' + b.id);
    return hops;
  }

  /* =================== Oberfläche =================== */

  function attach(doc) {
    var boot = doc.getElementById('net-boot');
    if (!boot || !doc.createElement) { return; }

    var cfg = {
      csrf: boot.getAttribute('data-csrf') || '',
      stand: boot.getAttribute('data-stand') || '',
      aktion: boot.getAttribute('data-aktion') || '',
      lehrkraft: boot.getAttribute('data-ist-lehrkraft') === '1'
    };
    var net;
    try { net = JSON.parse(boot.getAttribute('data-net') || '{}'); } catch (e) { net = defaultNet(); }
    net = saneNet(net); // kaputte Datenbankstände dürfen attach() nie töten (#233)
    var lastStand = JSON.stringify(net);

    var svg = doc.getElementById('net-svg');
    var modal = doc.getElementById('net-modal');
    var modalBox = doc.getElementById('net-modal-box');
    var openId = null;
    var flags = readFlags();

    function readFlags() {
      try {
        var raw = win().localStorage ? win().localStorage.getItem('net.flags') : null;
        return raw ? JSON.parse(raw) : [];
      } catch (e) { return []; }
    }
    function saveFlag(flag) {
      if (flags.indexOf(flag) < 0) { flags.push(flag); }
      try { if (win().localStorage) { win().localStorage.setItem('net.flags', JSON.stringify(flags)); } } catch (e) {}
    }
    function win() { return (typeof window !== 'undefined' && window) || global; }

    function byId(id) { return doc.getElementById(id); }

    /* ---------- Aktionen an den Server ---------- */

    function act(action, params, cb) {
      var body = 'aktion=' + encodeURIComponent(action) + '&params=' + encodeURIComponent(JSON.stringify(params || {}))
        + '&csrf=' + encodeURIComponent(cfg.csrf);
      var fetchFn = (typeof window !== 'undefined' && window.fetch) || (typeof global !== 'undefined' && global.fetch);
      if (!fetchFn) { if (cb) { cb(null); } return; }
      fetchFn(cfg.aktion, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
      }).then(function (r) { return r.json(); }).then(function (data) {
        if (data && data.net) { net = saneNet(data.net); lastStand = JSON.stringify(net); } // (#233)
        if (cb) { cb(data || null); }
        // Seitenaufruf und Druck ändern nur Logs: Das offene Browser-Fenster
        // darf danach NICHT neu aufgebaut werden, sonst verschwindet die
        // geladene Seite sofort wieder (#245, Bugfix Runde 37).
        if (action === 'browse' || action === 'printPage') { render({ modal: false }); }
        else { render(); }
      }).catch(function () { if (connState) { connState(false); } if (cb) { cb(null); } });
    }

    /* ---------- Topologie ---------- */

    function render(opts) {
      var rebuildModal = !(opts && opts.modal === false);
      if (byId('net-router-name')) { byId('net-router-name').textContent = net.router.name || 'Router'; }
      if (byId('net-router-ssid')) { byId('net-router-ssid').textContent = net.router.ssid || 'noch nicht eingerichtet'; }
      if (byId('net-netinfo')) {
        byId('net-netinfo').textContent = net.devices.length + ' Geräte im Netz'
          + (hasDns(net) ? ' · DNS aktiv' : ' · kein DNS-Server');
      }
      drawStage();
      renderMissions();
      renderLevel();
      if (openId !== null && modal) {
        var d = findDevice(net, openId);
        if (d === null && openId !== 'router') { closeModal(); }
        // Offenes Fenster nur neu aufbauen, wenn die Aktion es will. Das
        // 5-Sekunden-Polling ruft render({modal:false}): Eingaben und Fokus
        // im geöffneten Fenster bleiben so unangetastet (#236).
        else if (rebuildModal && typeof openKind === 'string') { openModal(openId, openKind, true); }
      }
    }

    var openKind = null;

    /* Kabel-Registry für den Paketflug (#227): 'router|dev:5' o. ä. */
    var paths = {};
    function edgeKey(a, b) { return a + '|' + b; }

    function svgEl(tag, attrs) {
      var el = doc.createElementNS ? doc.createElementNS('http://www.w3.org/2000/svg', tag) : doc.createElement(tag);
      for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { el.setAttribute(k, attrs[k]); } }
      return el;
    }

    function drawStage() {
      if (!svg) { return; }
      while (svg.firstChild) { svg.removeChild(svg.firstChild); }
      var cx = 450, cy = 290;
      var n = net.devices.length;

      // Router in der Mitte, mit sichtbaren Ports an der Unterkante (#235):
      // jedes Gerät hängt an seinem eigenen Anschluss, belegte Ports sind grün.
      var PORTS = 8;
      var rw = 180, rh = 64;
      var g = svgEl('g', {});
      g.appendChild(svgEl('rect', { x: cx - rw / 2, y: cy - rh / 2, rx: 10, width: rw, height: rh, class: 'net-router-box' }));
      var label = svgEl('text', { x: cx, y: cy - 8, class: 'net-router-text', 'text-anchor': 'middle' });
      label.innerHTML = '<span class="icn ic-satellite-dish" aria-hidden="true"></span> ' + (net.router.name || 'Router');
      g.appendChild(label);
      var ipText = svgEl('text', { x: cx, y: cy + 10, class: 'net-sub net-router-sub', 'text-anchor': 'middle' });
      ipText.textContent = '192.168.0.1';
      g.appendChild(ipText);
      var usedPorts = 0;
      for (var pi0 = 0; pi0 < n; pi0++) { if (net.devices[pi0].zone !== 'wan') { usedPorts++; } }
      usedPorts = Math.min(usedPorts, PORTS);
      for (var pi = 0; pi < PORTS; pi++) {
        var pxx = cx - 72 + pi * 21;
        g.appendChild(svgEl('rect', {
          x: pxx, y: cy + rh / 2 - 5, width: 14, height: 9, rx: 2,
          class: 'net-port' + (pi < usedPorts ? ' net-port-on' : '')
        }));
      }
      g.appendChild(svgEl('title', {}));
      (function (routerG) {
        if (routerG.addEventListener) { routerG.addEventListener('click', function () { openModal('router', 'router'); }); }
      })(g);
      svg.appendChild(g);

      // Provider-Cloud (#227): Vermittler zwischen Schulnetz und Website-Anbietern.
      // Wie die Geräte frei verschiebbar, Position wird gespeichert (Bugfix Runde 37).
      var provPosRaw = (net.provider && net.provider.pos && typeof net.provider.pos.x === 'number' && typeof net.provider.pos.y === 'number') ? net.provider.pos : null;
      var PX = provPosRaw ? Math.max(70, Math.min(830, provPosRaw.x)) : 724;
      var PY = provPosRaw ? Math.max(44, Math.min(518, provPosRaw.y)) : 86;
      var providerEdges = []; // WAN-Kabel, die beim Ziehen mitwandern
      var trunk = svgEl('path', {
        d: cableD(cx + 88, cy - rh / 2, PX, PY + 34, 6),
        class: 'net-link net-provider-trunk'
      });
      svg.appendChild(trunk);
      paths[edgeKey('router', 'provider')] = trunk;
      var provG = svgEl('g', { class: 'net-provider-group' });
      provG.appendChild(svgEl('rect', { x: PX - 96, y: PY - 32, rx: 28, width: 192, height: 66, class: 'net-provider-box' }));
      var provLabel = svgEl('text', { x: PX, y: PY - 6, class: 'net-provider-name', 'text-anchor': 'middle' });
      provLabel.innerHTML = '<span class="icn ic-cloud" aria-hidden="true"></span> ' + esc((net.provider && net.provider.name) || 'Provider');
      provG.appendChild(provLabel);
      var provIp = svgEl('text', { x: PX, y: PY + 14, class: 'net-provider-ip', 'text-anchor': 'middle' });
      provIp.textContent = (net.provider && net.provider.ip) || '62.153.20.1';
      provG.appendChild(provIp);
      (function (pg, bx, by, trunkEl) {
        if (!pg.addEventListener) { return; }
        var dragging = false;
        var moved = false;
        var start = null;
        var cur = { x: bx, y: by };
        function setTrunk(nx, ny) { trunkEl.setAttribute('d', cableD(cx + 88, cy - rh / 2, nx, ny + 34, 6)); }
        function down(ev) {
          var p = stagePoint(ev);
          if (!p) { return; }
          dragging = true;
          moved = false;
          start = { mx: p.x, my: p.y, ox: bx, oy: by };
          if (pg.setPointerCapture && ev.pointerId !== undefined) {
            try { pg.setPointerCapture(ev.pointerId); } catch (e) {}
          }
          if (ev.preventDefault) { ev.preventDefault(); }
        }
        function moveFn(ev) {
          if (!dragging || !start) { return; }
          var p = stagePoint(ev);
          if (!p) { return; }
          var nx = Math.max(70, Math.min(830, start.ox + (p.x - start.mx)));
          var ny = Math.max(44, Math.min(518, start.oy + (p.y - start.my)));
          if (Math.abs(nx - start.ox) > 3 || Math.abs(ny - start.oy) > 3) { moved = true; }
          cur = { x: nx, y: ny };
          pg.setAttribute('transform', 'translate(' + (nx - bx) + ',' + (ny - by) + ')');
          setTrunk(nx, ny);
          for (var ei = 0; ei < providerEdges.length; ei++) {
            var pe = providerEdges[ei];
            pe.el.setAttribute('d', cableD(nx - 96, ny, pe.tx, pe.ty, 0));
          }
          if (ev.preventDefault) { ev.preventDefault(); }
        }
        function up() {
          if (!dragging) { return; }
          dragging = false;
          if (moved) { act('moveProvider', { x: Math.round(cur.x), y: Math.round(cur.y) }); }
        }
        pg.addEventListener('pointerdown', down);
        pg.addEventListener('pointermove', moveFn);
        pg.addEventListener('pointerup', up);
        pg.addEventListener('pointercancel', up);
      })(provG, PX, PY, trunk);
      svg.appendChild(provG);

      // SVG-Punkt aus Mauskoordinaten (Zoom und Größe der Bühne mitrechnen)
      function stagePoint(ev) {
        if (!svg.getBoundingClientRect || !ev || typeof ev.clientX !== 'number') { return null; }
        var rect = svg.getBoundingClientRect();
        if (!rect || !rect.width || !rect.height) { return null; }
        return { x: (ev.clientX - rect.left) * (900 / rect.width), y: (ev.clientY - rect.top) * (560 / rect.height) };
      }

      var lanCount = 0;
      var wanCount = 0;
      var lanTotal = 0;
      for (var li = 0; li < n; li++) { if (net.devices[li].zone !== 'wan') { lanTotal++; } }
      for (var i = 0; i < n; i++) {
        var d = net.devices[i];
        var wan = d.zone === 'wan';
        var pos = (d.pos && typeof d.pos.x === 'number' && typeof d.pos.y === 'number')
          ? { x: Math.max(70, Math.min(830, d.pos.x)), y: Math.max(44, Math.min(518, d.pos.y)) }
          : null;
        if (!pos) {
          if (wan) { // Website-Anbieter beim Provider: obere Zone links der Cloud (#227)
            pos = { x: 130 + (wanCount % 3) * 210, y: 70 + Math.floor(wanCount / 3) * 120 };
            wanCount++;
          } else {
            var angle = (-90 + lanCount * (360 / Math.max(lanTotal, 1))) * Math.PI / 180;
            pos = { x: cx + Math.cos(angle) * 250, y: cy + Math.sin(angle) * 185 };
          }
        }
        var dx = pos.x;
        var dy = pos.y;
        var on = online(d);
        var line;
        var anchX, anchY;
        if (wan) {
          // Leitung von der Provider-Cloud zum Website-Anbieter (#227)
          anchX = PX - 96;
          anchY = PY;
          line = svgEl('path', { d: cableD(anchX, anchY, dx, dy, 0), class: 'net-link net-cable' + (on ? '' : ' net-link-off') });
          svg.appendChild(line);
          paths[edgeKey('provider', 'dev:' + d.id)] = line;
          providerEdges.push({ el: line, tx: dx, ty: dy });
        } else {
          // Leitung vom Router-Port zum Gerät (#235)
          anchX = cx - 72 + (lanCount % PORTS) * 21 + 7;
          anchY = cy + rh / 2;
          line = svgEl('path', { d: cableD(anchX, anchY, dx, dy, 0), class: 'net-link net-cable' + (on ? '' : ' net-link-off') });
          svg.appendChild(line);
          paths[edgeKey('router', 'dev:' + d.id)] = line;
          lanCount++;
        }
        var gg = svgEl('g', { class: 'net-device' + (on ? '' : ' net-device-off') });
        gg.appendChild(svgEl('rect', { x: dx - 74, y: dy - 30, rx: 10, width: 148, height: 84 }));
        var t1 = svgEl('text', { x: dx, y: dy - 4, class: 'net-dev-text', 'text-anchor': 'middle' });
        var dispName = (d.name || '').length > 18 ? (d.name || '').slice(0, 17) + '…' : (d.name || '');
        t1.innerHTML = '<span class="icn ' + (TYPES[d.type] ? TYPES[d.type].ic : 'ic-question') + '" aria-hidden="true"></span> ' + esc(dispName);
        gg.appendChild(t1);
        var t2 = svgEl('text', { x: dx, y: dy + 18, class: 'net-sub', 'text-anchor': 'middle' });
        var state = d.wifi ? ' · im WLAN ' + (d.ssid || '') : (d.cable === false ? ' · offline' : (d.type === 'phone' ? ' · kein WLAN' : ' · per Kabel'));
        t2.textContent = d.ip + state;
        gg.appendChild(t2);
        var t3 = svgEl('text', { x: dx, y: dy + 38, class: 'net-sub net-sub-dim', 'text-anchor': 'middle' });
        t3.textContent = (TYPES[d.type] ? TYPES[d.type].label : '') + (wan ? ' · beim Provider' : '');
        gg.appendChild(t3);
        (function (dev, devG, lineEl, baseX, baseY, portX0, portY0) {
          if (!devG.addEventListener) { return; }
          var dragging = false;
          var moved = false;
          var start = null;
          var boost = 0;
          var lastMove = null;
          function setCable(nx, ny, extra) {
            lineEl.setAttribute('d', cableD(portX0, portY0, nx, ny, extra));
          }
          function swing() {
            var raf = win() && win().requestAnimationFrame;
            if (!raf) { return; }
            var boost0 = boost;
            var t0 = Date.now();
            function step() {
              var t = (Date.now() - t0) / 1000;
              var extra = boost0 * Math.exp(-t * 4.5) * Math.cos(t * 16);
              if (dragging || t > 1.4 || !dev.pos) { return; }
              setCable(dev.pos.x, dev.pos.y, extra);
              raf(step);
            }
            raf(step);
          }
          function down(ev) {
            var p = stagePoint(ev);
            if (!p) { return; }
            dragging = true;
            moved = false;
            start = { mx: p.x, my: p.y, ox: baseX, oy: baseY };
            if (devG.setPointerCapture && ev.pointerId !== undefined) {
              try { devG.setPointerCapture(ev.pointerId); } catch (e) {}
            }
            if (ev.preventDefault) { ev.preventDefault(); }
          }
          function moveFn(ev) {
            if (!dragging || !start) { return; }
            var p = stagePoint(ev);
            if (!p) { return; }
            var nx = Math.max(70, Math.min(830, start.ox + (p.x - start.mx)));
            var ny = Math.max(44, Math.min(518, start.oy + (p.y - start.my)));
            if (Math.abs(nx - start.ox) > 3 || Math.abs(ny - start.oy) > 3) { moved = true; }
            devG.setAttribute('transform', 'translate(' + (nx - baseX) + ',' + (ny - baseY) + ')');
            var now = Date.now();
            var speed = lastMove ? (Math.abs(nx - lastMove.x) + Math.abs(ny - lastMove.y)) / Math.max(now - lastMove.t, 1) : 0;
            boost = Math.min(boost * 0.75 + speed * 0.9, 48);
            lastMove = { x: nx, y: ny, t: now };
            setCable(nx, ny, boost);
            dev._drag = { x: nx, y: ny };
            if (ev.preventDefault) { ev.preventDefault(); }
          }
          function up() {
            if (!dragging) { return; }
            dragging = false;
            if (dev._drag) {
              dev.pos = { x: Math.round(dev._drag.x), y: Math.round(dev._drag.y) };
              dev._drag = null;
              act('moveDevice', { id: dev.id, x: dev.pos.x, y: dev.pos.y }); // Position persistieren
              swing(); // Schnur schwingt nach, bis sie zur Ruhe kommt (#244)
            }
          }
          devG.addEventListener('pointerdown', down);
          devG.addEventListener('pointermove', moveFn);
          devG.addEventListener('pointerup', up);
          devG.addEventListener('pointercancel', up);
          devG.addEventListener('click', function () { if (!moved) { openModal(dev.id, dev.type); } });
        })(d, gg, line, dx, dy, anchX, anchY);
        svg.appendChild(gg);
      }
    }

    /* ---------- Paketflug auf den Kabeln (#227) ---------- */

    /** Paket-Punkt wandert ein Kabel entlang (SVG getPointAtLength),
     * rückwärts für die Antwort. Ohne SVG-Geometrie still aussteigen. */
    function flyPacket(pathEl, reverse, label, done) {
      var raf = win() && win().requestAnimationFrame;
      if (!svg || !raf || !pathEl || !pathEl.getTotalLength || !pathEl.getPointAtLength) {
        if (done) { done(); }
        return;
      }
      var circle = svgEl('circle', { r: '7', class: 'net-packet' });
      var txt = svgEl('text', { class: 'net-packet-label', 'text-anchor': 'middle' });
      txt.textContent = label || '';
      svg.appendChild(circle);
      svg.appendChild(txt);
      var L = pathEl.getTotalLength();
      var dur = 480;
      var t0 = Date.now();
      function step() {
        var t = Math.min((Date.now() - t0) / dur, 1);
        var pt = pathEl.getPointAtLength((reverse ? (1 - t) : t) * L);
        circle.setAttribute('cx', pt.x);
        circle.setAttribute('cy', pt.y);
        txt.setAttribute('x', pt.x);
        txt.setAttribute('y', pt.y - 12);
        if (t < 1) { raf(step); return; }
        if (circle.parentNode) { circle.parentNode.removeChild(circle); }
        if (txt.parentNode) { txt.parentNode.removeChild(txt); }
        if (done) { done(); }
      }
      raf(step);
    }

    /** Hüpfer-Kette animieren: Hinweg mit label, Rückweg mit responseLabel. */
    function animateRoute(hops, label, responseLabel) {
      var chain = [];
      for (var i = 0; i < hops.length - 1; i++) {
        (function (a, b) {
          chain.push(function (next) {
            flyPacket(paths[edgeKey(a, b)] || paths[edgeKey(b, a)], false, label, next);
          });
        })(hops[i], hops[i + 1]);
      }
      if (responseLabel) {
        for (var r = hops.length - 1; r > 0; r--) {
          (function (a, b) {
            chain.push(function (next) {
              flyPacket(paths[edgeKey(a, b)] || paths[edgeKey(b, a)], true, responseLabel, next);
            });
          })(hops[r], hops[r - 1]);
        }
      }
      var idx = 0;
      (function run() { if (idx < chain.length) { chain[idx++](run); } })();
    }

    /* ---------- Missionen ---------- */

    function renderMissions() {
      var list = byId('net-missions');
      if (!list) { return; }
      while (list.firstChild) { list.removeChild(list.firstChild); }
      var state = missionsState(net, flags);
      for (var i = 0; i < state.length; i++) {
        var li = doc.createElement('li');
        li.setAttribute('class', state[i].done ? 'net-mission-done' : '');
        li.appendChild(el('span', 'net-mission-badge net-mission-grad-' + state[i].grad, state[i].gradLabel));
        li.appendChild((function () {
          var s = doc.createElement('span');
          s.innerHTML = (state[i].done ? '<span class="icn ic-checkmark" aria-hidden="true"></span> ' : '<span class="icn ic-checkbox-unchecked" aria-hidden="true"></span> ')
            + String(state[i].title).replace(/[&<>]/g, '') + ': ' + String(state[i].text).replace(/[&<>]/g, '');
          return s;
        })());
        if (state[i].tip) {
          li.appendChild(el('div', 'net-mission-tip', 'Fachwort: ' + state[i].tip));
        }
        list.appendChild(li);
      }
    }

    /* ---------- Geräte-Fenster (Modal) ---------- */

    function el(tag, cls, text) {
      var e = doc.createElement(tag);
      if (cls) { e.setAttribute('class', cls); }
      if (text != null) { e.textContent = text; }
      return e;
    }

    /** Zwischen Schulnetz und Provider-Zone umziehen (#227): Webserver und
     * DNS-Server können als Website-Anbieter hinter den Provider ziehen. */
    function zoneToggle(d) {
      if (d.type !== 'webserver' && d.type !== 'dns') { return el('span', null); }
      var box = el('div', 'net-zone-toggle');
      var btn = el('button', 'btn net-btn-sm');
      var wan = d.zone === 'wan';
      btn.innerHTML = wan
        ? '<span class="icn ic-arrow-left" aria-hidden="true"></span> Zurück ins Schulnetz'
        : '<span class="icn ic-cloud" aria-hidden="true"></span> Zum Provider ziehen (Website-Anbieter)';
      btn.setAttribute('aria-label', wan ? 'Gerät zurück ins Schulnetz ziehen' : 'Gerät zur Provider-Zone ziehen');
      btn.addEventListener('click', function () {
        act('setZone', { id: d.id, zone: wan ? 'lan' : 'wan' });
      });
      box.appendChild(btn);
      box.appendChild(el('span', 'net-muted', wan ? 'hängt beim Provider' : 'hängt im Schulnetz'));
      return box;
    }

    /** Entfernen-Button fuer jedes Geraetefenster (#244): Auch AP, DNS-Server
     * und Co. lassen sich jetzt abhaengen. Mit Rueckfrage, gegen Versehen. */
    function deviceFooter(d) {
      var box = el('div', 'net-footer-actions');
      var del = el('button', 'btn net-btn net-danger');
      del.innerHTML = '<span class="icn ic-bin2" aria-hidden="true"></span> Gerät entfernen';
      del.setAttribute('aria-label', 'Gerät ' + d.name + ' entfernen');
      del.addEventListener('click', function () {
        if (win().confirm && !win().confirm('Gerät "' + d.name + '" wirklich vom Netz trennen und entfernen?')) { return; }
        act('removeDevice', { id: d.id });
        closeModal();
      });
      box.appendChild(del);
      return box;
    }

    function closeModal() {
      openId = null;
      openKind = null;
      if (modal) { modal.setAttribute('hidden', ''); }
    }

    function head(title, sub, icon) {
      var box = el('div', 'net-modal-head');
      var strong = el('strong', null, title);
      if (icon) { strong.innerHTML = '<span class="icn ' + icon + '" aria-hidden="true"></span> ' + title; }
      box.appendChild(strong);
      if (sub) { box.appendChild(el('span', 'net-muted', sub)); }
      var x = el('button', 'btn net-close');
      x.innerHTML = '<span class="icn ic-cross" aria-hidden="true"></span>';
      x.setAttribute('aria-label', 'Fenster schließen');
      x.addEventListener('click', closeModal);
      box.appendChild(x);
      return box;
    }

    function field(labelText, inputTag, value, attrs) {
      var wrap = el('label', 'net-field');
      wrap.appendChild(el('span', null, labelText));
      var input = el(inputTag, 'net-input');
      for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { input.setAttribute(k, attrs[k]); } }
      if (inputTag === 'textarea') { input.value = String(value == null ? '' : value); }
      else { input.setAttribute('value', String(value == null ? '' : value)); }
      wrap.appendChild(input);
      return wrap;
    }

    /** Eingabewerte im Fenster einsammeln, bevor ein Neuaufbau sie wegwischt. */
    function collectInputs(root) {
      var out = {};
      if (!root) { return out; }
      var stack = [root];
      while (stack.length > 0) {
        var node = stack.pop();
        var kids = node.children || [];
        for (var k = 0; k < kids.length; k++) {
          var c = kids[k];
          if ((c.tagName === 'INPUT' || c.tagName === 'TEXTAREA' || c.tagName === 'SELECT') && c.id && typeof c.value === 'string') {
            out[c.id] = c.value;
          }
          stack.push(c);
        }
      }
      return out;
    }

    /** Gesammelte Eingabewerte nach dem Neuaufbau wiederherstellen (#236). */
    function applyInputs(vals) {
      for (var k in vals) {
        if (Object.prototype.hasOwnProperty.call(vals, k)) {
          var e = byId(k);
          if (e && typeof e.value === 'string') { e.value = vals[k]; }
        }
      }
    }

    function openModal(id, kind, isRefresh) {
      openId = id;
      openKind = kind;
      if (!modal || !modalBox) { return; }
      var keep = isRefresh ? collectInputs(modalBox) : null;
      while (modalBox.firstChild) { modalBox.removeChild(modalBox.firstChild); }
      modalBox.appendChild(buildModal(id, kind));
      modal.removeAttribute('hidden');
      if (keep) { applyInputs(keep); }
    }

    function buildModal(id, kind) {
      if (id === 'router') {
        return routerWindow();
      }
      var d = findDevice(net, id);
      if (d === null) { closeModal(); return el('div', null); }
      if (d.type === 'pc' || d.type === 'phone') { return browserWindow(d); }
      if (d.type === 'webserver') { return webserverWindow(d); }
      if (d.type === 'dns') { return dnsWindow(d); }
      if (d.type === 'ap') { return apWindow(d); }
      if (d.type === 'printer') { return printerWindow(d); }
      return simpleWindow(d);
    }

    /* ---------- Router-Fenster ---------- */

    function routerWindow() {
      var box = el('div', null);
      box.appendChild(head(net.router.name || 'Router', '192.168.0.1 · Verwaltungsoberfläche', 'ic-satellite-dish'));
      var passNote = el('p', 'net-muted');
      passNote.textContent = net.router.ssid
        ? 'WLAN "' + net.router.ssid + '" · Passwort: ' + (net.router.pass || '(noch keins)')
        : 'WLAN noch nicht eingerichtet. Vergib Namen und Passwort, damit Smartphones beitreten können.';
      box.appendChild(passNote);
      var ssid = field('WLAN-Name (SSID)', 'input', net.router.ssid, { id: 'net-w-ssid', maxlength: '32' });
      var pass = field('WLAN-Passwort', 'input', net.router.pass, { id: 'net-w-pass', maxlength: '32' });
      box.appendChild(ssid);
      box.appendChild(pass);
      var save = el('button', 'btn net-btn');
      save.innerHTML = '<span class="icn ic-floppy-disk" aria-hidden="true"></span> WLAN speichern';
      save.addEventListener('click', function () {
        var s = doc.getElementById('net-w-ssid');
        var p = doc.getElementById('net-w-pass');
        act('setWlan', { ssid: s ? s.value : '', pass: p ? p.value : '' }, function (res) {
          if (res && res.ok) { saveFlag('wlan-set'); }
        });
      });
      box.appendChild(save);
      // Provider-Name (#227): Lehrkraft benennt den Vermittler ins Internet
      if (cfg.lehrkraft) {
        var provHead = el('h3', 'net-sec-h', 'Provider (Verbindung ins Internet)');
        box.appendChild(provHead);
        box.appendChild(field('Name des Providers', 'input', (net.provider && net.provider.name) || 'Schulprovider', { id: 'net-provider-name', maxlength: '40' }));
        var pSave = el('button', 'btn net-btn-sm');
        pSave.innerHTML = '<span class="icn ic-cloud" aria-hidden="true"></span> Provider umbenennen';
        pSave.addEventListener('click', function () {
          var nEl = doc.getElementById('net-provider-name');
          act('setProvider', { name: nEl ? nEl.value : '' });
        });
        box.appendChild(pSave);
      }
      box.appendChild(logsSection('Router-Log (wer ruft wen auf)'));
      return box;
    }

    function logsSection(title) {
      var sec = el('details', 'net-logs');
      var sum = el('summary', null, title + ' (' + net.logs.length + ')');
      sec.appendChild(sum);
      if (net.logs.length === 0) {
        sec.appendChild(el('p', 'net-muted', 'Noch keine Einträge.'));
      }
      for (var i = 0; i < net.logs.length; i++) {
        var line = el('div', 'net-log-line');
        line.textContent = net.logs[i].t + ' · ' + net.logs[i].msg;
        sec.appendChild(line);
      }
      return sec;
    }

    /* ---------- Browser (Computer und Smartphone) ---------- */

    function browserWindow(d) {
      var box = el('div', 'net-window net-window-' + d.type);
      box.appendChild(head(d.name, d.ip + (d.wifi ? ' · im WLAN ' + (d.ssid || '') : ''), d.type === 'phone' ? 'ic-mobile' : 'ic-display'));

      if (d.type === 'phone' && !d.wifi) {
        var note = el('p', 'net-muted', 'Dieses Smartphone ist noch nicht im Netz. Wähle ein WLAN und gib das Passwort ein.');
        box.appendChild(note);
        var ssids = wlanSsids(net);
        var sel = el('select', 'net-input');
        sel.setAttribute('id', 'net-wlan-sel');
        if (ssids.length === 0) {
          var o = el('option', null, 'Kein WLAN eingerichtet');
          o.setAttribute('value', '');
          sel.appendChild(o);
        }
        for (var s = 0; s < ssids.length; s++) {
          var opt = el('option', null, ssids[s]);
          opt.setAttribute('value', ssids[s]);
          sel.appendChild(opt);
        }
        var wrap = el('label', 'net-field');
        wrap.appendChild(el('span', null, 'WLAN'));
        wrap.appendChild(sel);
        box.appendChild(wrap);
        box.appendChild(field('Passwort', 'input', '', { id: 'net-wlan-pass', type: 'password' }));
        var join = el('button', 'btn net-btn');
        join.innerHTML = '<span class="icn ic-connection" aria-hidden="true"></span> Verbinden';
        join.addEventListener('click', function () {
          var sEl = doc.getElementById('net-wlan-sel');
          var pEl = doc.getElementById('net-wlan-pass');
          act('joinWlan', { id: d.id, ssid: sEl ? sEl.value : '', pass: pEl ? pEl.value : '' });
        });
        box.appendChild(join);
        box.appendChild(deviceFooter(d));
        return box;
      }

      /* Realistischer Browser im Geraetefenster (#244): Titelleiste mit
       * Fenster-Punkten und Tab, darunter Adressleiste und Seite. */
      var shell = el('div', 'net-browser-shell net-shell-' + d.type);
      var titlebar = el('div', 'net-browser-titlebar');
      titlebar.appendChild(el('span', 'net-win-dot net-win-r'));
      titlebar.appendChild(el('span', 'net-win-dot net-win-y'));
      titlebar.appendChild(el('span', 'net-win-dot net-win-g'));
      titlebar.appendChild(el('span', 'net-browser-tab', (d.type === 'phone' ? 'Browser' : 'Neuer Tab')));
      shell.appendChild(titlebar);

      var bar = el('div', 'net-urlbar');
      var input = el('input', 'net-input net-url');
      input.setAttribute('id', 'net-url-in');
      input.setAttribute('placeholder', '192.168.0.2 oder schulzeitung.net');
      var go = el('button', 'btn net-btn');
      go.innerHTML = '<span class="icn ic-arrow-right" aria-hidden="true"></span> Öffnen';
      var view = el('div', 'net-view');
      var status = el('div', 'net-status net-muted', 'Bereit. Gib eine Adresse ein.');
      var lastRes = null;
      var lastUrl = '';

      // Netzdrucker im Browser (#231)
      var printers = [];
      for (var prI = 0; prI < net.devices.length; prI++) {
        if (net.devices[prI].type === 'printer' && online(net.devices[prI])) { printers.push(net.devices[prI]); }
      }
      if (printers.length > 0) {
        var printBtn = el('button', 'btn net-btn-sm net-print-btn');
        printBtn.innerHTML = '<span class="icn ic-printer" aria-hidden="true"></span> Auf ' + printers[0].name + ' drucken';
        printBtn.addEventListener('click', function () {
          act('printPage', { printer: printers[0].id, who: d.name, url: lastUrl });
        });
        bar.appendChild(printBtn);
      }

      // Schichtenansicht im Level 2 (#232)
      var layersBox = null;
      function renderLayers() {
        if ((net.level || 1) !== 2) { return; }
        var trace = layerTrace(net, d, lastUrl, lastRes);
        if (trace.length === 0) { return; }
        layersBox = el('details', 'net-layers');
        var osiSum = el('summary', null); osiSum.innerHTML = '<span class="icn ic-stack" aria-hidden="true"></span> Schichtenansicht (OSI-Modell)'; layersBox.appendChild(osiSum);
        for (var i = 0; i < trace.length; i++) {
          var line = el('div', 'net-layer-line');
          line.textContent = 'Schicht ' + trace[i].n + ' · ' + trace[i].name + ': ' + trace[i].text;
          layersBox.appendChild(line);
        }
        box.appendChild(layersBox);
      }

      function load() {
        var url = input.value;
        lastUrl = String(url).trim();
        var res = resolve(net, url);
        lastRes = res;
        view.textContent = '';
        if (layersBox && layersBox.parentNode) { layersBox.parentNode.removeChild(layersBox); layersBox = null; }
        if (res.kind === 'page') {
          var frame = el('iframe', 'net-frame');
          frame.setAttribute('sandbox', 'allow-scripts allow-forms');
          frame.setAttribute('title', 'Simulierte Webseite (abgeschottet)');
          frame.setAttribute('srcdoc', pageDocument(res.page));
          view.appendChild(frame);
          status.textContent = 'Geladen von ' + res.server.name + ' (' + res.ip + ')'
            + (res.server.zone === 'wan' ? ' · über den Provider ' + ((net.provider && net.provider.name) || 'Provider') : '')
            + (res.viaDns ? ' · über DNS aufgelöst' : ' · direkt über die IP');
          saveFlag(isIp(String(url).trim().replace(/^https?:\/\//i, '')) ? 'browse-ip' : 'browse-domain');
          // Sichtbarer Paketflug auf den Kabeln (#227): Anfrage hin, Antwort zurück
          animateRoute(routeHops(net, d.id, res.server.id), 'GET', 'HTML');
          act('browse', { who: d.name, url: String(url).trim(), server: res.server.id, ip: d.ip, code: 200, path: '/' });
        } else {
          var err = el('div', 'net-error');
          err.innerHTML = '<span class="icn ic-warning" aria-hidden="true"></span> ' + esc(res.message);
          view.appendChild(err);
          status.textContent = 'Fehler · Code ' + res.code;
          if (res.code === 404 && res.server) { // 404 landet im Webserver-Log (#218)
            act('browse', { who: d.name, url: String(url).trim(), server: res.server.id, ip: d.ip, code: 404, path: '/' });
          }
        }
        renderLayers();
      }
      go.addEventListener('click', load);
      input.addEventListener('keydown', function (ev) { if (ev && ev.key === 'Enter') { load(); } });
      bar.appendChild(input);
      bar.appendChild(go);
      shell.appendChild(bar);
      shell.appendChild(status);
      shell.appendChild(view);
      box.appendChild(shell);
      box.appendChild(messengerSection(d));
      box.appendChild(deviceFooter(d));
      return box;
    }

    /* ---------- Messenger (#227): Nachrichten zwischen Geräten ---------- */

    function messengerSection(d) {
      var sec = el('div', 'net-messenger');
      var h = el('h3', 'net-sec-h', 'Messenger');
      sec.appendChild(h);
      var partners = [];
      for (var i = 0; i < net.devices.length; i++) {
        var o = net.devices[i];
        if (o.id !== d.id && (o.type === 'pc' || o.type === 'phone') && online(o)) { partners.push(o); }
      }
      if (partners.length === 0) {
        sec.appendChild(el('p', 'net-muted', 'Kein anderes Gerät im Netz, dem du schreiben kannst. Computer und Smartphones erscheinen hier, sobald sie online sind.'));
        return sec;
      }
      // Verlauf: empfangene und gesendete Nachrichten dieses Geräts
      var msgs = messagesFor(net, d.id);
      if (msgs.length > 0) {
        var hist = el('div', 'net-msg-list');
        for (var m = 0; m < msgs.length; m++) {
          (function (msg) {
            var row = el('div', 'net-msg-row' + (msg.to === d.id ? ' net-msg-in' : ' net-msg-out'));
            var headTxt = (msg.to === d.id ? 'von ' + msg.fromName : 'an ' + msg.toName) + ' · ' + (msg.t || '');
            var headEl = el('span', 'net-msg-meta', headTxt);
            row.appendChild(headEl);
            row.appendChild(el('span', null, msg.text));
            if (msg.spoof && cfg.lehrkraft) {
              // Spoofing-Kennzeichnung: nur die Lehrkraft sieht die Täuschung (#227)
              var badge = el('span', 'net-msg-spoof');
              badge.innerHTML = '<span class="icn ic-warning" aria-hidden="true"></span> getäuscht: wirklich von ' + esc(msg.realFrom || 'Lehrkraft');
              row.appendChild(badge);
            }
            if (cfg.lehrkraft) {
              var del = el('button', 'btn net-btn-sm net-danger');
              del.setAttribute('aria-label', 'Nachricht entfernen');
              del.innerHTML = '<span class="icn ic-bin2" aria-hidden="true"></span>';
              del.addEventListener('click', function () { act('deleteMessage', { id: msg.id }); });
              row.appendChild(del);
            }
            hist.appendChild(row);
          })(msgs[m]);
        }
        sec.appendChild(hist);
      }
      var sel = el('select', 'net-input');
      sel.setAttribute('id', 'net-msg-to');
      for (var p = 0; p < partners.length; p++) {
        var opt = el('option', null, partners[p].name);
        opt.setAttribute('value', partners[p].id);
        sel.appendChild(opt);
      }
      var wrap = el('label', 'net-field');
      wrap.appendChild(el('span', null, 'An'));
      wrap.appendChild(sel);
      sec.appendChild(wrap);
      sec.appendChild(field('Nachricht', 'textarea', '', { id: 'net-msg-text', rows: '2', maxlength: '200' }));
      var send = el('button', 'btn net-btn');
      send.innerHTML = '<span class="icn ic-paper-plane" aria-hidden="true"></span> Senden';
      send.addEventListener('click', function () {
        var tEl = doc.getElementById('net-msg-to');
        var xEl = doc.getElementById('net-msg-text');
        act('sendMessage', { from: d.id, to: tEl ? tEl.value : '', text: xEl ? xEl.value : '' }, function (res) {
          if (res && res.ok) {
            saveFlag('msg-sent');
            if (xEl) { xEl.value = ''; }
            // Paket fliegt sichtbar zum Empfänger (#227)
            var toDev = findDevice(net, tEl ? tEl.value : '');
            if (toDev) { animateRoute(routeHops(net, d.id, toDev.id), 'Nachricht', null); }
          }
        });
      });
      sec.appendChild(send);
      // Phishing-Demo der Lehrkraft (#227): Absender-Identität täuschen
      if (cfg.lehrkraft) {
        var phish = el('details', 'net-phish');
        var sum = el('summary', null, '');
        sum.innerHTML = '<span class="icn ic-warning" aria-hidden="true"></span> Phishing-Demo: Absender täuschen';
        phish.appendChild(sum);
        var note = el('p', 'net-muted', 'Für den Unterricht: Die Nachricht zeigt den Namen eines anderen Geräts, nur du siehst die Kennzeichnung. Perfekt für die Frage "Wem könntest du diese Nachricht vertrauen?"');
        phish.appendChild(note);
        var victimSel = el('select', 'net-input');
        victimSel.setAttribute('id', 'net-phish-from');
        var candidates = [];
        for (var v = 0; v < net.devices.length; v++) {
          var vd = net.devices[v];
          if (vd.type === 'pc' || vd.type === 'phone') { candidates.push(vd); }
        }
        for (var c = 0; c < candidates.length; c++) {
          var vopt = el('option', null, candidates[c].name);
          vopt.setAttribute('value', candidates[c].id);
          victimSel.appendChild(vopt);
        }
        var vwrap = el('label', 'net-field');
        vwrap.appendChild(el('span', null, 'Angeblischer Absender'));
        vwrap.appendChild(victimSel);
        phish.appendChild(vwrap);
        var toSel = el('select', 'net-input');
        toSel.setAttribute('id', 'net-phish-to');
        for (var t = 0; t < partners.length; t++) {
          var topt = el('option', null, partners[t].name);
          topt.setAttribute('value', partners[t].id);
          toSel.appendChild(topt);
        }
        var twrap = el('label', 'net-field');
        twrap.appendChild(el('span', null, 'Empfänger'));
        twrap.appendChild(toSel);
        phish.appendChild(twrap);
        phish.appendChild(field('Nachricht', 'textarea', '', { id: 'net-phish-text', rows: '2', maxlength: '200' }));
        var pSend = el('button', 'btn net-btn net-danger');
        pSend.innerHTML = '<span class="icn ic-warning" aria-hidden="true"></span> Täuschung senden';
        pSend.addEventListener('click', function () {
          var fEl = doc.getElementById('net-phish-from');
          var ttEl = doc.getElementById('net-phish-to');
          var txEl = doc.getElementById('net-phish-text');
          act('spoofMessage', { from: fEl ? fEl.value : '', to: ttEl ? ttEl.value : '', text: txEl ? txEl.value : '' }, function (res) {
            if (res && res.ok && txEl) { txEl.value = ''; }
          });
        });
        phish.appendChild(pSend);
        sec.appendChild(phish);
      }
      return sec;
    }

    /* ---------- Webserver-Fenster (#222) ---------- */

    function webserverWindow(d) {
      var box = el('div', null);
      box.appendChild(head(d.name, d.ip + ' · Webseiten verwalten', 'ic-cloud'));
      box.appendChild(zoneToggle(d));
      box.appendChild(el('p', 'net-muted', 'Webseiten für: ' + d.name + '. Bearbeite den HTML-Quelltext und schalte die Seite frei, damit sie über die IP oder Domain erreichbar ist.'));
      var mine = [];
      for (var i = 0; i < net.pages.length; i++) {
        if (net.pages[i].server === d.id) { mine.push(net.pages[i]); }
      }
      for (var p = 0; p < mine.length; p++) {
        (function (page) {
          var row = el('div', 'net-pagerow');
          var pgStrong = el('strong', null, page.title);
          pgStrong.appendChild(el('span', 'icn ' + (page.published ? 'ic-checkmark' : 'ic-circle'), ''));
          row.appendChild(pgStrong);
          row.appendChild(el('span', 'net-muted', page.domain ? page.domain : '(nur über IP)'));
          var pub = el('button', 'btn net-btn-sm');
          pub.innerHTML = page.published ? '<span class="icn ic-lock" aria-hidden="true"></span> Sperren' : '<span class="icn ic-checkmark" aria-hidden="true"></span> Freischalten';
          pub.addEventListener('click', function () {
            act(page.published ? 'unpublishPage' : 'publishPage', { id: page.id });
          });
          var edit = el('button', 'btn net-btn-sm');
          edit.innerHTML = '<span class="icn ic-pencil" aria-hidden="true"></span> Bearbeiten';
          edit.addEventListener('click', function () { pageEditor(d, page); });
          var del = el('button', 'btn net-btn-sm net-danger');
          del.innerHTML = '<span class="icn ic-bin2" aria-hidden="true"></span>';
          del.addEventListener('click', function () { act('deletePage', { id: page.id }); });
          var toHtmlBtn = el('button', 'btn net-btn-sm');
          toHtmlBtn.innerHTML = '<span class="icn ic-cube" aria-hidden="true"></span> in die HTML-Werkstatt';
          toHtmlBtn.addEventListener('click', function () {
            act('toHtml', { id: page.id }, function (res) {
              if (res && res.ok && res.url) { win().location.href = res.url; }
            });
          });
          row.appendChild(pub);
          row.appendChild(edit);
          row.appendChild(toHtmlBtn);
          row.appendChild(del);
          box.appendChild(row);
        })(mine[p]);
      }
      if (mine.length === 0) {
        box.appendChild(el('p', 'net-muted', 'Noch keine Webseiten. Ohne freigeschaltete Seite zeigt der Browser übrigens 404.'));
      }

      // Beispielseiten (#229)
      var exHead = el('h3', 'net-sec-h', 'Beispielseiten zum Ausprobieren');
      box.appendChild(exHead);
      var exRow = el('div', 'net-examples');
      for (var ex = 0; ex < EXAMPLES.length; ex++) {
        (function (example) {
          var b = el('button', 'btn net-btn-sm net-example');
          b.innerHTML = '<span class="icn ' + example.ic + '" aria-hidden="true"></span> ' + example.title;
          b.addEventListener('click', function () {
            act('loadExample', { server: d.id, example: example.key }, function (res) {
              if (res && res.ok) { saveFlag('example-loaded'); }
            });
          });
          exRow.appendChild(b);
        })(EXAMPLES[ex]);
      }
      box.appendChild(exRow);

      // Brücke zur HTML-Werkstatt (#230)
      var bridgeHead = el('h3', 'net-sec-h', 'Aus der HTML-Werkstatt übernehmen');
      box.appendChild(bridgeHead);
      var projects = net.htmlProjects || [];
      if (projects.length > 0) {
        var projSel = el('select', 'net-input');
        projSel.setAttribute('id', 'net-html-sel');
        for (var pr = 0; pr < projects.length; pr++) {
          var popt = el('option', null, projects[pr].title);
          popt.setAttribute('value', String(projects[pr].id));
          projSel.appendChild(popt);
        }
        var projWrap = el('label', 'net-field');
        projWrap.appendChild(el('span', null, 'Dein HTML-Projekt'));
        projWrap.appendChild(projSel);
        box.appendChild(projWrap);
        var loadHtml = el('button', 'btn net-btn');
        loadHtml.innerHTML = '<span class="icn ic-download3" aria-hidden="true"></span> Auf den Webserver laden';
        loadHtml.addEventListener('click', function () {
          var pEl = doc.getElementById('net-html-sel');
          act('fromHtml', { server: d.id, projectId: pEl ? pEl.value : 0 });
        });
        box.appendChild(loadHtml);
      } else {
        box.appendChild(el('p', 'net-muted', 'Du hast noch keine HTML-Projekte. Lege eines in der HTML-Werkstatt an, dann erscheint es hier.'));
      }

      var add = el('button', 'btn net-btn');
      add.innerHTML = '<span class="icn ic-plus" aria-hidden="true"></span> Neue Webseite (Vorlage Schulzeitung)';
      add.addEventListener('click', function () {
        act('savePage', { server: d.id, title: 'Schulzeitung', domain: 'schulzeitung.net', html: NEWSPAPER }, function (res) {
          if (res && res.ok) { saveFlag('page-created'); }
        });
      });
      box.appendChild(add);
      var own = el('button', 'btn net-btn');
      own.innerHTML = '<span class="icn ic-plus" aria-hidden="true"></span> Leere Seite mit eigenem HTML';
      own.addEventListener('click', function () { pageEditor(d, null); });
      box.appendChild(own);
      var hits = el('details', 'net-logs');
      hits.appendChild(el('summary', null, 'Zugriffs-Logs anzeigen'));
      var shown = 0;
      for (var l = 0; l < net.logs.length; l++) {
        if (net.logs[l].msg.indexOf(d.ip) >= 0 || net.logs[l].msg.indexOf(d.name) >= 0) {
          hits.appendChild(el('div', 'net-log-line', net.logs[l].t + ' · ' + net.logs[l].msg));
          shown++;
        }
      }
      if (shown === 0) { hits.appendChild(el('p', 'net-muted', 'Noch keine Zugriffe auf diesen Webserver.')); }
      box.appendChild(hits);
      box.appendChild(serverLogSection(d));
      box.appendChild(deviceFooter(d));
      return box;
    }

    // Strukturiertes Webserver-Log je Geraet (#218): Zeit, Quell-IP, Pfad, Statuscode
    function serverLogSection(d) {
      var entries = (d.log && d.log.length) ? d.log : [];
      var sec = el('details', 'net-logs');
      sec.appendChild(el('summary', null, 'Webserver-Log (Anfragen an ' + d.name + ') (' + entries.length + ')'));
      if (entries.length === 0) {
        sec.appendChild(el('p', 'net-muted', 'Noch keine Anfragen. Rufe mit einem Browser die IP oder Domain dieses Servers auf.'));
      }
      for (var i = 0; i < entries.length; i++) {
        var e = entries[i];
        var cls = e.code === 404 ? 'net-log-line net-error' : 'net-log-line';
        sec.appendChild(el('div', cls, e.t + ' · von ' + e.ip + ' · ' + e.path + ' · HTTP ' + e.code));
      }
      return sec;
    }

    function pageEditor(d, page) {
      var box = el('div', null);
      var isNew = page === null;
      box.appendChild(head(isNew ? 'Neue Webseite' : page.title, 'Quelltext bearbeiten', 'ic-pencil'));
      box.appendChild(field('Titel', 'input', isNew ? 'Meine Seite' : page.title, { id: 'net-pg-title', maxlength: '60' }));
      box.appendChild(field('Domain (für DNS)', 'input', isNew ? '' : page.domain, { id: 'net-pg-domain', maxlength: '40', placeholder: 'z. B. schulzeitung.net' }));
      var ta = field('HTML-Quelltext', 'textarea', isNew ? '<h1>Hallo Welt</h1>\n<p>Meine erste eigene Webseite.</p>' : page.html, { id: 'net-pg-html', rows: '10' });
      box.appendChild(ta);
      var save = el('button', 'btn net-btn');
      save.innerHTML = '<span class="icn ic-floppy-disk" aria-hidden="true"></span> Speichern';
      save.addEventListener('click', function () {
        var tEl = doc.getElementById('net-pg-title');
        var dEl = doc.getElementById('net-pg-domain');
        var hEl = doc.getElementById('net-pg-html');
        var params = {
          server: d.id,
          title: tEl ? tEl.value : '',
          domain: dEl ? dEl.value : '',
          html: hEl ? hEl.value : ''
        };
        if (!isNew) { params.id = page.id; }
        act('savePage', params, function (res) {
          if (res && res.ok) { saveFlag('own-page'); }
        });
      });
      box.appendChild(save);
      var back = el('button', 'btn net-btn net-btn-ghost');
      back.innerHTML = '<span class="icn ic-arrow-left" aria-hidden="true"></span> Zurück zum Webserver';
      back.addEventListener('click', function () { openModal(d.id, 'webserver'); });
      box.appendChild(back);
      if (modalBox) {
        while (modalBox.firstChild) { modalBox.removeChild(modalBox.firstChild); }
        modalBox.appendChild(box);
        modal.removeAttribute('hidden');
      }
    }

    /* ---------- DNS-Fenster (#223) ---------- */

    function dnsWindow(d) {
      var box = el('div', null);
      box.appendChild(head(d.name, d.ip + ' · Domain-Tabelle', 'ic-compass'));
      box.appendChild(zoneToggle(d));
      box.appendChild(el('p', 'net-muted', 'Der DNS-Server übersetzt Domainnamen in IP-Adressen. Beim Freischalten einer Webseite erscheint sie hier automatisch.'));
      for (var i = 0; i < net.dns.length; i++) {
        (function (entry) {
          var row = el('div', 'net-pagerow');
          row.appendChild(el('strong', null, entry.domain));
          row.appendChild(el('span', 'net-muted', '→ ' + entry.ip));
          var del = el('button', 'btn net-btn-sm net-danger');
          del.innerHTML = '<span class="icn ic-bin2" aria-hidden="true"></span>';
          del.addEventListener('click', function () { act('deleteDns', { id: entry.id }); });
          row.appendChild(del);
          box.appendChild(row);
        })(net.dns[i]);
      }
      if (net.dns.length === 0) { box.appendChild(el('p', 'net-muted', 'Die Tabelle ist leer.')); }
      box.appendChild(field('Domain', 'input', '', { id: 'net-dns-domain', maxlength: '40', placeholder: 'z. B. schulzeitung.net' }));
      box.appendChild(field('IP-Adresse', 'input', '', { id: 'net-dns-ip', maxlength: '15', placeholder: 'z. B. 192.168.0.2' }));
      var add = el('button', 'btn net-btn');
      add.innerHTML = '<span class="icn ic-plus" aria-hidden="true"></span> Eintrag hinzufügen';
      add.addEventListener('click', function () {
        var dEl = doc.getElementById('net-dns-domain');
        var iEl = doc.getElementById('net-dns-ip');
        act('saveDns', { domain: dEl ? dEl.value : '', ip: iEl ? iEl.value : '' });
      });
      box.appendChild(add);
      box.appendChild(deviceFooter(d));
      return box;
    }

    /* ---------- Access Point (#224) ---------- */

    function apWindow(d) {
      var box = el('div', null);
      box.appendChild(head(d.name, d.ip + ' · WLAN-Zugangspunkt', 'ic-connection'));
      box.appendChild(field('WLAN-Name (SSID)', 'input', d.name, { id: 'net-ap-ssid', maxlength: '32' }));
      box.appendChild(field('WLAN-Passwort', 'input', d.pass || '', { id: 'net-ap-pass', maxlength: '32' }));
      var save = el('button', 'btn net-btn');
      save.innerHTML = '<span class="icn ic-floppy-disk" aria-hidden="true"></span> Speichern';
      save.addEventListener('click', function () {
        var sEl = doc.getElementById('net-ap-ssid');
        var pEl = doc.getElementById('net-ap-pass');
        act('setAp', { id: d.id, name: sEl ? sEl.value : '', pass: pEl ? pEl.value : '' });
      });
      box.appendChild(save);
      box.appendChild(deviceFooter(d));
      return box;
    }

    /* ---------- Drucker: Kabel oder WLAN (#231) ---------- */

    function printerWindow(d) {
      var box = el('div', null);
      box.appendChild(head(d.name, d.ip + ' · Drucker' + (online(d) ? '' : ' · offline'), 'ic-printer'));
      box.appendChild(el('p', 'net-muted', 'Ein Drucker ist flexibel: klassisch per Kabel am Router oder drahtlos per WLAN.'));
      var cableBtn = el('button', 'btn net-btn');
      cableBtn.innerHTML = d.cable === false ? '<span class="icn ic-power-cord" aria-hidden="true"></span> Kabel anschließen' : '<span class="icn ic-scissors" aria-hidden="true"></span> Kabel trennen (nur noch WLAN)';
      cableBtn.addEventListener('click', function () {
        act('setCable', { id: d.id, cable: d.cable === false ? '1' : '0' });
      });
      box.appendChild(cableBtn);
      if (!d.wifi) {
        var ssids = wlanSsids(net);
        var sel = el('select', 'net-input');
        sel.setAttribute('id', 'net-pr-wlan');
        if (ssids.length === 0) {
          var o = el('option', null, 'Kein WLAN eingerichtet');
          o.setAttribute('value', '');
          sel.appendChild(o);
        }
        for (var s = 0; s < ssids.length; s++) {
          var opt = el('option', null, ssids[s]);
          opt.setAttribute('value', ssids[s]);
          sel.appendChild(opt);
        }
        var wrap = el('label', 'net-field');
        wrap.appendChild(el('span', null, 'WLAN (statt oder zusätzlich zum Kabel)'));
        wrap.appendChild(sel);
        box.appendChild(wrap);
        box.appendChild(field('Passwort', 'input', '', { id: 'net-pr-pass', type: 'password' }));
        var join = el('button', 'btn net-btn');
        join.innerHTML = '<span class="icn ic-connection" aria-hidden="true"></span> Ins WLAN verbinden';
        join.addEventListener('click', function () {
          var sEl = doc.getElementById('net-pr-wlan');
          var pEl = doc.getElementById('net-pr-pass');
          act('joinWlan', { id: d.id, ssid: sEl ? sEl.value : '', pass: pEl ? pEl.value : '' });
        });
        box.appendChild(join);
      } else {
        box.appendChild(el('p', 'net-muted', 'im WLAN "' + (d.ssid || '') + '"'));
      }
      box.appendChild(field('Name', 'input', d.name, { id: 'net-ren-name', maxlength: '40' }));
      var ren = el('button', 'btn net-btn');
      ren.innerHTML = '<span class="icn ic-pencil" aria-hidden="true"></span> Umbenennen';
      ren.addEventListener('click', function () {
        var nEl = doc.getElementById('net-ren-name');
        act('renameDevice', { id: d.id, name: nEl ? nEl.value : '' });
      });
      box.appendChild(ren);
      box.appendChild(deviceFooter(d));
      return box;
    }

    /* ---------- Einfache Geräte (Umbenennen) ---------- */

    function simpleWindow(d) {
      var box = el('div', null);
      box.appendChild(head(d.name, d.ip + ' · ' + (TYPES[d.type] ? TYPES[d.type].label : 'Gerät'), TYPES[d.type] ? TYPES[d.type].ic : 'ic-question'));
      box.appendChild(el('p', 'net-muted', 'Dieses Gerät hängt am Router und ist im Netz erreichbar.'));
      box.appendChild(field('Name', 'input', d.name, { id: 'net-ren-name', maxlength: '40' }));
      var ren = el('button', 'btn net-btn');
      ren.innerHTML = '<span class="icn ic-pencil" aria-hidden="true"></span> Umbenennen';
      ren.addEventListener('click', function () {
        var nEl = doc.getElementById('net-ren-name');
        act('renameDevice', { id: d.id, name: nEl ? nEl.value : '' });
      });
      box.appendChild(ren);
      box.appendChild(deviceFooter(d));
      return box;
    }

    /* ---------- Ereignisse der Seite ---------- */

    var addBtn = byId('net-add-btn');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        var tEl = byId('net-add-type');
        var nEl = byId('net-add-name');
        act('addDevice', { type: tEl ? tEl.value : 'pc', name: nEl ? nEl.value : '' }, function (res) {
          if (res && res.ok && nEl) { nEl.value = ''; }
        });
      });
    }

    /* PNG-Export der Topologie (#244): SVG klonen, Styles einbetten, ueber
     * Canvas in ein PNG wandeln und als Download anbieten. */
    function exportPNG() {
      if (!svg) { return; }
      var XMLSer = win().XMLSerializer;
      var Img = win().Image;
      if (!XMLSer || !Img || !doc.createElement) { return; }
      var clone = svg.cloneNode(true);
      if (!clone) { return; }
      try {
        var icons = clone.querySelectorAll ? clone.querySelectorAll('.icn') : [];
        for (var q = 0; q < icons.length; q++) { if (icons[q].parentNode) { icons[q].parentNode.removeChild(icons[q]); } }
        var styleEl = svgEl('style');
        styleEl.textContent = NET_PNG_CSS;
        if (clone.insertBefore) { clone.insertBefore(styleEl, clone.firstChild); }
        clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        clone.setAttribute('width', '1800');
        clone.setAttribute('height', '1120');
        var xml = new XMLSer().serializeToString(clone);
        var canv = doc.createElement('canvas');
        if (!canv.getContext) { return; }
        canv.width = 1800;
        canv.height = 1120;
        var ctx = canv.getContext('2d');
        var img = new Img();
        img.onload = function () {
          ctx.fillStyle = '#f8fafc';
          ctx.fillRect(0, 0, 1800, 1120);
          ctx.drawImage(img, 0, 0, 1800, 1120);
          var a = doc.createElement('a');
          a.download = 'netzwerk-topologie.png';
          a.href = canv.toDataURL('image/png');
          if (a.click) { a.click(); }
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
      } catch (e) { /* Export ist Zusaetzkuchen, kein Fehler fuer die Werkstatt */ }
    }
    var pngBtn = byId('net-png-btn');
    if (pngBtn) { pngBtn.addEventListener('click', exportPNG); }

    /* Topologie drucken (#244): Druck-CSS blendet alles andere aus. */
    var printBtn = byId('net-print-btn');
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        if (win().print) { win().print(); }
      });
    }
    // Stufen-Schalter der Lehrkraft (#232)
    var lvl1 = byId('net-level-1');
    var lvl2 = byId('net-level-2');
    if (lvl1) {
      lvl1.addEventListener('click', function () { act('setLevel', { level: '1' }); });
    }
    if (lvl2) {
      lvl2.addEventListener('click', function () { act('setLevel', { level: '2' }); });
    }
    function renderLevel() {
      var lvl = (net.level || 1) === 2 ? 2 : 1;
      var l1 = byId('net-level-1');
      var l2 = byId('net-level-2');
      var badge = byId('net-level-badge');
      if (l1) { l1.setAttribute('class', 'btn net-level-btn' + (lvl === 1 ? ' net-level-active' : '')); }
      if (l2) { l2.setAttribute('class', 'btn net-level-btn' + (lvl === 2 ? ' net-level-active' : '')); }
      if (badge) { badge.textContent = lvl === 2 ? 'Stufe 2 · Schichtenansicht (Oberstufe)' : 'Stufe 1 · Basis'; }
    }
    if (modal) {
      modal.addEventListener('click', function (ev) {
        if (ev.target === modal) { closeModal(); }
      });
    }
    var resetBtn = byId('net-reset-btn');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        if (win().confirm && win().confirm('Das komplette Netz zurücksetzen? Alle Geräte und Webseiten weg.')) {
          act('reset', {});
        }
      });
    }
    var importBtn = byId('net-import-btn');
    var importFile = byId('net-import-file');
    var importForm = byId('net-import-form');
    if (importBtn && importFile) {
      importBtn.addEventListener('click', function () { importFile.click(); });
      importFile.addEventListener('change', function () {
        var f = importFile.files && importFile.files[0];
        if (!f) { return; }
        if (typeof FileReader === 'undefined' || !f) { return; }
        try {
          var fr = new FileReader();
          fr.onload = function () {
            var params = byId('net-import-params');
            if (params) {
              params.value = JSON.stringify({ content: String(fr.result) });
              if (importForm) { importForm.submit(); }
            }
          };
          fr.readAsText(f);
        } catch (e) {}
      });
    }

    /* ---------- Polling: gemeinsames Klassennetz (#225) ---------- */

    var fetchFn = (typeof window !== 'undefined' && window.fetch) || (typeof global !== 'undefined' && global.fetch);
    var connFails = 0;
    function connState(ok) {
      var c = byId('net-conn');
      if (!c) { return; }
      if (ok) {
        if (connFails >= 2) { c.setAttribute('hidden', ''); c.textContent = ''; }
        connFails = 0;
        return;
      }
      connFails++;
      if (connFails >= 2) {
        c.removeAttribute('hidden');
        c.textContent = 'Verbindung zum Server verloren. Neue St';
        c.textContent = c.textContent + String.fromCharCode(228) + 'nde werden automatisch abgeholt, sobald sie wieder da ist.';
      }
    }
    function poll() {
      if (!fetchFn) { return; }
      fetchFn(cfg.stand).then(function (r) { return r.json(); }).then(function (data) {
        if (!data || !data.devices) { connState(false); return; }
        connState(true);
        var fresh = saneNet(data); // (#233)
        var raw = JSON.stringify(fresh);
        // Nichts Neues? Dann gar nichts anfassen: kein Neuaufbau, der
        // Eingaben und Fokus im Browser oder in Geräte-Fenstern zerstört (#236).
        if (raw === lastStand) { return; }
        lastStand = raw;
        net = fresh;
        render({ modal: false });
      }).catch(function () { connState(false); });
    }
    var win1 = win();
    if (win1.setInterval) { win1.setInterval(poll, 5000); }

    render();
  }

  /* =================== Export =================== */

  var api = {
    version: '0.37.0',
    defaultNet: defaultNet,
    resolve: resolve,
    dnsLookup: dnsLookup,
    isIp: isIp,
    wlanSsids: wlanSsids,
    hasDns: hasDns,
    pageDocument: pageDocument,
    missionsState: missionsState,
    saneNet: saneNet,
    cableD: cableD,
    NET_PNG_CSS: NET_PNG_CSS,
    online: online,
    layerTrace: layerTrace,
    LAYERS: LAYERS,
    EXAMPLES: EXAMPLES,
    MISSIONS: MISSIONS,
    GRADS: GRADS,
    NEWSPAPER: NEWSPAPER,
    TYPES: TYPES,
    messagesFor: messagesFor,
    messengerReady: messengerReady,
    routeHops: routeHops,
    attach: attach
  };
  if (typeof window !== 'undefined') { window.IC_NET = api; }
  if (typeof module !== 'undefined' && module.exports) { module.exports = api; }

  /* Selbststart in der Werkstatt-Seite (#234): Das Template bindet nur das
   * net-boot-Daten-Element und diese Datei, app.js kennt IC_NET nicht.
   * attach() muss sich hier selbst ans Dokument hängen, sonst rendert die
   * Werkstatt im Browser nichts. In Node (ui-tests) gibt es kein
   * window.document, attach() bleibt dort der manuelle Aufruf. */
  if (typeof window !== 'undefined' && window.document && window.document.getElementById) {
    attach(window.document);
  }
})();
