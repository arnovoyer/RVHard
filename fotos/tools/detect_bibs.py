#!/usr/bin/env python3
# -*- coding: utf-8 -*-
r"""
RV Hard – Startnummern-Erkennung (detect_bibs.py)
==================================================
Scant alle .jpg/.jpeg/.png Bilder in einem Ordner per OCR
(pytesseract + Tesseract OCR Engine – läuft auf CPU)
und extrahiert plausible Startnummern (1–4 Ziffern).

Output: startnummern.json im Format:
    {"foto_001.jpg": ["123"], "foto_002.jpg": ["45", "678"]}

Verwendung:
    python detect_bibs.py /pfad/zum/bilder-ordner
    python detect_bibs.py /pfad/zum/bilder-ordner --output ergebnis.json

Installation (Windows):
    1. Tesseract OCR installieren:
       https://github.com/UB-Mannheim/tesseract/wiki
       → Download tesseract-ocr-w64-setup-*.exe
       → Bei der Installation Haken bei "German language data" setzen!
       → Standard-Pfad: C:\Program Files\Tesseract-OCR\tesseract.exe

    2. Python-Pakete installieren:
       pip install pytesseract pillow tqdm
"""

import argparse
import json
import re
import sys
from pathlib import Path

try:
    from tqdm import tqdm
except ImportError:
    print("FEHLER: tqdm ist nicht installiert!")
    print("Installiere es mit: pip install tqdm")
    sys.exit(1)

try:
    import pytesseract
except ImportError:
    print("FEHLER: pytesseract ist nicht installiert!")
    print("Installiere es mit: pip install pytesseract")
    sys.exit(1)

try:
    from PIL import Image
except ImportError:
    print("FEHLER: Pillow ist nicht installiert!")
    print("Installiere es mit: pip install pillow")
    sys.exit(1)


# ---- Tesseract automatisch finden (Windows) ----
import os
_tess_candidates = [
    r"C:\Program Files\Tesseract-OCR",
    r"C:\Program Files (x86)\Tesseract-OCR",
]
for _tp in _tess_candidates:
    if os.path.isfile(os.path.join(_tp, "tesseract.exe")):
        os.environ["PATH"] = _tp + os.pathsep + os.environ.get("PATH", "")
        pytesseract.pytesseract.tesseract_cmd = os.path.join(_tp, "tesseract.exe")
        break


# Falls tesseract trotzdem nicht im PATH ist, hier den Pfad eintragen:
# pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# Regex für Startnummern: 1–4 Ziffern
BIB_PATTERN = re.compile(r"\b(\d{1,4})\b")

# Unterstützte Bildformate
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png"}


def find_images(folder: Path):
    """Sammelt alle Bilddateien im Ordner (nicht rekursiv)."""
    return sorted(
        f for f in folder.iterdir()
        if f.is_file() and f.suffix.lower() in IMAGE_EXTENSIONS
    )


def extract_bibs(image_path: Path):
    """
    Erkennt Ziffern in einem Bild und gibt eine Liste von
    plausiblen Startnummern (als Strings) zurück.
    """
    try:
        img = Image.open(image_path)
        # --psm 11 = Sparse text (findet lose Textteile wie Nummern auf Trikots)
        # -c tessedit_char_whitelist=0123456789 → NUR Ziffern erkennen
        text = pytesseract.image_to_string(
            img,
            config="--psm 11 -c tessedit_char_whitelist=0123456789",
        )
    except Exception as e:
        print(f"  WARN: Fehler bei {image_path.name}: {e}", file=sys.stderr)
        return []

    bibs = set()
    for match in BIB_PATTERN.finditer(text):
        num = match.group(1)
        # Führende Nullen entfernen (außer "0" selbst)
        num = str(int(num))
        bibs.add(num)

    # Sortiert zurückgeben
    return sorted(bibs, key=lambda x: int(x))


def main():
    parser = argparse.ArgumentParser(
        description="RV Hard – Startnummern-Erkennung per OCR"
    )
    parser.add_argument(
        "folder",
        help="Ordner mit den Bildern (.jpg/.jpeg/.png)",
    )
    parser.add_argument(
        "--output", "-o",
        default="startnummern.json",
        help="Pfad zur Ausgabe-JSON (Standard: startnummern.json)",
    )
    parser.add_argument(
        "--no-progress",
        action="store_true",
        help="Keinen Fortschrittsbalken anzeigen",
    )
    args = parser.parse_args()

    folder = Path(args.folder).resolve()
    if not folder.is_dir():
        print(f"FEHLER: Ordner nicht gefunden: {folder}")
        sys.exit(1)

    images = find_images(folder)
    if not images:
        print(f"Keine Bilder (.jpg/.jpeg/.png) in {folder} gefunden.")
        sys.exit(0)

    # Prüfe ob tesseract gefunden wird
    try:
        pytesseract.get_tesseract_version()
    except Exception:
        print("FEHLER: Tesseract OCR nicht gefunden!")
        print("Lösung:")
        print("  1. Tesseract installieren von https://github.com/UB-Mannheim/tesseract/wiki")
        print("  2. Oder in detect_bibs.py den Pfad eintragen bei 'pytesseract.pytesseract.tesseract_cmd'")
        sys.exit(1)

    print(f"Gefundene Bilder: {len(images)}")
    print("Tesseract OCR bereit. Starte Scan…")

    results = {}
    iterator = images if args.no_progress else tqdm(images, desc="Scanne Bilder", unit="Bild")

    for img in iterator:
        bibs = extract_bibs(img)
        results[img.name] = bibs
        if not args.no_progress:
            iterator.set_postfix({img.name: f"{len(bibs)} #N"})

    # Ergebnis speichern
    output_path = Path(args.output)
    if not output_path.is_absolute():
        output_path = folder / output_path

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)

    # Statistik
    total_bibs = sum(len(v) for v in results.values())
    with_bibs = sum(1 for v in results.values() if v)

    print()
    print("=" * 60)
    print(f"FERTIG! Ergebnisse gespeichert in: {output_path}")
    print(f"  Bilder gescannt : {len(results)}")
    print(f"  Bilder mit #N   : {with_bibs}")
    print(f"  Startnummern gesamt: {total_bibs}")
    print("=" * 60)


if __name__ == "__main__":
    main()
