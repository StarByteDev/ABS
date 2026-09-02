#!/usr/bin/env python3
"""Repair Flutter native hosts for ABS mobile.

Safe to run repeatedly. It hard-pins Android compileSdk/NDK and rewrites
local.properties from the Flutter/Android SDK selected by RUN_ANDROID.bat.
"""
from pathlib import Path
import os
import re

ROOT = Path(__file__).resolve().parents[1]
OLD_IDS = ['com.alphablocksolutions.abs_mobile', 'com.alphablocksolutions.absmobile', 'com.alphablocksolutions.abs_pulse']
NEW_ID = 'com.alphablocksolutions.abs'
APP_NAME = 'ABS Pulse'
ANDROID_COMPILE_SDK = 36
ANDROID_MIN_SDK = 23
ANDROID_NDK = '27.0.12077973'
changed = []

for base in [ROOT / 'android', ROOT / 'ios']:
    if not base.exists():
        continue
    for p in base.rglob('*'):
        if not p.is_file() or p.suffix.lower() in {'.png','.jpg','.jpeg','.gif','.ico','.jar'}:
            continue
        try:
            text = p.read_text(encoding='utf-8')
        except Exception:
            continue
        original = text
        for old in OLD_IDS:
            text = text.replace(old, NEW_ID)
        if text != original:
            p.write_text(text, encoding='utf-8')
            changed.append(str(p.relative_to(ROOT)))

gradle = ROOT / 'android/app/build.gradle.kts'
if gradle.exists():
    text = gradle.read_text(encoding='utf-8')
    original = text
    # Accept either flutter.compileSdkVersion or any numeric value.
    text = re.sub(r'compileSdk\s*=\s*(?:flutter\.compileSdkVersion|\d+)', f'compileSdk = {ANDROID_COMPILE_SDK}', text, count=1)
    if re.search(r'ndkVersion\s*=', text):
        text = re.sub(r'ndkVersion\s*=\s*(?:flutter\.ndkVersion|"[^"]+")', f'ndkVersion = "{ANDROID_NDK}"', text, count=1)
    else:
        text = re.sub(r'(compileSdk\s*=\s*\d+\s*\n)', rf'\1    ndkVersion = "{ANDROID_NDK}"\n', text, count=1)
    text = re.sub(r'namespace\s*=\s*"[^"]+"', f'namespace = "{NEW_ID}"', text, count=1)
    text = re.sub(r'applicationId\s*=\s*"[^"]+"', f'applicationId = "{NEW_ID}"', text, count=1)
    text = re.sub(r'minSdk\s*=\s*(?:flutter\.minSdkVersion|\d+)', f'minSdk = {ANDROID_MIN_SDK}', text, count=1)
    if text != original:
        gradle.write_text(text, encoding='utf-8')
        changed.append(str(gradle.relative_to(ROOT)))

main_manifest = ROOT / 'android/app/src/main/AndroidManifest.xml'
values_dir = ROOT / 'android/app/src/main/res/values'
values_dir.mkdir(parents=True, exist_ok=True)
strings_xml = values_dir / 'strings.xml'
strings_xml.write_text('<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <string name="app_name">ABS Pulse</string>\n</resources>\n', encoding='utf-8')
changed.append(str(strings_xml.relative_to(ROOT)))

for manifest in [
    ROOT / 'android/app/src/main/AndroidManifest.xml',
    ROOT / 'android/app/src/debug/AndroidManifest.xml',
    ROOT / 'android/app/src/profile/AndroidManifest.xml',
]:
    if manifest.exists():
        text = manifest.read_text(encoding='utf-8')
        original = text
        if manifest == main_manifest and 'android.permission.INTERNET' not in text:
            close = text.find('>')
            if close != -1:
                text = text[:close+1] + '\n    <uses-permission android:name="android.permission.INTERNET" />' + text[close+1:]
        if 'android:label=' in text:
            text = re.sub(r'android:label="[^"]*"', 'android:label="ABS Pulse"', text)
        else:
            text = text.replace('<application', '<application android:label="ABS Pulse"', 1)
        if text != original:
            manifest.write_text(text, encoding='utf-8')
            changed.append(str(manifest.relative_to(ROOT)))

# Eliminate the common Windows failure where android/local.properties points at
# a different/obsolete Flutter SDK than the `flutter` command being executed.
flutter_sdk = os.environ.get('ABS_FLUTTER_SDK', '').strip()
android_sdk = os.environ.get('ABS_ANDROID_SDK', '').strip()
if (ROOT / 'android').exists() and flutter_sdk:
    def prop_path(v: str) -> str:
        return v.replace('\\', '\\\\')
    lines = []
    if android_sdk:
        lines.append(f'sdk.dir={prop_path(android_sdk)}')
    lines.append(f'flutter.sdk={prop_path(flutter_sdk)}')
    local = ROOT / 'android/local.properties'
    local.write_text('\n'.join(lines) + '\n', encoding='utf-8')
    changed.append(str(local.relative_to(ROOT)))

plist = ROOT / 'ios/Runner/Info.plist'
if plist.exists():
    text = plist.read_text(encoding='utf-8')
    original = text
    text = re.sub(r'(<key>CFBundleDisplayName</key>\s*<string>)[^<]*(</string>)', rf'\g<1>{APP_NAME}\g<2>', text, count=1)
    text = re.sub(r'(<key>CFBundleName</key>\s*<string>)[^<]*(</string>)', rf'\g<1>ABS Pulse\g<2>', text, count=1)
    if text != original:
        plist.write_text(text, encoding='utf-8')
        changed.append(str(plist.relative_to(ROOT)))

print('ABS native configuration complete')
print(f'  applicationId: {NEW_ID}')
print(f'  compileSdk: {ANDROID_COMPILE_SDK}')
print(f'  minSdk: {ANDROID_MIN_SDK}')
print(f'  ndkVersion: {ANDROID_NDK}')
if flutter_sdk:
    print(f'  flutter.sdk: {flutter_sdk}')
if android_sdk:
    print(f'  sdk.dir: {android_sdk}')
for item in sorted(set(changed)):
    print(f'  updated: {item}')
