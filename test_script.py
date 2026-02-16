#!/usr/bin/env python3
import os
import sys

print("Test 1: Basic Output")
print(f"Python Version: {sys.version}")
print(f"Current Directory: {os.getcwd()}")

base_dir = r"c:\Users\Arnov\OneDrive - BRG Schoren\RVHard\RV Hard Webseite\news"
print(f"News Dir: {base_dir}")
print(f"Dir Exists: {os.path.exists(base_dir)}")

if os.path.exists(base_dir):
    files = [f for f in os.listdir(base_dir) if f.endswith('.html')]
    print(f"Found {len(files)} HTML files")
    print("First 5 files:")
    for f in files[:5]:
        print(f"  - {f}")

print("Test Complete!")
