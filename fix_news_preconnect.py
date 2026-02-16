#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Update preconnect links in news HTML files"""

import re
from pathlib import Path

news_dir = Path(__file__).parent / 'news'
updated_count = 0

for file in sorted(news_dir.glob('*.html')):
    try:
        with open(file, 'r', encoding='utf-8') as f:
            content = f.read()
        
        original = content
        
        # 1. Preconnect für unpkg (vor aos.css Link)
        if 'unpkg.com/aos' in content and 'preconnect" href="https://unpkg.com' not in content:
            # Ersetze aos Link - einfach preconnect davor einfügen
            content = re.sub(
                r'(<link\s+href="https://unpkg\.com/aos[^>]*>)',
                r'<link rel="preconnect" href="https://unpkg.com">\n    \1',
                content
            )
        
        # 2. Preconnect für cdnjs (vor Font Awesome)
        if 'cdnjs.cloudflare.com' in content and 'preconnect" href="https://cdnjs.cloudflare.com' not in content:
            # Ersetze cdnjs Link  
            content = re.sub(
                r'(<link\s+rel="stylesheet"\s+href="https://cdnjs\.cloudflare\.com[^>]*>)',
                r'<link rel="preconnect" href="https://cdnjs.cloudflare.com">\n    \1',
                content
            )
        
        if content != original:
            with open(file, 'w', encoding='utf-8') as f:
                f.write(content)
            updated_count += 1
    except Exception as e:
        pass

print(f'{updated_count} Dateien aktualisiert')
