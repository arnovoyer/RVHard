# 📸 SUMMER BIKE SERIES – FOTO-GALERIE (KOMPLETT-NEU V3)

## 🧩 Aufbau: 2 getrennte Bereiche

| Bereich | URL | Wer darf hin? | Was passiert dort? |
|---|---|---|---|
| **FRONTEND (Öffentlich)** | `/fotos/index.html` | **Jeder** | Alle SBS-Bilder ansehen, nach **Startnummer** suchen, Bilder herunterladen |
| **BACKEND (Admin)** | `/fotos/admin/index.html` | **NUR DU mit Passwort!** | Bilder hochladen, **Startnummern taggen**, 1-Klick Upload auf RV Hard Server |

---

## ⚙️ ERSTMALIGE SERVER-INSTALLATION (MACHEN WIR GENAU 1 MAL!)

### 🚨 GANZ WICHTIG: Passwort setzen!
Öffne auf deinem PC die Datei:
→ **`/fotos/admin/upload.php`** → **Zeile 9!**

```php
$ADMIN_PASSWORD = 'rvhard-sbs-passwort-2026!';  /* ← HIER DEIN EIGENES ADMIN-PASSWORT EINTRAGEN! */
```
→ **Ändere das Passwort auf DEINS!** (z.B. `MeinSuperSicheresSBSPasswort2026!`)

---

### Ordner + Dateien auf den Server laden (per FTP / FileZilla):

1. **Ganzen `/fotos/` Ordner** auf den Server kopieren → er liegt jetzt unter `rv-hard.at/fotos/`
2. **WICHTIG – Schreibrechte auf dem Server setzen (per FTP):**
   - Ordner **`/fotos/data/`** → Rechte auf **`755`** (read/write/execute für Owner)
   - Wenn es schon Bilder/Events gibt, später: Dateien → Rechte **`644`** (read/write Owner, read alle anderen)
3. **Testen ob Backend geht:**
   - Browser öffnen: **`https://rv-hard.at/fotos/admin/`**
   - **Passwort** eingeben → Login-Test!

---

## 👨‍💻 BACKEND BEDIENUNG (So lädst du SBS Bilder hoch!)

### 📍 URL im Browser:
→ **`https://rv-hard.at/fotos/admin/`**

---

### Ablauf Schritt für Schritt:

#### 1️⃣ SBS Jahr + Disziplin wählen
Oben auf der Seite:
1. **SBS Jahr** auswählen (z.B. `2026`)
2. Darunter erscheinen **3 Karten** für die Disziplinen:
   - 🏔️ **Bergrennen** (Schellenberg)
   - ⏱️ **Einzelzeitfahren** (EZF)
   - 🏁 **Kriterium** (Stadtkurs)
3. **Klicke die Disziplin an**, die du jetzt bearbeiten möchtest!

---

#### 2️⃣ Bilder hochladen (Drag & Drop)
1. **Ziehe Bilder per Maus** in das große graue Feld ("Bilder hier reinziehen…")
2. **ODER:** Klicke auf das Feld → Dateiauswahl → mehrere Bilder markieren → OK
3. Bilder erscheinen sofort als kleine Karten mit Vorschau!

---

#### 3️⃣ 🏁 STARTNUMMERN TAGGEN (WICHTIG! → Sonst findet später keiner Bilder!)
Für jedes Bild **MÜSSEN** Startnummern eingetragen werden. Zwei Möglichkeiten:

##### 🅰️ Einzeln (für wenige Bilder):
- Gib bei jedem Bild direkt im **gelben Feld "Startnummern"** die Nummern ein:
  ```
  42, 12, 88
  ```
  → Mit **KOMMA getrennt!**

##### 🅱️ **Massen-Tagging (MEGA SCHNELL – EMPFOHLEN!)**
Perfekt, wenn du 50 Bilder hast, auf denen Gruppe A Nummern 1-20 hat:
1. **Setze Haken** bei allen Bildern, bei denen die gleiche Nummer drauf ist
   - Oder Klick: **"Alle Bilder auswählen"**
2. In der **gelben "Massen-Tagging" Leiste** oben:
   - Feld **🏁 Startnummern**: `42, 12, 88` (alle Nummern, die auf den ausgewählten Bildern sind!)
   - Optional 👤 Fahrernamen, 📸 Fotograf
3. Klick: **"Tags hinzufügen"** → ✅ Fertig! Nummern sind bei allen ausgewählten Bildern eingetragen!

---

#### 4️⃣ 🚀 Auf den Server hochladen – 1 KLICK!
Sobald alle Bilder Nummern haben:
1. Scrolle runter zum **grünen Export-Bereich**
2. Klick: **"Auf RV Hard Server hochladen"**
3. Warte 5-30 Sekunden → Progress-Bar läuft hoch
4. ✅ **Erledigt!** Die Bilder sind **sofort LIVE** auf `rv-hard.at/fotos/` sichtbar!

---

## 👥 FRONTEND BEDIENUNG (Für Besucher / Fahrer / Zuschauer)

### 📍 URL im Browser:
→ **`https://rv-hard.at/fotos/`**

### Funktionen:
1. **Oben im Filter:**
   - **SBS Jahr**: z.B. nur `SBS 2026` anzeigen
   - **Disziplin**: z.B. nur `Bergrennen` anzeigen
   - **Suchfeld**: Suche nach Ort / Nummer / Namen
2. **Unterteilt nach SBS-Jahr** (2026 oben, 2025 darunter etc.)
3. **Pro Disziplin eine Karte**: 🏔️ Bergrennen · ⏱️ EZF · 🏁 Kriterium
4. **Klick auf Karte** → Öffnet die Galerie mit allen Bildern
5. **Filter auf Galerie-Seite:**
   - Feld **🏁 Startnummer** eingeben, z.B. `42`
   - **SOFORT werden NUR noch Bilder mit #42 angezeigt!**
6. **Klick auf Bild → Lightbox** mit:
   - Download-Button für Original
   - Anzeige von Nummern, Namen, Fotograf

---

## 🆘 Häufige Probleme + Lösungen

| Problem | Lösung |
|---|---|
| **Login im Backend: Falsches Passwort** | Prüfe in `/fotos/admin/upload.php` Zeile 9 – Passwort 1:1 gleich schreiben! |
| **Bilder werden nicht hochgeladen (Fehler 500)** | Ordner `/fotos/data/` braucht Schreibrechte! In FileZilla: Rechtsklick auf data/ → Dateirechte → `755` |
| **Besucher sehen keine Bilder trotz Upload** | Browser-Cache leeren! (Strg + Umschalt + Entf) ODER events.json Schreibrechte 644 |
| **Startnummer-Suche findet nix** | Hatten die Bilder im Backend auch wirklich **Nummern im gelben Feld** bekommen? |
| **Galerie zeigt "Events werden geladen…" für immer** | Prüfe per FTP, ob `/fotos/data/events.json` wirklich da ist und lesbar (644)! |

---

## 🧠 TECHNISCHE INFOS (Für später)

### Ordner-Struktur auf Server:
```
/fotos/
├── index.html                  ← Öffentliche Galerie (Frontend)
├── event.html                  ← Galerie EINER Disziplin (mit Nummernfilter)
├── README.md                   ← DIESE ANLEITUNG
│
├── admin/
│   ├── index.html              ← ADMIN BEREICH (Passwort-geschützt!)
│   └── upload.php              ← PHP Endpoint (Auth + Upload + Speichern)
│
├── assets/
│   ├── galerie.css
│   └── galerie.js
│
└── data/
    ├── events.json             ← ALLE Events + Fotos + Nummern (wird von admin.php erzeugt!)
    │
    ├── sbs-2026-bergrennen/    ← Bilder Bergrennen
    │   ├── 001-bibs-42-12.jpg
    │   ├── 002-bibs-88.jpg
    │   └── ...
    │
    ├── sbs-2026-ezf/           ← Bilder EZF
    ├── sbs-2026-kriterium/     ← Bilder Kriterium
    ├── sbs-2025-bergrennen/
    └── ...
```
