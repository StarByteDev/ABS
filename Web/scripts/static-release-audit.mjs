import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const viewRoot = path.join(root, 'resources', 'views');
const publicRoot = path.join(root, 'public');

const walk = directory => fs.readdirSync(directory, {withFileTypes: true}).flatMap(entry => {
    const target = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(target) : [target];
});

const bladeFiles = walk(viewRoot).filter(file => file.endsWith('.blade.php'));
const allBladeSource = bladeFiles.map(file => fs.readFileSync(file, 'utf8')).join('\n');
const phpFiles = ['app', 'routes', 'config', 'database', 'tests']
    .flatMap(directory => walk(path.join(root, directory)))
    .filter(file => file.endsWith('.php'));
const failures = [];

const balancedDelimiters = source => {
    const stack = [];
    const pairs = {')': '(', ']': '[', '}': '{'};
    let quote = null;
    let lineComment = false;
    let blockComment = false;
    for (let index = 0; index < source.length; index += 1) {
        const char = source[index];
        const next = source[index + 1];
        if (lineComment) {
            if (char === '\n') lineComment = false;
            continue;
        }
        if (blockComment) {
            if (char === '*' && next === '/') { blockComment = false; index += 1; }
            continue;
        }
        if (quote) {
            if (char === '\\') { index += 1; continue; }
            if (char === quote) quote = null;
            continue;
        }
        if (char === '/' && next === '/') { lineComment = true; index += 1; continue; }
        if (char === '/' && next === '*') { blockComment = true; index += 1; continue; }
        if (char === '#' && next !== '[') { lineComment = true; continue; }
        if (char === "'" || char === '"' || char === '`') { quote = char; continue; }
        if ('([{'.includes(char)) stack.push(char);
        if (')]}'.includes(char) && stack.pop() !== pairs[char]) return false;
    }
    return stack.length === 0 && !quote && !blockComment;
};

const routeSource = fs.readFileSync(path.join(root, 'routes', 'web.php'), 'utf8');
let depth = 0;
const prefixes = [];
const routeNames = new Set();

for (const line of routeSource.split(/\r?\n/)) {
    while (prefixes.length && depth <= prefixes[prefixes.length - 1].baseDepth) prefixes.pop();
    const groupPrefix = line.match(/->name\(\s*['"]([^'"]+\.)['"]\s*\)->group\s*\(/);
    const isGroup = /->group\s*\(/.test(line);
    if (groupPrefix && isGroup) prefixes.push({value: groupPrefix[1], baseDepth: depth});
    if (!isGroup) {
        for (const match of line.matchAll(/->name\(\s*['"]([^'"]+)['"]\s*\)/g)) {
            routeNames.add(prefixes.map(item => item.value).join('') + match[1]);
        }
    }
    const code = line.replace(/(['"])(?:\\.|(?!\1).)*\1/g, '');
    depth += (code.match(/\{/g) || []).length - (code.match(/\}/g) || []).length;
}

const sourceFiles = bladeFiles.concat(phpFiles);
const routeReferences = new Set();
for (const file of sourceFiles) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(/route\(\s*['"]([^'"]+)['"]/g)) routeReferences.add(match[1]);
}
for (const name of [...routeReferences].sort()) {
    if (!routeNames.has(name)) failures.push(`Missing named route: ${name}`);
}

for (const routeFile of ['routes/web.php', 'routes/api.php']) {
    const routeFileSource = fs.readFileSync(path.join(root, routeFile), 'utf8');
    const orderedRoutes = [...routeFileSource.matchAll(/Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/gi)]
        .map(match => ({method: match[1].toUpperCase(), path: match[2]}));
    for (let earlier = 0; earlier < orderedRoutes.length; earlier += 1) {
        const candidate = orderedRoutes[earlier];
        if (!candidate.path.includes('{')) continue;
        const pattern = new RegExp(`^${candidate.path
            .replace(/[.*+?^$()|[\]\\]/g, '\\$&')
            .replace(/\\?\{[^}]+}/g, '[^/]+')}$`);
        for (let later = earlier + 1; later < orderedRoutes.length; later += 1) {
            const target = orderedRoutes[later];
            if (candidate.method === target.method && !target.path.includes('{') && pattern.test(target.path)) {
                failures.push(`Dynamic route shadows static route in ${routeFile}: ${candidate.method} ${candidate.path} before ${target.path}`);
            }
        }
    }
    const imports = new Map([...routeFileSource.matchAll(/^use\s+(App\\[^;]+);/gm)].map(match => {
        const [className, alias] = match[1].split(/\s+as\s+/i);
        return [alias || className.split('\\').at(-1), className];
    }));
    for (const match of routeFileSource.matchAll(/\[\s*(\w+)::class\s*,\s*['"](\w+)['"]\s*\]/g)) {
        const className = imports.get(match[1]);
        if (!className) { failures.push(`Unresolved controller in ${routeFile}: ${match[1]}`); continue; }
        const controllerPath = path.join(root, `${className.replace(/^App\\/, 'app\\').replaceAll('\\', '/')}.php`);
        if (!fs.existsSync(controllerPath)) { failures.push(`Missing controller in ${routeFile}: ${className}`); continue; }
        const controllerSource = fs.readFileSync(controllerPath, 'utf8');
        if (!new RegExp(`public\\s+function\\s+${match[2]}\\s*\\(`).test(controllerSource)) {
            failures.push(`Missing controller method in ${routeFile}: ${className}::${match[2]}`);
        }
    }
    for (const match of routeFileSource.matchAll(/Route::(?:get|post|put|patch|delete)\([\s\S]*?,\s*(\w+)::class\s*\)/g)) {
        const className = imports.get(match[1]);
        if (!className) { failures.push(`Unresolved invokable controller in ${routeFile}: ${match[1]}`); continue; }
        const controllerPath = path.join(root, `${className.replace(/^App\\/, 'app\\').replaceAll('\\', '/')}.php`);
        if (!fs.existsSync(controllerPath)) { failures.push(`Missing invokable controller in ${routeFile}: ${className}`); continue; }
        if (!/public\s+function\s+__invoke\s*\(/.test(fs.readFileSync(controllerPath, 'utf8'))) {
            failures.push(`Missing __invoke method in ${routeFile}: ${className}`);
        }
    }
}

for (const file of phpFiles) {
    const source = fs.readFileSync(file, 'utf8');
    if (!balancedDelimiters(source)) failures.push(`Unbalanced PHP delimiters in ${path.relative(root, file)}`);
}

const crossRouteTargets = {
    home: {file: 'resources/views/home.blade.php'},
    about: {file: 'resources/views/pages/about.blade.php'},
    'pulse.entry': {file: 'resources/views/pulse/gateway.blade.php'},
    'pulse.settings.edit': {file: 'resources/views/pulse/settings.blade.php'},
    'pulse.points.index': {file: 'resources/views/pulse/points/index.blade.php'},
    'legal.privacy': {anchors: ['section-8-cookies-sessions']},
};
for (const match of allBladeSource.matchAll(/route\(\s*['"]([^'"]+)['"][^)]*\)\s*}}#([A-Za-z][\w:-]*)/g)) {
    const [, routeName, anchor] = match;
    const target = crossRouteTargets[routeName];
    if (!target) { failures.push(`Unverified cross-route anchor: ${routeName}#${anchor}`); continue; }
    const targetSource = target.file ? fs.readFileSync(path.join(root, target.file), 'utf8') : '';
    const literalId = new RegExp(`id=["']${anchor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}["']`).test(targetSource);
    if (!literalId && !(target.anchors || []).includes(anchor)) failures.push(`Missing cross-route anchor: ${routeName}#${anchor}`);
}

const apiSource = fs.readFileSync(path.join(root, 'routes', 'api.php'), 'utf8');
const apiGroups = [];
const apiOperations = [];
let apiDepth = 0;
for (const line of apiSource.split(/\r?\n/)) {
    while (apiGroups.length && apiDepth <= apiGroups.at(-1).baseDepth) apiGroups.pop();
    const prefix = line.match(/(?:::|->)prefix\(\s*['"]([^'"]+)['"]\s*\)/)?.[1] || '';
    if (/->group\s*\(/.test(line)) apiGroups.push({prefix, baseDepth: apiDepth});
    for (const match of line.matchAll(/Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/gi)) {
        const prefixes = apiGroups.map(group => group.prefix).filter(Boolean);
        if (prefixes[0] !== 'v1') continue;
        apiOperations.push(`${match[1].toLowerCase()} /${[...prefixes.slice(1), match[2].replace(/^\//, '')].filter(Boolean).join('/')}`);
    }
    const code = line.replace(/(['"])(?:\\.|(?!\1).)*\1/g, '');
    apiDepth += (code.match(/\{/g) || []).length - (code.match(/\}/g) || []).length;
}
const openApiLines = fs.readFileSync(path.join(root, 'docs', 'openapi.yaml'), 'utf8').split(/\r?\n/);
const documentedOperations = [];
let openApiPath = null;
for (const line of openApiLines) {
    const pathMatch = line.match(/^  (\/[^:]+):\s*$/);
    if (pathMatch) { openApiPath = pathMatch[1]; continue; }
    const methodMatch = line.match(/^    (get|post|put|patch|delete):\s*$/);
    if (methodMatch && openApiPath) documentedOperations.push(`${methodMatch[1]} ${openApiPath}`);
}
const operationShape = operation => operation.replace(/\{[^}]+}/g, '{}');
const actualApiShapes = new Set(apiOperations.map(operationShape));
const documentedApiShapes = new Set(documentedOperations.map(operationShape));
for (const operation of [...actualApiShapes].sort()) {
    if (!documentedApiShapes.has(operation)) failures.push(`API operation missing from OpenAPI: ${operation}`);
}
for (const operation of [...documentedApiShapes].sort()) {
    if (!actualApiShapes.has(operation)) failures.push(`OpenAPI operation has no API route: ${operation}`);
}

for (const file of bladeFiles) {
    const source = fs.readFileSync(file, 'utf8');
    if (/(?:href|action)=["']#["']/.test(source)) failures.push(`Placeholder link or form action in ${path.relative(root, file)}`);
    if (/>[^<]*\bworkspace\b/i.test(source)) failures.push(`Customer-facing "workspace" copy in ${path.relative(root, file)}`);
    for (const match of source.matchAll(/asset\(\s*['"]([^'"]+)['"]\s*\)/g)) {
        const assetPath = match[1];
        if (!fs.existsSync(path.join(publicRoot, assetPath))) failures.push(`Missing asset in ${path.relative(root, file)}: ${assetPath}`);
    }
    for (const match of source.matchAll(/href=["']#([A-Za-z][\w:-]*)["']/g)) {
        const id = match[1];
        if (!new RegExp(`id=["']${id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}["']`).test(allBladeSource)) {
            failures.push(`Missing same-page anchor in ${path.relative(root, file)}: #${id}`);
        }
    }
    const phpOpen = (source.match(/<\?php/g) || []).length;
    const phpClose = (source.match(/\?>/g) || []).length;
    if (phpOpen !== phpClose) failures.push(`Unbalanced PHP blocks in ${path.relative(root, file)} (${phpOpen}/${phpClose})`);
    const sections = (source.match(/@section\(/g) || []).length;
    const endSections = (source.match(/@endsection/g) || []).length;
    if (sections > 0 && endSections === 0) failures.push(`Blade section has no @endsection in ${path.relative(root, file)}`);
}

const allowedLiteralInternalTargets = new Set([
    '/',
    '/api/recovery',
    '/api/recovery/repair',
    '/api/recovery/initialize',
    '/api/recovery/restore',
]);
for (const match of allBladeSource.matchAll(/(?:href|action)=["'](\/[^"'?#]*)["']/g)) {
    if (!allowedLiteralInternalTargets.has(match[1])) failures.push(`Unverified literal internal target: ${match[1]}`);
}

const publicStylesheets = walk(path.join(publicRoot, 'assets')).filter(file => file.endsWith('.css'));
for (const stylesheet of publicStylesheets) {
    const source = fs.readFileSync(stylesheet, 'utf8');
    for (const match of source.matchAll(/url\(\s*["']?([^"')]+)["']?\s*\)/g)) {
        const target = match[1].trim();
        if (target.startsWith('#') || target.startsWith('data:') || /^https?:\/\//i.test(target)) continue;
        const absolute = path.resolve(path.dirname(stylesheet), target.split(/[?#]/)[0]);
        if (!fs.existsSync(absolute)) failures.push(`Missing CSS asset in ${path.relative(root, stylesheet)}: ${target}`);
    }
}


// Verify static controller/route view targets exist.
let staticViewReferences = 0;
for (const file of phpFiles) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(/\bview\(\s*['"]([^'"]+)['"]/g)) {
        staticViewReferences += 1;
        const expected = path.join(viewRoot, ...match[1].split('.')) + '.blade.php';
        if (!fs.existsSync(expected)) failures.push(`Missing static view target in ${path.relative(root, file)}: ${match[1]}`);
    }
}

// Verify literal Blade @extends/@include targets exist.
let bladeTemplateReferences = 0;
for (const file of bladeFiles) {
    const source = fs.readFileSync(file, 'utf8');
    for (const match of source.matchAll(/@(?:extends|include|includeIf|includeWhen|includeUnless)\(\s*['"]([^'"]+)['"]/g)) {
        bladeTemplateReferences += 1;
        const expected = path.join(viewRoot, ...match[1].split('.')) + '.blade.php';
        if (!fs.existsSync(expected)) failures.push(`Missing Blade template reference in ${path.relative(root, file)}: ${match[1]}`);
    }
}

for (const required of [
    'BUILD_VERSION.txt',
    'docs/ABS_V14_7_7_PREMIUM_PULSE_USER_PAGES.md',
    'docs/ABS_V14_7_7_VALIDATION.md',
    'docs/BUILD_VALIDATION_V14_7.md',
    'docs/MOBILE_API_V14_7.md',
    'docs/openapi.yaml',
    'app/Services/PulsePageDataService.php',
    'public/assets/css/pulse-premium.css',
    'public/assets/js/pulse-premium.js',
    'resources/views/pulse/dashboard.blade.php',
    'resources/views/pulse/scanner.blade.php',
    'resources/views/pulse/signals/index.blade.php',
    'resources/views/pulse/strategies.blade.php',
    'resources/views/pulse/execution.blade.php',
    'tests/Feature/PulsePremiumPagesApiTest.php',
    'tests/Visual/generate-premium-previews.mjs',
    'tests/Visual/verify-premium-contract.mjs',
    'tests/Release/verify-v1484-contract.mjs',
    'docs/ABS_V14_8_4_FINAL_VALIDATION.md',
    'tests/Release/verify-v1485-contract.mjs',
    'docs/ABS_V14_8_5_SCANNER_FRAGMENT_HOTFIX.md',
    'docs/ABS_V14_8_5_FINAL_VALIDATION.md',
    'tests/Release/verify-v14819-simplified-ux.mjs',
    'docs/ABS_V14_8_19_SIMPLIFIED_PREMIUM_PULSE_UX.md',
    'docs/ABS_V14_8_19_FINAL_VALIDATION.md',
    'docs/MOBILE_API_V14_8_19.md',
    'tests/Release/verify-v14820-guided-self-configured-trading.mjs',
    'docs/ABS_V14_8_20_GUIDED_SELF_CONFIGURED_TRADING_UX.md',
    'docs/MOBILE_API_V14_8_20.md',
    'docs/ABS_V14_8_20_FINAL_VALIDATION.md',
    'tests/Release/verify-v14821-full-qa-hostgator.mjs',
    'docs/ABS_V14_8_21_FULL_APPLICATION_QA_HOSTGATOR_SCHEDULER.md',
    'docs/HOSTGATOR_CRON_SETUP_V14_8_21.md',
    'docs/MOBILE_API_V14_8_21.md',
    'docs/ABS_V14_8_21_FINAL_VALIDATION.md',
    'docs/MOBILE_API_V14_8_5.md',
    'tests/Release/verify-v1486-contract.mjs',
    'docs/ABS_V14_8_6_FULL_PACKAGE_SCANNER.md',
    'docs/MOBILE_API_V14_8_6.md',
    'docs/ABS_V14_8_6_FINAL_VALIDATION.md',
    'tests/Release/verify-v1487-contract.mjs',
    'docs/ABS_V14_8_7_SELECTED_PAIR_SCANNER_THRESHOLD.md',
    'tests/Release/verify-v1488-contract.mjs',
    'docs/ABS_V14_8_8_SCANNER_TRADE_BINANCE_EXECUTION.md',
    'docs/MOBILE_API_V14_8_8.md',
    'tests/Release/verify-v1489-contract.mjs',
    'docs/ABS_V14_8_9_SCANNER_ACTION_CONSISTENCY.md',
    'docs/MOBILE_API_V14_8_9.md',
    'tests/Release/verify-v14818-stability.mjs',
    'docs/ABS_V14_8_18_FULL_SITE_STABILITY_RUNTIME_REPAIR.md',
    'docs/MOBILE_API_V14_8_18.md',
    'docs/ABS_V14_8_18_FINAL_VALIDATION.md',
]) {
    if (!fs.existsSync(path.join(root, required))) failures.push(`Missing release file: ${required}`);
}

console.log(`Blade files inspected: ${bladeFiles.length}`);
console.log(`Named routes discovered: ${routeNames.size}`);
console.log(`Named route references checked: ${routeReferences.size}`);
console.log(`Mobile/API operations matched to OpenAPI: ${actualApiShapes.size}`);
console.log(`Static view targets checked: ${staticViewReferences}`);
console.log(`Blade template references checked: ${bladeTemplateReferences}`);

if (failures.length) {
    console.error(`Static release audit failed (${failures.length}):`);
    failures.forEach(failure => console.error(`- ${failure}`));
    process.exit(1);
}

console.log('Static release audit: PASS');
