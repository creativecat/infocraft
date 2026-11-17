/**
 * InfoCraft - offene Lernplattform für Informatik
 * Datei: Netzwerk-Werkstatt (#220 bis #226)
 *
 * Simuliertes Netz im Browser: Topologie als SVG, Router-Fenster,
 * Geräte (Computer, Smartphone, Drucker, Webserver, DNS-Server,
 * Access Point), Browser mit DNS-Auflösung und Fehlermeldungen,
 * Webseiten mit eigenem HTML, Missionen für die 7. Jahrgangsstufe.
 * Die Engine ist DOM-frei (Node-Tests), attach() baut die Oberfläche.
 *
 * Node-Tests: module.exports; Browser: window.IC_NET.
 *
 * @version 0.30.0
 * @license CC BY-NC-SA 4.0 (InfoCraft)
 */
(function () {
  'use strict';

  /* =================== Konstanten =================== */

  var TYPES = {
    pc: { icon: '💻', label: 'Computer' },
    phone: { icon: '📱', label: 'Smartphone' },
    printer: { icon: '🖨️', label: 'Drucker' },
    webserver: { icon: '🖥️', label: 'Webserver' },
    dns: { icon: '🧭', label: 'DNS-Server' },
    ap: { icon: '📶', label: 'Access Point' }
  };

  var NEWSPAPER = '<h1>Schulzeitung</h1>'
    + '<p>Die Zeitung der Klasse: Hier schreiben wir über alles, was in der Schule passiert.</p>'
    + '<h2>Ausgabe 1</h2>'
    + '<ul><li>Sportfest: Die 7. Klasse gewinnt den Staffelpokal</li>'
    + '<li>Neuer Informatikraum: 25 Rechner ans Netz</li>'
    + '<li>Wetterbericht: Morgen sonnig</li></ul>';

  /* Beispielseiten für den Webserver (#229): Schlüssel wie im PHP-Katalog. */
  var EXAMPLES = [
    { key: 'zeitung', icon: '📰', title: 'Schulzeitung' },
    { key: 'sport', icon: '⚽', title: 'Sportclub Eichenwald' },
    { key: 'wetter', icon: '🌤️', title: 'Wetterdienst Schulnet' },
    { key: 'kochen', icon: '🍝', title: 'Kochblog Klassenküche' }
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
    return { v: 1, level: 1, router: { name: 'Router', ssid: '', pass: '' }, devices: [], pages: [], dns: [], logs: [], htmlProjects: [] };
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
    var lists = ['devices', 'pages', 'dns', 'logs', 'htmlProjects'];
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

  var MISSIONS = [
    { id: 'm1', title: 'Computer anschließen', text: 'Hänge deinen ersten Computer an den Router.', check: function (net) { return net.devices.some(function (d) { return d.type === 'pc'; }); } },
    { id: 'm2', title: 'Erster Seitenaufruf', text: 'Öffne am Computer den Browser und rufe eine Seite über die IP-Adresse auf.', flag: 'browse-ip' },
    { id: 'm3', title: 'WLAN einrichten', text: 'Richte am Router ein WLAN mit Namen und Passwort ein.', check: function (net) { return !!(net.router && net.router.ssid); } },
    { id: 'm4', title: 'Smartphone online', text: 'Bring dein Smartphone mit dem WLAN-Passwort ins Netz.', check: function (net) { return net.devices.some(function (d) { return d.type === 'phone' && d.wifi; }); } },
    { id: 'm5', title: 'Schulzeitung frei', text: 'Hänge einen Webserver an, lege die Vorlage Schulzeitung an und schalte sie frei.', check: function (net) { return net.pages.some(function (p) { return p.published; }); } },
    { id: 'm6', title: 'Per Domain surfen', text: 'Hänge einen DNS-Server an und rufe die Schulzeitung über ihren Domainnamen auf.', flag: 'browse-domain' },
    { id: 'm7', title: 'Drucker im Netz', text: 'Schließe einen Drucker an den Router an.', check: function (net) { return net.devices.some(function (d) { return d.type === 'printer'; }); } },
    { id: 'm8', title: 'Eigene Webseite', text: 'Baue auf dem Webserver eine eigene Seite mit deinem HTML.', flag: 'own-page' }
  ];

  function missionsState(net, flags) {
    var out = [];
    for (var i = 0; i < MISSIONS.length; i++) {
      var m = MISSIONS[i];
      var done = m.flag ? flags.indexOf(m.flag) >= 0 : (m.check ? !!m.check(net) : false);
      out.push({ id: m.id, title: m.title, text: m.text, done: done });
    }
    return out;
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
        if (data && data.net) { net = saneNet(data.net); } // (#233)
        if (cb) { cb(data || null); }
        render();
      }).catch(function () { if (cb) { cb(null); } });
    }

    /* ---------- Topologie ---------- */

    function render() {
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
        else if (typeof openKind === 'string') { openModal(openId, openKind, true); }
      }
    }

    var openKind = null;

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

      // Router in der Mitte
      var g = svgEl('g', {});
      g.appendChild(svgEl('rect', { x: cx - 70, y: cy - 26, rx: 10, width: 140, height: 52, class: 'net-router-box' }));
      var label = svgEl('text', { x: cx, y: cy + 6, class: 'net-router-text', 'text-anchor': 'middle' });
      label.textContent = '📡 ' + (net.router.name || 'Router');
      g.appendChild(label);
      var ipText = svgEl('text', { x: cx, y: cy + 42, class: 'net-sub', 'text-anchor': 'middle' });
      ipText.textContent = '192.168.0.1';
      g.appendChild(ipText);
      g.appendChild(svgEl('title', {}));
      (function (routerG) {
        if (routerG.addEventListener) { routerG.addEventListener('click', function () { openModal('router', 'router'); }); }
      })(g);
      svg.appendChild(g);

      for (var i = 0; i < n; i++) {
        var d = net.devices[i];
        var angle = (-90 + i * (360 / Math.max(n, 1))) * Math.PI / 180;
        var dx = cx + Math.cos(angle) * 250;
        var dy = cy + Math.sin(angle) * 185;
        var on = online(d);
        var line = svgEl('line', { x1: cx, y1: cy, x2: dx, y2: dy, class: on ? 'net-link' : 'net-link net-link-off' });
        svg.appendChild(line);
        var gg = svgEl('g', { class: 'net-device' + (on ? '' : ' net-device-off') });
        gg.appendChild(svgEl('rect', { x: dx - 62, y: dy - 24, rx: 10, width: 124, height: 68 }));
        var t1 = svgEl('text', { x: dx, y: dy - 2, class: 'net-dev-text', 'text-anchor': 'middle' });
        t1.textContent = (TYPES[d.type] ? TYPES[d.type].icon : '❓') + ' ' + d.name;
        gg.appendChild(t1);
        var t2 = svgEl('text', { x: dx, y: dy + 16, class: 'net-sub', 'text-anchor': 'middle' });
        var state = d.wifi ? ' · im WLAN ' + (d.ssid || '') : (d.cable === false ? ' · offline' : (d.type === 'phone' ? ' · kein WLAN' : ' · per Kabel'));
        t2.textContent = d.ip + state;
        gg.appendChild(t2);
        var t3 = svgEl('text', { x: dx, y: dy + 32, class: 'net-sub net-sub-dim', 'text-anchor': 'middle' });
        t3.textContent = TYPES[d.type] ? TYPES[d.type].label : '';
        gg.appendChild(t3);
        (function (dev, devG) {
          if (devG.addEventListener) { devG.addEventListener('click', function () { openModal(dev.id, dev.type); }); }
        })(d, gg);
        svg.appendChild(gg);
      }
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
        li.textContent = (state[i].done ? '✅ ' : '⬜ ') + state[i].title + ': ' + state[i].text;
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

    function closeModal() {
      openId = null;
      openKind = null;
      if (modal) { modal.setAttribute('hidden', ''); }
    }

    function head(title, sub) {
      var box = el('div', 'net-modal-head');
      box.appendChild(el('strong', null, title));
      if (sub) { box.appendChild(el('span', 'net-muted', sub)); }
      var x = el('button', 'btn net-close');
      x.textContent = '✕';
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

    function openModal(id, kind, isRefresh) {
      openId = id;
      openKind = kind;
      if (!modal || !modalBox) { return; }
      if (!isRefresh) { modal.removeAttribute('hidden'); }
      while (modalBox.firstChild) { modalBox.removeChild(modalBox.firstChild); }

      if (id === 'router') {
        modalBox.appendChild(routerWindow());
        return;
      }
      var d = findDevice(net, id);
      if (d === null) { closeModal(); return; }
      if (d.type === 'pc' || d.type === 'phone') { modalBox.appendChild(browserWindow(d)); return; }
      if (d.type === 'webserver') { modalBox.appendChild(webserverWindow(d)); return; }
      if (d.type === 'dns') { modalBox.appendChild(dnsWindow(d)); return; }
      if (d.type === 'ap') { modalBox.appendChild(apWindow(d)); return; }
      if (d.type === 'printer') { modalBox.appendChild(printerWindow(d)); return; }
      modalBox.appendChild(simpleWindow(d));
    }

    /* ---------- Router-Fenster ---------- */

    function routerWindow() {
      var box = el('div', null);
      box.appendChild(head('📡 ' + (net.router.name || 'Router'), '192.168.0.1 · Verwaltungsoberfläche'));
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
      save.textContent = '💾 WLAN speichern';
      save.addEventListener('click', function () {
        var s = doc.getElementById('net-w-ssid');
        var p = doc.getElementById('net-w-pass');
        act('setWlan', { ssid: s ? s.value : '', pass: p ? p.value : '' }, function (res) {
          if (res && res.ok) { saveFlag('wlan-set'); }
        });
      });
      box.appendChild(save);
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
      var box = el('div', null);
      box.appendChild(head((d.type === 'phone' ? '📱 ' : '💻 ') + d.name, d.ip + (d.wifi ? ' · im WLAN ' + (d.ssid || '') : '')));

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
        join.textContent = '📶 Verbinden';
        join.addEventListener('click', function () {
          var sEl = doc.getElementById('net-wlan-sel');
          var pEl = doc.getElementById('net-wlan-pass');
          act('joinWlan', { id: d.id, ssid: sEl ? sEl.value : '', pass: pEl ? pEl.value : '' });
        });
        box.appendChild(join);
        return box;
      }

      var bar = el('div', 'net-urlbar');
      var input = el('input', 'net-input net-url');
      input.setAttribute('id', 'net-url-in');
      input.setAttribute('placeholder', '192.168.0.2 oder schulzeitung.net');
      var go = el('button', 'btn net-btn');
      go.textContent = '➡︎ Öffnen';
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
        printBtn.textContent = '🖨️ Auf ' + printers[0].name + ' drucken';
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
        layersBox.appendChild(el('summary', null, '🧅 Schichtenansicht (OSI-Modell)'));
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
            + (res.viaDns ? ' · über DNS aufgelöst' : ' · direkt über die IP');
          saveFlag(isIp(String(url).trim().replace(/^https?:\/\//i, '')) ? 'browse-ip' : 'browse-domain');
          act('browse', { who: d.name, url: String(url).trim() });
        } else {
          var err = el('div', 'net-error');
          err.textContent = '⚠️ ' + res.message;
          view.appendChild(err);
          status.textContent = 'Fehler · Code ' + res.code;
        }
        renderLayers();
      }
      go.addEventListener('click', load);
      bar.appendChild(input);
      bar.appendChild(go);
      box.appendChild(bar);
      box.appendChild(status);
      box.appendChild(view);
      return box;
    }

    /* ---------- Webserver-Fenster (#222) ---------- */

    function webserverWindow(d) {
      var box = el('div', null);
      box.appendChild(head('🖥️ ' + d.name, d.ip + ' · Webseiten verwalten'));
      box.appendChild(el('p', 'net-muted', 'Webseiten für: ' + d.name + '. Bearbeite den HTML-Quelltext und schalte die Seite frei, damit sie über die IP oder Domain erreichbar ist.'));
      var mine = [];
      for (var i = 0; i < net.pages.length; i++) {
        if (net.pages[i].server === d.id) { mine.push(net.pages[i]); }
      }
      for (var p = 0; p < mine.length; p++) {
        (function (page) {
          var row = el('div', 'net-pagerow');
          row.appendChild(el('strong', null, page.title + (page.published ? ' 🟢' : ' ⚪')));
          row.appendChild(el('span', 'net-muted', page.domain ? page.domain : '(nur über IP)'));
          var pub = el('button', 'btn net-btn-sm');
          pub.textContent = page.published ? '🏁 Sperren' : '🟢 Freischalten';
          pub.addEventListener('click', function () {
            act(page.published ? 'unpublishPage' : 'publishPage', { id: page.id });
          });
          var edit = el('button', 'btn net-btn-sm');
          edit.textContent = '✏️ Bearbeiten';
          edit.addEventListener('click', function () { pageEditor(d, page); });
          var del = el('button', 'btn net-btn-sm net-danger');
          del.textContent = '🗑️';
          del.addEventListener('click', function () { act('deletePage', { id: page.id }); });
          var toHtmlBtn = el('button', 'btn net-btn-sm');
          toHtmlBtn.textContent = '🧱 → HTML-Werkstatt';
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
          b.textContent = example.icon + ' ' + example.title;
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
        loadHtml.textContent = '📥 Auf den Webserver laden';
        loadHtml.addEventListener('click', function () {
          var pEl = doc.getElementById('net-html-sel');
          act('fromHtml', { server: d.id, projectId: pEl ? pEl.value : 0 });
        });
        box.appendChild(loadHtml);
      } else {
        box.appendChild(el('p', 'net-muted', 'Du hast noch keine HTML-Projekte. Lege eines in der HTML-Werkstatt an, dann erscheint es hier.'));
      }

      var add = el('button', 'btn net-btn');
      add.textContent = '＋ Neue Webseite (Vorlage Schulzeitung)';
      add.addEventListener('click', function () {
        act('savePage', { server: d.id, title: 'Schulzeitung', domain: 'schulzeitung.net', html: NEWSPAPER }, function (res) {
          if (res && res.ok) { saveFlag('page-created'); }
        });
      });
      box.appendChild(add);
      var own = el('button', 'btn net-btn');
      own.textContent = '＋ Leere Seite mit eigenem HTML';
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
      return box;
    }

    function pageEditor(d, page) {
      var box = el('div', null);
      var isNew = page === null;
      box.appendChild(head(isNew ? '✏️ Neue Webseite' : '✏️ ' + page.title, 'Quelltext bearbeiten'));
      box.appendChild(field('Titel', 'input', isNew ? 'Meine Seite' : page.title, { id: 'net-pg-title', maxlength: '60' }));
      box.appendChild(field('Domain (für DNS)', 'input', isNew ? '' : page.domain, { id: 'net-pg-domain', maxlength: '40', placeholder: 'z. B. schulzeitung.net' }));
      var ta = field('HTML-Quelltext', 'textarea', isNew ? '<h1>Hallo Welt</h1>\n<p>Meine erste eigene Webseite.</p>' : page.html, { id: 'net-pg-html', rows: '10' });
      box.appendChild(ta);
      var save = el('button', 'btn net-btn');
      save.textContent = '💾 Speichern';
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
      back.textContent = '← Zurück zum Webserver';
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
      box.appendChild(head('🧭 ' + d.name, d.ip + ' · Domain-Tabelle'));
      box.appendChild(el('p', 'net-muted', 'Der DNS-Server übersetzt Domainnamen in IP-Adressen. Beim Freischalten einer Webseite erscheint sie hier automatisch.'));
      for (var i = 0; i < net.dns.length; i++) {
        (function (entry) {
          var row = el('div', 'net-pagerow');
          row.appendChild(el('strong', null, entry.domain));
          row.appendChild(el('span', 'net-muted', '→ ' + entry.ip));
          var del = el('button', 'btn net-btn-sm net-danger');
          del.textContent = '🗑️';
          del.addEventListener('click', function () { act('deleteDns', { id: entry.id }); });
          row.appendChild(del);
          box.appendChild(row);
        })(net.dns[i]);
      }
      if (net.dns.length === 0) { box.appendChild(el('p', 'net-muted', 'Die Tabelle ist leer.')); }
      box.appendChild(field('Domain', 'input', '', { id: 'net-dns-domain', maxlength: '40', placeholder: 'z. B. schulzeitung.net' }));
      box.appendChild(field('IP-Adresse', 'input', '', { id: 'net-dns-ip', maxlength: '15', placeholder: 'z. B. 192.168.0.2' }));
      var add = el('button', 'btn net-btn');
      add.textContent = '＋ Eintrag hinzufügen';
      add.addEventListener('click', function () {
        var dEl = doc.getElementById('net-dns-domain');
        var iEl = doc.getElementById('net-dns-ip');
        act('saveDns', { domain: dEl ? dEl.value : '', ip: iEl ? iEl.value : '' });
      });
      box.appendChild(add);
      return box;
    }

    /* ---------- Access Point (#224) ---------- */

    function apWindow(d) {
      var box = el('div', null);
      box.appendChild(head('📶 ' + d.name, d.ip + ' · WLAN-Zugangspunkt'));
      box.appendChild(field('WLAN-Name (SSID)', 'input', d.name, { id: 'net-ap-ssid', maxlength: '32' }));
      box.appendChild(field('WLAN-Passwort', 'input', d.pass || '', { id: 'net-ap-pass', maxlength: '32' }));
      var save = el('button', 'btn net-btn');
      save.textContent = '💾 Speichern';
      save.addEventListener('click', function () {
        var sEl = doc.getElementById('net-ap-ssid');
        var pEl = doc.getElementById('net-ap-pass');
        act('setAp', { id: d.id, name: sEl ? sEl.value : '', pass: pEl ? pEl.value : '' });
      });
      box.appendChild(save);
      return box;
    }

    /* ---------- Drucker: Kabel oder WLAN (#231) ---------- */

    function printerWindow(d) {
      var box = el('div', null);
      box.appendChild(head('🖨️ ' + d.name, d.ip + ' · Drucker' + (online(d) ? '' : ' · offline')));
      box.appendChild(el('p', 'net-muted', 'Ein Drucker ist flexibel: klassisch per Kabel am Router oder drahtlos per WLAN.'));
      var cableBtn = el('button', 'btn net-btn');
      cableBtn.textContent = d.cable === false ? '🔌 Kabel anschließen' : '✂️ Kabel trennen (nur noch WLAN)';
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
        join.textContent = '📶 Ins WLAN verbinden';
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
      ren.textContent = '💾 Umbenennen';
      ren.addEventListener('click', function () {
        var nEl = doc.getElementById('net-ren-name');
        act('renameDevice', { id: d.id, name: nEl ? nEl.value : '' });
      });
      box.appendChild(ren);
      var del = el('button', 'btn net-btn net-danger');
      del.textContent = '🗑️ Gerät entfernen';
      del.addEventListener('click', function () { act('removeDevice', { id: d.id }); closeModal(); });
      box.appendChild(del);
      return box;
    }

    /* ---------- Einfache Geräte (Umbenennen) ---------- */

    function simpleWindow(d) {
      var box = el('div', null);
      box.appendChild(head((TYPES[d.type] ? TYPES[d.type].icon : '❓') + ' ' + d.name, d.ip + ' · ' + (TYPES[d.type] ? TYPES[d.type].label : 'Gerät')));
      box.appendChild(el('p', 'net-muted', 'Dieses Gerät hängt am Router und ist im Netz erreichbar.'));
      box.appendChild(field('Name', 'input', d.name, { id: 'net-ren-name', maxlength: '40' }));
      var ren = el('button', 'btn net-btn');
      ren.textContent = '💾 Umbenennen';
      ren.addEventListener('click', function () {
        var nEl = doc.getElementById('net-ren-name');
        act('renameDevice', { id: d.id, name: nEl ? nEl.value : '' });
      });
      box.appendChild(ren);
      var del = el('button', 'btn net-btn net-danger');
      del.textContent = '🗑️ Gerät entfernen';
      del.addEventListener('click', function () { act('removeDevice', { id: d.id }); closeModal(); });
      box.appendChild(del);
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
    function poll() {
      if (!fetchFn) { return; }
      fetchFn(cfg.stand).then(function (r) { return r.json(); }).then(function (data) {
        if (data && data.devices) { net = saneNet(data); render(); } // (#233)
      }).catch(function () {});
    }
    var win1 = win();
    if (win1.setInterval) { win1.setInterval(poll, 5000); }

    render();
  }

  /* =================== Export =================== */

  var api = {
    version: '0.30.0',
    defaultNet: defaultNet,
    resolve: resolve,
    dnsLookup: dnsLookup,
    isIp: isIp,
    wlanSsids: wlanSsids,
    hasDns: hasDns,
    pageDocument: pageDocument,
    missionsState: missionsState,
    saneNet: saneNet,
    online: online,
    layerTrace: layerTrace,
    LAYERS: LAYERS,
    EXAMPLES: EXAMPLES,
    MISSIONS: MISSIONS,
    NEWSPAPER: NEWSPAPER,
    TYPES: TYPES,
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
