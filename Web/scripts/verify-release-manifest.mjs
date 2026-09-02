import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const manifest = fs.readFileSync(path.join(root, 'docs/FILE_SHA256_MANIFEST.txt'), 'utf8');
const records = manifest.split(/\r?\n/)
    .map(line => line.match(/^([a-f0-9]{64})  (.+)$/))
    .filter(Boolean)
    .map(match => ({expected: match[1], relative: match[2]}));

const failures = [];
for (const record of records) {
    const file = path.join(root, ...record.relative.split('/'));
    if (!fs.existsSync(file)) {
        failures.push(`Missing: ${record.relative}`);
        continue;
    }
    const actual = crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
    if (actual !== record.expected) failures.push(`Checksum mismatch: ${record.relative}`);
}

if (failures.length > 0) {
    console.error(`Release manifest verification: FAIL (${failures.length})`);
    failures.forEach(failure => console.error(`- ${failure}`));
    process.exit(1);
}

console.log(`Release manifest verification: PASS (${records.length} files)`);
