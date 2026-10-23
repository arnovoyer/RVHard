# 📸 SUMMER BIKE SERIES FOTO-GALERIE (V3.1 – EINFACH WIE FRÜHER!)

## 🏔️ ECHTE SBS ORTE 2026 (3 Disziplinen pro Jahr!)

Pro SBS-Jahr gibt es **3 ECHTE Wettbewerbe** mit echten Namen:

| Icon | Disziplin | Genauer Ort / Strecke | Ordner am PC + Server |
|---|---|---|---|
| 🏔️ | **Bergrennen** | Wolfurt → Buch (Bregenzerwald) | `/fotos/data/sbs-2026-bergrennen-wolfurt-buch/` |
| 🏁 | **Kriterium** | Kammgarn Areal, Hard (Stadtkurs) | `/fotos/data/sbs-2026-kriterium-kammgarn-hard/` |
| ⏱️ | **Einzelzeitfahren** | Rohrspitz, Fußach (Bodensee-Ufer) | `/fotos/data/sbs-2026-ezf-rohrspitz-fussach/` |

---

## 🧩 Aufbau des Systems (2 getrennte Bereiche)

| Bereich | URL | Wer darf hin? | Aufgabe |
|---|---|---|---|
| 🌍 **FRONTEND (öffentlich)** | `rv-hard.at/fotos/` | **Jeder Besucher** | Galerie ansehen, nach Startnummer suchen, Bilder downloaden |
| 🛠️ **BACKEND (Admin Tool)** | `rv-hard.at/fotos/admin/` | **Nur du (RV Hard)** | Bilder taggen, events.json herunterladen |

---

## 🚀 GANZER ABLauf SCHRITT FÜR SCHRITT (EINFACH WIE FRÜHER!)

### 1️⃣ Bilder per FTP hochladen

So wie früher, einfach per FileZilla/WinSCP:

1. **Bilder am PC in die passenden Ordner kopieren** (Ordner sind schon auf deinem PC angelegt!):
   ```
   /fotos/data/sbs-2026-bergrennen-wolfurt-buch/      ← Alle Bergrennen-Bilder
   /fotos/data/sbs-2026-kriterium-kammgarn-hard/      ← Alle Kriterium-Bilder
   /fotos/data/sbs-2026-ezf-rohrspitz-fussach/         ← Alle EZF-Bilder
   ```
2. **Diese 3 Ordner per FTP auf den Server** nach `rv-hard.at/fotos/data/` hochladen
3. **ODER:** Direkt per FTP Bilder aus Explorer auf Server in die Ordner ziehen!

⚠️ **Wichtig:** Dateinamen am besten **kurz + ohne Leerzeichen**, z.B. `IMG_1234.jpg` oder `zielsprint-42.jpg`

---

### 2️⃣ Backend öffnen & Bilder taggen

Öffne **`rv-hard.at/fotos/admin/`** (Passwort kann auf Wunsch per .htaccess hinzugefügt werden – aktuell einfach nur "obfuscated" weil URL nicht öffentlich bekannt ist).

Dort:
1. **SBS-Jahr** auswählen (2026 / 2025 / 2024)
2. **Klicke die Disziplin an**, die du gerade bearbeiten willst:
   - 🏔️ Bergrennen Wolfurt-Buch
   - 🏁 Kriterium Kammgarn Hard
   - ⏱️ EZF Rohrspitz Fußach
3. **Bilder reinziehen:**
   - Dateien aus dem lokalen Ordner markieren
   - Oder per FTP **runtergeladene Bilder** (zur Vorschau) markieren
   - Ins große graue Feld ziehen → Vorschau erscheint
4. **STARTNUMMERN TAGGEN – 2 Möglichkeiten:**
   - **🟡 MASSEN-TAGGING (Schnellste Variante – EMPFOHLEN!):**
     1. Haken bei Bildern setzen (oder "Alle" Button)
     2. In gelbe Leiste oben Nummern eingeben, z.B. `42, 12, 88` (KOMMA getrennt!)
     3. Button **"auf Auswahl anwenden"** → Nummern sind bei allen Bildern gesetzt! ✅
   - **Einzeln:** Bei jedem Bild im gelben Feld Nummern eintragen
5. **Speichern:** Button **"events.json herunterladen"** klicken → Datei wird runtergeladen!

---

### 3️⃣ events.json auf den Server laden

1. **FTP öffnen** (FileZilla/WinSCP)
2. **Heruntergeladene** `events.json` nach:
   ```
   /fotos/data/events.json   (ersetzen!)
   ```
   hochladen (ja, Datei überschreiben!)
3. **FERTIG!** 🎉

---

### 4️⃣ Testen im Frontend

Öffne: **`rv-hard.at/fotos/`**
1. Wähle oben im Filter: Jahr (2026) ODER Disziplin (z.B. Kriterium)
2. Klicke auf die **Disziplin-Karte**
3. Gib im Feld **🏁 Startnummer** `42` ein → **Filter funktioniert sofort!**
4. Klick auf ein Bild → Lightbox → Download-Button

---

## 🧠 Wie funktioniert die Startnummer-Suche? (Ganz einfach!)

| Schritt | Wer | Was |
|---|---|---|
| 1 | **Du (Admin)** | Jedes Bild bekommt Tags mit den sichtbaren Startnummern, z.B. `bibNumbers: [42, 12, 88]` |
| 2 | **Besucher** | Gibt im Feld "Startnummer" die `42` ein |
| 3 | **JavaScript** | Durchsucht ALLE Bilder nach `bibNumbers`, die `42` enthalten |
| 4 | **Ergebnis** | Nur Bilder MIT der Nummer 42 werden angezeigt! |

**Auch Teil-Suche:** Eingabe `4` → findet automatisch `#4`, `#14`, `#42`, `#144`, `#412` etc.

---

## 📂 Ordner-Struktur auf Server & PC

```
/fotos/
├── index.html                  ← Frontend Galerie (öffentlich)
├── event.html                  ← Einzelne Disziplin-Seite (mit Nummernfilter)
│
├── admin/
│   └── index.html              ← Backend (NUR DU!): Bilder taggen + JSON download
│
├── assets/
│   ├── galerie.css             ← Styling (RV Hard Design)
│   └── galerie.js              ← Logik (Filter + Nummernsuche)
│
├── data/
│   ├── events.json             ← ALLE Events + Fotos + Startnummern (ZENTRAL!)
│   │
│   ├── sbs-2026-bergrennen-wolfurt-buch/    ← BERGRENNEN-BILDER (FTP)
│   │   ├── IMG_1234.jpg
│   │   ├── IMG_1235.jpg
│   │   └── ...
│   │
│   ├── sbs-2026-kriterium-kammgarn-hard/    ← KRITERIUM-BILDER (FTP)
│   │   ├── bild1.jpg
│   │   └── ...
│   │
│   └── sbs-2026-ezf-rohrspitz-fussach/       ← EZF-BILDER (FTP)
│       ├── ezf001.jpg
│       └── ...
│
└── README.md                   ← Diese Anleitung
```

---

## 🆘 Troubleshooting

| Problem | Lösung |
|---|---|
| **Nummernsuche findet nix** | Backend gecheckt: Haben die Bilder wirklich **gelbe Startnummern-Einträge**? events.json aktuell auf Server? |
| **Bilder werden als "kaputt" angezeigt** | Bilder per FTP im richtigen Ordner? Dateinamen 1:1 gleich wie auf PC? |
| **Galerie zeigt Lade-Symbol ewig** | events.json auf dem Server vorhanden? Rechte 644? |
| **Browser zeigt alte Bilder** | Cache leeren! Strg + Umschalt + Entf. |
| **Welche Events sind überhaupt da?** | Öffne `rv-hard.at/fotos/data/events.json` im Browser → alles anzeigbar! |
