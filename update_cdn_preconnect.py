#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import os
import re
from pathlib import Path

# Workspace directory
workspace_dir = r"c:\Users\Arnov\OneDrive - BRG Schoren\RVHard\RV Hard Webseite"
news_dir = os.path.join(workspace_dir, "news")

print("Starting CDN preconnect update...")
print(f"News directory: {news_dir}")
print()

# Get all HTML files in the news directory
html_files = sorted([f for f in os.listdir(news_dir) if f.endswith('.html')])
print(f"Found {len(html_files)} HTML files\n")

updated_files = []
skipped_files = []

for idx, filename in enumerate(html_files, 1):
    filepath = os.path.join(news_dir, filename)
    
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        print(f"[{idx}] ERROR reading {filename}: {e}")
        continue
    
    changes_made = False
    
    # Check for unpkg.com/aos
    has_unpkg_link = bool(re.search(r'https://unpkg\.com/aos', content))
    has_unpkg_preconnect = bool(re.search(r'rel\s*=\s*["\']preconnect["\']\s+href\s*=\s*["\']https://unpkg\.com["\']', content, re.DOTALL))
    
    # Check for cdnjs.cloudflare.com
    has_cdnjs_link = bool(re.search(r'https://cdnjs\.cloudflare\.com', content))
    has_cdnjs_preconnect = bool(re.search(r'rel\s*=\s*["\']preconnect["\']\s+href\s*=\s*["\']https://cdnjs\.cloudflare\.com["\']', content, re.DOTALL))
    
    # Add unpkg preconnect if needed
    if has_unpkg_link and not has_unpkg_preconnect:
        # Find the first unpkg.com/aos link
        match = re.search(r'(<\s*link[^>]*href\s*=\s*["\']https://unpkg\.com/aos[^>]*>)', content, re.IGNORECASE)
        if match:
            preconnect = '<link rel="preconnect" href="https://unpkg.com">\n    '
            content = content[:match.start()] + preconnect + content[match.start():]
            changes_made = True
    
    # Add cdnjs preconnect if needed
    if has_cdnjs_link and not has_cdnjs_preconnect:
        # Find the first cdnjs.cloudflare.com link
        match = re.search(r'(<\s*link[^>]*href\s*=\s*["\']https://cdnjs\.cloudflare\.com[^>]*>)', content, re.IGNORECASE)
        if match:
            preconnect = '<link rel="preconnect" href="https://cdnjs.cloudflare.com">\n    '
            content = content[:match.start()] + preconnect + content[match.start():]
            changes_made = True
    
    # Write back if changes were made
    if changes_made:
        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            updated_files.append(filename)
            print(f"[{idx}] ✓ Updated: {filename}")
        except Exception as e:
            print(f"[{idx}] ERROR writing {filename}: {e}")
    else:
        skipped_files.append(filename)
        reasons = []
        if not has_unpkg_link and not has_cdnjs_link:
            reasons.append("no CDN links")
        else:
            if has_unpkg_link and has_unpkg_preconnect:
                reasons.append("unpkg preconnect OK")
            if has_cdnjs_link and has_cdnjs_preconnect:
                reasons.append("cdnjs preconnect OK")
        print(f"[{idx}] - Skipped ({'; '.join(reasons)}): {filename}")

print()
print("="*70)
print("SUMMARY:")
print("="*70)
print(f"Total HTML files processed: {len(html_files)}")
print(f"Files updated: {len(updated_files)}")
print(f"Files skipped: {len(skipped_files)}")

if updated_files:
    print(f"\nUpdated files ({len(updated_files)}):")
    for f in updated_files:
        print(f"  • {f}")

print("\nUpdate complete!")
