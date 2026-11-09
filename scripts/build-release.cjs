const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const source = path.join(root, 'mac-frontend-motion-editor');
const dist = path.join(root, 'dist');
const run = process.env.GITHUB_RUN_NUMBER;
const attempt = process.env.GITHUB_RUN_ATTEMPT || '1';
if (!/^\d+$/.test(run || '') || !/^\d+$/.test(attempt) || Number(attempt) >= 1000) throw new Error('Valid GitHub run number and attempt required');
const main = fs.readFileSync(path.join(source, 'mac-frontend-motion-editor.php'), 'utf8');
const base = main.match(/Version:\s+(\d+\.\d+\.\d+)/)?.[1];
if (!base) throw new Error('Plugin version is missing');
const version = `${base}.${Number(run) * 1000 + Number(attempt)}`;
const tag = `v${version}`;
if (fs.existsSync(dist)) throw new Error('dist already exists; use a clean checkout');
fs.mkdirSync(dist);
const destination = path.join(dist, 'mac-frontend-motion-editor');
fs.cpSync(source, destination, { recursive: true });
fs.writeFileSync(path.join(destination, 'mac-frontend-motion-editor.php'), main.replace(/Version:\s+\d+\.\d+\.\d+/, `Version:           ${version}`).replace(/define\( 'MFME_VERSION', '[^']+' \)/, `define( 'MFME_VERSION', '${version}' )`));
const readmePath = path.join(destination, 'readme.txt');
fs.writeFileSync(readmePath, fs.readFileSync(readmePath, 'utf8').replace(/Stable tag: .*/, `Stable tag: ${version}`));
const packageUrl = `https://github.com/LeoVo2003/mac-frontend-motion-editor/releases/download/${tag}/mac-frontend-motion-editor.zip`;
fs.writeFileSync(path.join(dist, 'update.json'), JSON.stringify({ version, package: packageUrl }, null, 2) + '\n');
if (process.platform === 'win32') {
  execFileSync('powershell.exe', ['-NoProfile', '-Command', 'Compress-Archive -LiteralPath mac-frontend-motion-editor -DestinationPath mac-frontend-motion-editor.zip'], { cwd: dist });
} else {
  execFileSync('zip', ['-qr', 'mac-frontend-motion-editor.zip', 'mac-frontend-motion-editor'], { cwd: dist });
}
if (process.env.GITHUB_OUTPUT) fs.appendFileSync(process.env.GITHUB_OUTPUT, `version=${version}\ntag=${tag}\n`);
console.log(`Built ${tag}`);
