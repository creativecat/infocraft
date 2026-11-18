# 🔐 Microsoft-365-SSO (Single Sign-On)

InfoCraft meldet Nutzerinnen und Nutzer über den Schulkonto-Anmeldedienst (Microsoft Entra ID, früher Azure AD) an. Datenminimierung: gespeichert werden nur Vorname, Nachname, Rolle und die Microsoft-Objekt-ID.

## Schritt für Schritt

1. **App-Registrierung anlegen**: portal.azure.com oder entra.microsoft.com mit dem Schul-Administratorkonto öffnen, dann *Identität* > *Anwendungen* > *App-Registrierungen* > *Neue Registrierung*. Name z. B. "InfoCraft", unterstützte Kontotypen: "Nur Konten in diesem Organisationsverzeichnis".
2. **Umleitungs-URI** hinterlegen (Plattform "Web"), z. B. `https://informatik.schule.de/auth/sso/callback` beziehungsweise lokal `http://localhost:8080/auth/sso/callback`.
3. **Geheimen Clientschlüssel** erstellen: *Zertifikate und Geheimnisse* > *Neuer geheimer Clientschlüssel*, den Wert sofort kopieren.
4. **Mandanten-ID und Anwendungs-ID (Client)** von der Übersichtsseite der App-Registrierung notieren.
5. **config/config.php** ausfüllen: `sso.enabled = true`, `tenant_id`, `client_id`, `client_secret`, `redirect_uri` (exakt wie in Schritt 2), `auto_create` nach Wunsch (Standard: true, unbekannte Schulkonten werden als Schülerin/Schüler angelegt).
6. Auf der Anmeldeseite erscheint jetzt der Button "Mit Schulkonto anmelden".

## Ablauf

- Der Anmeldedialog startet den OAuth2-Authorization-Code-Flow gegen `login.microsoftonline.com`.
- Nach der Anmeldung gleicht InfoCraft die Microsoft-Objekt-ID mit bestehenden Konten ab. Per CSV vorab angelegte Nutzerinnen und Nutzer werden beim ersten Login über Vor- und Nachnamen verknüpft.
- Passwörter verbleiben allein bei Microsoft, InfoCraft speichert keine Passwörter im SSO-Betrieb.

## Sicherheitshinweise

- Nach Abschluss der Testphase `app.demo_mode` auf `false` setzen.
- Das `client_secret` nur in config/config.php pflegen (liegt in .gitignore), niemals ins Repository.
- Sobald HTTPS aktiv ist, in src/bootstrap.php die Cookie-Option `secure` aktivieren.
