#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Update preconnect links in news HTML files"""

import os
from pathlib import Path

# Setze das Arbeitsverzeichnis
news_dir = Path(__file__).parent / 'news'
updated_count = 0

for file in sorted(news_dir.glob('*.html')):
    with open(file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    original = content
    
    # Füge preconnect für unpkg hinzu (vor aos.css)
    if 'unpkg.com/aos' in content and 'preconnect" href="https://unpkg.com' not in content:
        content = content.replace(
            '<link href="https://unpkg.com/aos',
            '<link rel="preconnect" href="https://unpkg.com">\n    <link href="https://unpkg.com/aos'
        )
    
    # Füge preconnect für cdnjs hinzu (vor cdnjs lінk)
    if 'cdnjs.cloudflare.com' in content and 'preconnect" href="https://cdnjs.cloudflare.com' not in content:
        content = content.replace(
            'href="https://cdnjs.cloudflare.com',
            'rel="preconnect" href="https://cdnjs.cloudflare.com">\n    <link rel="stylesheet" href="https://cdnjs.cloudflare.com'
        )
        # Fix: entferne das doppelte <link rel="stylesheet"
        content = content.replace(
            '>\n    <link rel="stylesheet" href="https://cdnjs.cloudflare.com',
            '">\n    <link rel="stylesheet" href="https://cdnjs.cloudflare.com'
        )
    
    if content != original:
        with open(file, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'✓ {file.name}')
        updated_count += 1

print(f'\nFertig! {updated_count} Dateien aktualisiert.')
