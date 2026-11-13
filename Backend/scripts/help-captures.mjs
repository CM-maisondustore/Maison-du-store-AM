// Captures d'écran des guides d'aide (public/help/*.webp), sur les données de démonstration uniquement.
//
//   node scripts/help-captures.mjs --php "C:\chemin\php.exe" [--edge "C:\...\msedge.exe"] [--only nom1,nom2]
//
// Le script crée une base SQLite temporaire, la remplit avec DemoSeeder (aucun vrai client), démarre un serveur PHP local,
// pilote Microsoft Edge sans fenêtre (protocole DevTools) avec les comptes demo-agent, demo-manager et demo-admin,
// puis enregistre chaque capture décrite dans resources/help/captures.json. Tout est supprimé à la fin.
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const arg = (name, fallback) => { const i = process.argv.indexOf('--' + name); return i > -1 ? process.argv[i + 1] : fallback; };
const php = arg('php', 'php');
const edge = arg('edge', 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe');
const only = (arg('only', '') || '').split(',').filter(Boolean);
const PORT = 8765, DEBUG_PORT = 9333, BASE = `http://127.0.0.1:${PORT}`;
const PASSWORD = 'Demo-Maison-2026';
const ACCOUNTS = { agent: 'demo-agent', manager: 'demo-manager', admin: 'demo-admin' };
const manifest = JSON.parse(readFileSync(arg('manifest', join(root, 'resources/help/captures.json')), 'utf8')).captures.filter(c => !only.length || only.includes(c.name));
const sleep = ms => new Promise(r => setTimeout(r, ms));

const work = mkdtempSync(join(tmpdir(), 'mds-help-'));
const database = join(work, 'demo.sqlite');
writeFileSync(database, '');
const env = { ...process.env, APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: BASE, DB_CONNECTION: 'sqlite', DB_DATABASE: database,
  MAIL_MAILER: 'log', QUEUE_CONNECTION: 'sync', SESSION_DRIVER: 'file', CACHE_STORE: 'array', PUSH_PUBLIC_KEY: '', PUSH_PRIVATE_KEY: '' };
const artisan = (...a) => { const r = spawnSync(php, ['artisan', ...a], { cwd: root, env, stdio: 'inherit' }); if (r.status !== 0) throw new Error('artisan ' + a.join(' ')); };
const children = [];
const cleanup = () => { for (const c of children) try { c.kill(); } catch {} ; try { rmSync(work, { recursive: true, force: true }); } catch {} };
process.on('exit', cleanup);

// --- Connexion DevTools : une session par onglet (flatten). ---
class Browser {
  constructor(ws) { this.ws = ws; this.id = 0; this.pending = new Map(); this.waiters = [];
    ws.addEventListener('message', e => { const m = JSON.parse(e.data);
      if (m.id && this.pending.has(m.id)) { const { ok, ko } = this.pending.get(m.id); this.pending.delete(m.id); m.error ? ko(new Error(m.error.message)) : ok(m.result); }
      else if (m.method) this.waiters = this.waiters.filter(w => !(w.method === m.method && w.session === m.sessionId && (w.done(m.params), true))); }); }
  send(method, params = {}, sessionId) { const id = ++this.id; this.ws.send(JSON.stringify({ id, method, params, sessionId })); return new Promise((ok, ko) => this.pending.set(id, { ok, ko })); }
  once(method, session, ms = 20000) { return new Promise((done, fail) => { const w = { method, session, done }; this.waiters.push(w); setTimeout(() => fail(new Error('Délai dépassé : ' + method)), ms); }); }
}

async function main() {
  console.log('Base de démonstration…');
  artisan('migrate:fresh', '--force', '--seed');
  artisan('db:seed', '--class=Database\\Seeders\\DemoSeeder', '--force');

  console.log('Serveur local et navigateur…');
  // Le routeur de Laravel sert depuis le dossier courant : il doit être public/.
  children.push(spawn(php, ['-S', `127.0.0.1:${PORT}`, '-t', '.', join(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { cwd: join(root, 'public'), env, stdio: 'ignore' }));
  children.push(spawn(edge, ['--headless=new', `--remote-debugging-port=${DEBUG_PORT}`, `--user-data-dir=${join(work, 'edge')}`, '--hide-scrollbars', '--no-first-run', '--lang=fr-FR', 'about:blank'], { stdio: 'ignore' }));
  let version;
  for (let i = 0; i < 60 && !version; i++) { await sleep(500); try { version = await (await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/version`)).json(); await fetch(BASE + '/admin/connexion'); } catch { version = undefined; } }
  if (!version) throw new Error('Edge ou le serveur PHP ne répond pas.');
  const ws = new WebSocket(version.webSocketDebuggerUrl);
  await new Promise((ok, ko) => { ws.onopen = ok; ws.onerror = ko; });
  const b = new Browser(ws);
  mkdirSync(join(root, 'public/help'), { recursive: true });

  const sessions = {};
  async function page(role, mobile) {
    const key = role + (mobile ? '-mobile' : '');
    if (sessions[key]) return sessions[key];
    const { browserContextId } = await b.send('Target.createBrowserContext');
    const { targetId } = await b.send('Target.createTarget', { url: 'about:blank', browserContextId });
    const { sessionId: s } = await b.send('Target.attachToTarget', { targetId, flatten: true });
    await b.send('Page.enable', {}, s);
    await b.send('Emulation.setDeviceMetricsOverride', mobile ? { width: 390, height: 844, deviceScaleFactor: 2, mobile: true } : { width: 1280, height: 860, deviceScaleFactor: 1, mobile: false }, s);
    if (mobile) await b.send('Emulation.setUserAgentOverride', { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' }, s);
    await go(s, BASE + '/admin/connexion');
    await evaluate(s, `(() => { const f = document.querySelector('input[name=username]').form; f.username.value = ${JSON.stringify(ACCOUNTS[role])}; f.password.value = ${JSON.stringify(PASSWORD)}; f.submit(); })()`);
    await b.once('Page.loadEventFired', s);
    return sessions[key] = s;
  }
  async function go(s, url) { const loaded = b.once('Page.loadEventFired', s); await b.send('Page.navigate', { url }, s); await loaded; await sleep(350); }
  async function evaluate(s, expression) { const r = await b.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }, s); if (r.exceptionDetails) throw new Error((r.exceptionDetails.exception?.description || r.exceptionDetails.text) + " — page : " + (await b.send("Runtime.evaluate", { expression: "location.href+\" \"+document.title+\" \"+document.body.innerText.slice(0,300)", returnByValue: true }, s)).result.value); return r.result.value; }

  for (const shot of manifest) {
    const s = await page(shot.role, !!shot.mobile);
    await go(s, BASE + shot.path);
    for (const selector of shot.follow || []) {
      const href = await evaluate(s, `document.querySelector(${JSON.stringify(selector)})?.href || ''`);
      if (!href) throw new Error(`${shot.name} : aucun lien pour ${selector}`);
      await go(s, href);
    }
    // Animations coupées, bulles et notifications masquées : capture stable.
    await evaluate(s, `(() => { const st = document.createElement('style'); st.textContent = '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}.bubble-tray,.bubble-flash,.bell-pop{display:none!important}'; document.head.append(st); document.querySelectorAll('details.menu[open]').forEach(d => d.open = false); return document.fonts.ready.then(() => true); })()`);
    // prepare : petit script de mise en situation (ouvrir un panneau, cliquer sur un bouton), puis attente.
    if (shot.prepare) { await evaluate(s, `(() => { ${shot.prepare} })()`); await sleep(shot.wait || 500); }
    await sleep(300);
    let clip;
    if (shot.clip) {
      // L'élément est amené sous la barre du haut (collante), puis cadré dans la fenêtre visible.
      const box = await evaluate(s, `(() => { const el = document.querySelector(${JSON.stringify(shot.clip)}); if (!el) return null; let r = el.getBoundingClientRect(); if (!r.width || !r.height) return null;
        if (getComputedStyle(el).position !== 'fixed') { scrollTo(0, Math.max(0, r.top + scrollY - 88)); r = el.getBoundingClientRect(); }
        return { x: r.left + scrollX, y: r.top + scrollY, w: r.width, h: r.height, vy: scrollY, vh: innerHeight }; })()`);
      if (!box) throw new Error(`${shot.name} : élément ${shot.clip} introuvable ou masqué`);
      await sleep(200);
      const pad = 16, y = Math.max(box.vy, box.y - pad);
      clip = { x: Math.max(0, box.x - pad), y, width: box.w + pad * 2, height: Math.min(box.h + pad * 2, box.vy + box.vh - y, shot.height || 9999), scale: 1 };
    } else if (shot.height) {
      clip = { x: 0, y: 0, width: shot.mobile ? 390 : 1280, height: shot.height, scale: 1 };
    }
    const { data } = await b.send('Page.captureScreenshot', { format: 'webp', quality: 82, ...(clip ? { clip } : {}) }, s);
    writeFileSync(join(arg('out', join(root, 'public/help')), shot.name + '.webp'), Buffer.from(data, 'base64'));
    console.log('  ✓', shot.name);
  }
  ws.close();
}

main().then(() => { cleanup(); process.exit(0); }, e => { console.error(e.stack || e); cleanup(); process.exit(1); });
