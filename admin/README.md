# RV Hard CMS (Decap)

## Lokal testen (ohne OAuth-Setup)

1. Terminal im Projekt öffnen.
2. Lokalen Webserver starten (Projektwurzel):
   - `npx serve . -l 8080`
3. Zweites Terminal öffnen und Decap Local Backend starten:
   - `npx decap-server`
4. Im Browser öffnen:
   - Website: `http://localhost:8080`
   - CMS: `http://localhost:8080/admin/`

Hinweis: Mit `local_backend: true` kannst du lokal ohne GitHub-OAuth testen.
Für Serverbetrieb muss `local_backend: false` gesetzt sein.

## Redaktions-Workflow

- Im CMS den Eintrag `News (alle Artikel)` öffnen.
- In `Artikel` einen neuen Eintrag hinzufügen.
- Pflichtfelder: `ID`, `Titel`, `Slug`, `Datum`, `Teaser`, `Artikeltext`, `Tags`.
- `Tags` sind Mehrfachauswahl mit Vorschlägen; neue Tags können direkt eingetippt werden.
- `Hauptbild` und `Zusätzliche Bilder` werden direkt hochgeladen und automatisch unter `/assets/img/news/` gespeichert.
- Beim Speichern werden Einträge automatisch nach `ID` absteigend sortiert (neueste oben).
- Speichern und veröffentlichen.

## Produktiv (remote Login)

Für Login auf der live Seite brauchst du GitHub OAuth für Decap:

- `admin/config.yml`:
  - `backend.repo` auf dein echtes Repo
   - `backend.branch` auf den vorhandenen GitHub-Branch (`main`)
- `backend.base_url` zeigt auf eure Test-Domain (`https://rv-hard.arnovoyer.com`)
- `backend.auth_endpoint` ist auf `/api/auth` gesetzt
- OAuth-Flow für Decap einrichten (GitHub App/OAuth Proxy), z. B. mit `decap-cms-oauth-provider`
   - Provider als kleinen Server/Serverless-Funktion deployen
   - Endpoint muss unter `https://rv-hard.at/api/auth` erreichbar sein
   - GitHub OAuth App auf diese URL konfigurieren

Ohne OAuth funktioniert das CMS live nicht mit Login.

## cPanel (Spaceship) konkret

1. Dateien hochladen/committen:
   - `api/auth/index.php`
   - `api/auth/.htaccess`
   - `api/auth/config.php.example`

2. Auf dem Server Datei `api/auth/config.php` anlegen (nicht ins Repo committen):

   ```php
   <?php
   return [
       'client_id' => 'DEINE_GITHUB_CLIENT_ID',
       'client_secret' => 'DEIN_GITHUB_CLIENT_SECRET',
         'redirect_uri' => 'https://rv-hard.arnovoyer.com/api/auth',
   ];
   ```

3. GitHub OAuth App erstellen:
   - Homepage URL: `https://rv-hard.arnovoyer.com`
   - Authorization callback URL: `https://rv-hard.arnovoyer.com/api/auth`

4. Prüfen, ob Endpoint erreichbar ist:
   - `https://rv-hard.arnovoyer.com/api/auth`
   - sollte auf GitHub Login weiterleiten.

5. CMS testen:
   - `https://rv-hard.arnovoyer.com/admin/`
   - Login klicken, GitHub autorisieren, zurückkehren.

Wenn Login nicht geht, zuerst im cPanel prüfen:
- PHP aktiv, `curl` Extension aktiv
- Session-Verzeichnis beschreibbar
- HTTPS-Zertifikat gültig

Wenn das Popup schließt und kein Login erfolgt:
- Browser-Cache leeren und erneut testen
- In GitHub OAuth App muss die Callback-URL exakt stimmen: `https://rv-hard.arnovoyer.com/api/auth`
- `admin/config.yml` muss `base_url: https://rv-hard.arnovoyer.com` und `auth_endpoint: /api/auth` haben
