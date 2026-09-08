# 📸 RV Hard Foto-Galerie – Kurzanleitung

## 📂 Ordner-Struktur

```
/fotos/
├── index.html                      ← EVENTS-ÜBERSICHT (Startseite Galerie)
├── event.html                      ← SINGLE EVENT (Bilder-Ansicht mit Filter)
├── README.md                       ← DIESE ANLEITUNG
├── assets/
│   ├── galerie.css                 ← Styling (RV Hard Design-System)
│   └── galerie.js                  ← Logik (Laden, Filter, Suche, Lightbox)
└── data/
    └── events.json                 ← ALLE EVENTS + BILDER (zentrale Konfiguration!)
```

---

## 🚀 Schritt-für-Schritt: Neues Event + Bilder hinzufügen

### 1. Bilder in Google Drive / OneDrive hochladen

#### Option A: Google Drive (EMPFOHLEN – am einfachsten!)
1. **Neuer Ordner** in Google Drive anlegen (z.B. `SBS 2026 Fotos`)
2. Alle Bilder **reinkopieren**
3. Ordner freigeben:
   - Rechtsklick → `Freigeben` → `Zugriff auf Link ändern`
   - ⚠️ WICHTIG: **"Jeder mit dem Link" → Rolle "Ansehen"** (sonst sieht die Galerie nichts!)
4. **Für jedes einzelne Bild → Direkt-Link erstellen:**
   - Bild in Google Drive öffnen → Adresszeile kopieren, z.B.:
     ```
     https://drive.google.com/file/d/1ABC123defXYZ789/view?usp=drive_link
     ```
   - **Datei-ID extrahieren:** `1ABC123defXYZ789` (der Teil zwischen `/d/` und `/view`)
   - Daraus 2 URLs machen:
     ```
     THUMBNAIL (schnell!): https://drive.google.com/thumbnail?id=DATEI_ID&sz=w800
     ORIGINAL  (Download): https://drive.google.com/uc?export=view&id=DATEI_ID
     ```

#### Option B: OneDrive (Microsoft 365)
1. Bilder in OneDrive Ordner hochladen
2. Für jedes Bild:
   - Rechtsklick → `Einbetten` → Link generieren → Adresse kopieren
   - **ODER:** Bild im Browser öffnen, **Rechtsklick aufs Bild → "Bild-Adresse kopieren"** → diese URL direkt nutzen als `src` + `thumbnail`
3. ⚠️ OneDrive-Links verfallen manchmal nach Monaten – lieber Google Drive nehmen!

#### Option C: Bilder direkt ins RV Hard Repo hochladen (am stabilsten!)
1. Unter `/fotos/data/` neuen Ordner anlegen, z.B. `/fotos/data/sbs2026/`
2. Bilder + Thumbnails dorthin kopieren (Ordner später auf Server laden)
3. Relative Pfade in `events.json` verwenden:
   ```json
   "src": "/fotos/data/sbs2026/01-start.jpg",
   "thumbnail": "/fotos/data/sbs2026/01-start-thumb.jpg"
   ```

---

### 2. Event + Bilder in `events.json` eintragen

Öffne [/fotos/data/events.json](data/events.json).

#### 🎯 EVENT OBJEKT (Template zum Kopieren):
Füge **NEUES Event** **nach dem letzten Event-Objekt** ein (vergiss das Komma davor nicht!):

```json
,
{
  "id": "MEIN-EVENT-2026",
  "slug": "mein-event-2026-fotos",
  "name": "Name des Events 2026",
  "description": "Kurzbeschreibung – erscheint auf der Event-Übersicht.",
  "category": "SBS",
  "date": "2026-09-05",
  "location": "Feldkirch, Vorarlberg",
  "organizer": "RV Hard",
  "coverPhoto": "https://drive.google.com/thumbnail?id=COVER_DATEI_ID&sz=w1200",
  "externalGalleryUrl": "https://photos.google.com/share/DEIN_ALBUM_LINK",
  "tags": ["Rennrad", "Landesmeisterschaft"],
  "photos": [

    {
      "src": "https://drive.google.com/uc?export=view&id=BILD_1_ID",
      "thumbnail": "https://drive.google.com/thumbnail?id=BILD_1_ID&sz=w800",
      "title": "Kurzer Titel (optional)",
      "comment": "Längerer Kommentar – was ist auf dem Bild zu sehen? (optional)",
      "date": "2026-09-05T10:30:00+02:00",
      "photographer": "Max Mustermann Fotografie",
      "copyright": "© RV Hard 2026",

      "bibNumbers": [42, 7, 123, 99],

      "athletes": ["Max Müller", "Anna Mayer", "David Huber"],
      "tags": ["Start", "Zielsprint"]
    },

    {
      "src": "https://drive.google.com/uc?export=view&id=BILD_2_ID",
      "thumbnail": "https://drive.google.com/thumbnail?id=BILD_2_ID&sz=w800",
      "bibNumbers": [42, 12],
      "athletes": ["Max Müller", "David Huber"]
    }

  ]
}
```

#### 🚨 WICHTIG: `bibNumbers` = DIE STARTNUMMER-SUCHE!
Füge **unbedingt** bei JEDEM Foto das Array `bibNumbers` mit allen Startnummern ein, die auf dem Bild zu sehen sind! **Nur dann findet die Suchfunktion die Bilder zu einer Nummer.**

```
✅ RICHTIG:  "bibNumbers": [42, 7, 99]
❌ FALSCH:  "bibNumbers": "#42 #7 #99"    (Array mit Zahlen/Strings, kein String!)
```

---

### 3. Testen

1. Lade **`/fotos/`** Ordner auf den Webserver hoch (FTP/SFTP/WebDAV – wie restliche RV Hard Seite)
2. Öffne im Browser:
   - `https://rv-hard.at/fotos/` → **Events-Übersicht** – 3 Beispiel-Events + dein neues Event sollten sichtbar sein
   - Klick auf Event → **Single Ansicht**
   - Gib **Startnummer** ein (z.B. `42`) → **Filter funktioniert sofort!**
   - Klick auf Bild → **Lightbox öffnet sich**, Download-Button da

---

## 🔑 Wichtige Felder auf einen Blick

| Feld | Wo? | Pflicht? | Erklärung |
|---|---|---|---|
| `id` | Event | ✅ | Eindeutige ID (z.B. `sbs-2026`) |
| `category` | Event | ✅ | Für Filter: `SBS`, `Nightrace`, `Triathlon`, `MTB`, `Rennrad`, `Cyclocross`, `Verein` |
| `date` | Event | ✅ | `YYYY-MM-DD` (z.B. `2026-09-05`) |
| `coverPhoto` | Event | ⭐ | Bild auf Events-Übersicht |
| `src` | Foto | ✅ | **Original-Bild URL** (Download) |
| `thumbnail` | Foto | ⭐ | **Kleine Version** (schnellere Ladezeit!) |
| **`bibNumbers`** | Foto | 🏆⭐ | **Array mit Startnummern – ERMÖGLICHT SUCHE!** |
| `athletes` | Foto | ⭐ | Array mit Namen (`["Max Müller", "Anna Mayer"]`) |

---

## 🆘 Häufige Probleme

### ❌ Bilder werden nicht angezeigt?
- ✅ Google Drive Freigabe: **"Jeder mit dem Link" → Ansehen** (sonst 403-Fehler!)
- ✅ URL getestet: Kopiere `src`-URL in Browser-Adresszeile – öffnet sich das Bild?
- ✅ `src` vs `thumbnail`: bei Google Drive: `uc?export=view` für Original, `thumbnail?id=` für klein

### ❌ Suche nach Startnummer findet nichts?
- ✅ `bibNumbers` ist **Array**: `"bibNumbers": [42, 7]` und **kein String!**
- ✅ Nummer als Zahl ODER String funktioniert (`[42]` oder `["42"]`)

### ❌ Galerie Seite "Event nicht gefunden"?
- ✅ URL Parameter `?id=EVENT_ID` stimmt mit `id` in JSON überein?
- ✅ Keine Tippfehler (Case-Sensitive!)

---

## 🎉 Fertig!

Deine Foto-Galerie ist betriebsbereit. **Viel Spaß beim Hochladen und Finden der Bilder!** 🚀
