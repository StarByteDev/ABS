import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const buildManifestPath = path.join(root, 'docs/BUILD_MANIFEST.txt');
const hashManifestPath = path.join(root, 'docs/FILE_SHA256_MANIFEST.txt');
const ignoredDirectories = new Set(['.git', 'node_modules', 'vendor', 'storage']);
const ignoredPrefixes = [
    'bootstrap/cache/',
];

function walk(directory) {
    return fs.readdirSync(directory, {withFileTypes: true}).flatMap(entry => {
        if (entry.isDirectory() && directory === root && ignoredDirectories.has(entry.name)) return [];
        const absolute = path.join(directory, entry.name);
        return entry.isDirectory() ? walk(absolute) : [absolute];
    });
}

function relative(file) {
    return path.relative(root, file).split(path.sep).join('/');
}

function packageFiles() {
    return walk(root)
        .filter(file => !ignoredPrefixes.some(prefix => relative(file).startsWith(prefix)))
        .sort((left, right) => relative(left).localeCompare(relative(right)));
}

const buildVersion = fs.readFileSync(path.join(root, 'BUILD_VERSION.txt'), 'utf8').trim();
const manifestVersion = buildVersion.replace(/\s+Build$/i, '');
const releaseDate = '2026-09-09';
const manifestSources = packageFiles().filter(file => ![buildManifestPath, hashManifestPath].includes(file));
const buildLines = [
    `${manifestVersion} — BUILD MANIFEST`,
    'Release package contents',
    `Release date: ${releaseDate}`,
    `Files listed: ${manifestSources.length}`,
    '',
    'BYTES\tPATH',
    ...manifestSources.map(file => `${fs.statSync(file).size}\t${relative(file)}`),
    '',
];
fs.writeFileSync(buildManifestPath, buildLines.join('\n'));

const hashSources = packageFiles().filter(file => file !== hashManifestPath);
const hashLines = [
    `${manifestVersion} — FILE SHA-256 MANIFEST`,
    `Release date: ${releaseDate}`,
    `Files hashed: ${hashSources.length}`,
    '',
    'SHA256  PATH',
    ...hashSources.map(file => {
        const digest = crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
        return `${digest}  ${relative(file)}`;
    }),
    '',
];
fs.writeFileSync(hashManifestPath, hashLines.join('\n'));

console.log(`Build manifest: ${manifestSources.length} files`);
console.log(`SHA-256 manifest: ${hashSources.length} files`);
