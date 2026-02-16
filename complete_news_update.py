#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Complete update of all news files with preconnect links"""

import re
import os
from pathlib import Path

news_dir = Path(__file__).parent / 'news'
updated = []
skipped = []

for file in sorted(news_dir.glob('*.html')):
    content = open(file, 'r', encoding='utf-8').read()
    original = content
    
    # Pattern 1: Add unpkg preconnect in older pattern (before gstatic line)
    if 'https://unpkg.com/aos' in content and 'preconnect" href="https://unpkg.com' not in content:
        content = re.sub(
            r'(<link rel="preconnect" href="https://fonts\.googleapis\.com">\s*<link href="https://unpkg\.com/aos)',
            r'<link rel="preconnect" href="https://fonts.googleapis.com">\n    <link rel="preconnect" href="https://unpkg.com">\n    <link href="https://unpkg.com/aos',
            content
        )
    
    # Pattern 2: Add unpkg preconnect in newer pattern (after gstatic line)
    if 'https://unpkg.com/aos' in content and 'preconnect" href="https://unpkg.com' not in content:
        content = re.sub(
            r'(<link rel="preconnect" href="https://fonts\.gstatic\.com"[^>]*>\s*<link href="https://unpkg\.com/aos)',
            r'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n    <link rel="preconnect" href="https://unpkg.com">\n    <link href="https://unpkg.com/aos',
            content
        )
    
    # Pattern 3: Add cdnjs preconnect
    if 'cdnjs.cloudflare.com' in content and 'preconnect" href="https://cdnjs.cloudflare.com' not in content:
        content = re.sub(
            r'(<link rel="stylesheet" href="https://cdnjs\.cloudflare\.com)',
            r'<link rel="preconnect" href="https://cdnjs.cloudflare.com">\n    \1',
            content
        )
    
    if content != original:
        open(file, 'w', encoding='utf-8').write(content)
        updated.append(file.name)
    else:
        skipped.append(file.name)

print(f"Aktualisiert: {len(updated)}\nSkipped: {len(skipped)}")
for f in updated[:10]:
    print(f"  ✓ {f}")
if len(updated) > 10:
    print(f"  ... und {len(updated)-10} weitere")
