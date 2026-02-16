import os
import re

# Get base directory with proper handling
base_dir = r"c:\Users\Arnov\OneDrive - BRG Schoren\RVHard\RV Hard Webseite\news"
workspace_base = r"c:\Users\Arnov\OneDrive - BRG Schoren\RVHard\RV Hard Webseite"

files_updated = 0
files_checked = 0
log_output = []

log_output.append("=" * 70)
log_output.append("CDN Preconnect Link Update Process")
log_output.append("=" * 70)

for filename in sorted(os.listdir(base_dir)):
    if not filename.endswith('.html'):
        continue
    
    filepath = os.path.join(base_dir, filename)
    files_checked += 1
    
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
    except Exception as e:
        log_output.append(f"ERROR reading {filename}: {e}")
        continue
    
    new_content = content
    
    # Add unpkg preconnect if missing
    if 'unpkg.com/aos' in content:
        if 'rel="preconnect" href="https://unpkg.com"' not in content and "rel='preconnect' href='https://unpkg.com'" not in content:
            # Find the unpkg aos link and add preconnect before it
            pattern = r'(<link\s+href="https://unpkg\.com/aos[^>]*>)'
            if re.search(pattern, new_content):
                preconnect = '<link rel="preconnect" href="https://unpkg.com">\n    '
                new_content = re.sub(pattern, preconnect + r'\1', new_content, count=1)
                log_output.append(f"✓ Added unpkg preconnect to: {filename}")
    
    # Add cdnjs preconnect if missing
    if 'cdnjs.cloudflare.com' in content:
        if 'rel="preconnect" href="https://cdnjs.cloudflare.com"' not in content and "rel='preconnect' href='https://cdnjs.cloudflare.com'" not in content:
            # Find the cdnjs link and add preconnect before it
            pattern = r'(<link\s+rel="stylesheet"\s+href="https://cdnjs\.cloudflare\.com[^>]*>)'
            if re.search(pattern, new_content):
                preconnect = '<link rel="preconnect" href="https://cdnjs.cloudflare.com">\n    '
                new_content = re.sub(pattern, preconnect + r'\1', new_content, count=1)
                log_output.append(f"✓ Added cdnjs preconnect to: {filename}")
    
    # Write if changes were made
    if new_content != content:
        try:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(new_content)
            files_updated += 1
        except Exception as e:
            log_output.append(f"ERROR writing {filename}: {e}")

log_output.append("")
log_output.append("=" * 70)
log_output.append("SUMMARY")
log_output.append("=" * 70)
log_output.append(f"Total files checked: {files_checked}")
log_output.append(f"Files updated: {files_updated}")
log_output.append("=" * 70)

# Write to log file
log_file = os.path.join(workspace_base, "preconnect_update.log")
with open(log_file, 'w', encoding='utf-8') as f:
    f.write('\n'.join(log_output))

# Also print
for line in log_output:
    print(line)
