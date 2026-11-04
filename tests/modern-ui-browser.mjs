import assert from 'node:assert/strict';
import {readFile, writeFile, mkdir} from 'node:fs/promises';

const output = 'storage/framework/testing/modern-ui';
await mkdir(output, {recursive: true});
const pointer = JSON.parse(await readFile('storage/framework/testing/independent-publication-current.json', 'utf8'));
const fixture = JSON.parse(await readFile(pointer.manifest, 'utf8'));
assert(fixture.database.replaceAll('\\', '/').includes('/storage/framework/testing/independent-publication-'));
const debug = 'http://127.0.0.1:9260';
for (let n = 0; n < 80; n++) {
    try { if ((await fetch(debug + '/json/version')).ok) break; } catch {}
    await new Promise(resolve => setTimeout(resolve, 150));
}
const target = await (await fetch(debug + '/json/new?about:blank', {method: 'PUT'})).json();
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, {once: true});
    socket.addEventListener('error', reject, {once: true});
});
let nextId = 0;
const pending = new Map(), exceptions = [], checks = [];
socket.addEventListener('message', event => {
    const data = JSON.parse(event.data);
    if (data.id) {
        const action = pending.get(data.id);
        if (action) {
            pending.delete(data.id);
            clearTimeout(action.timer);
            data.error ? action.reject(new Error('CDP ' + action.method)) : action.resolve(data.result);
        }
    }
    if (data.method === 'Runtime.exceptionThrown') exceptions.push(data.params.exceptionDetails.text);
});
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++nextId;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error('CDP timeout ' + method)); }, 20000);
        pending.set(id, {resolve, reject, timer, method});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const data = await command('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    if (data.exceptionDetails) throw new Error('Browser evaluation failed');
    return data.result.value;
}
async function wait(expression) {
    const end = Date.now() + 25000;
    while (Date.now() < end) {
        if (await evaluate(expression)) return;
        await new Promise(resolve => setTimeout(resolve, 120));
    }
    throw new Error('Browser state did not become ready');
}
async function go(base, path) {
    await command('Page.navigate', {url: base + path});
    await wait('document.readyState === "complete" && location.pathname === ' + JSON.stringify(path));
}
async function viewport(mobile) {
    await command('Emulation.setDeviceMetricsOverride', {width: mobile ? 390 : 1440, height: mobile ? 844 : 1000, deviceScaleFactor: 1, mobile});
}
async function screenshot(name) {
    await evaluate('document.fonts.ready');
    await new Promise(resolve => setTimeout(resolve, 300));
    const shot = await command('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    await writeFile(output + '/' + name + '.png', Buffer.from(shot.data, 'base64'));
}
async function noOverflow(name) {
    const result = await evaluate('({width:innerWidth,scroll:document.documentElement.scrollWidth})');
    assert(result.scroll <= result.width + 1, 'Horizontal overflow: ' + name);
    checks.push({name, status: 'PASS'});
}
try {
    await command('Page.enable');
    await command('Runtime.enable');
    await command('Network.clearBrowserCookies');
    await viewport(false);
    for (const [path, name] of [['/', 'home-desktop'], ['/peta', 'map-desktop'], ['/admin/login', 'login-desktop']]) {
        await go('http://127.0.0.1:8000', path);
        if (path === '/peta') {
            await wait('document.getElementById("map-count").textContent.includes(" dari ")');
            await wait('document.querySelectorAll(".leaflet-marker-icon").length > 0');
        }
        await noOverflow(name);
        await screenshot(name);
    }
    await viewport(true);
    for (const [path, name] of [['/', 'home-mobile'], ['/peta', 'map-mobile'], ['/admin/login', 'login-mobile']]) {
        await go('http://127.0.0.1:8000', path);
        if (path === '/peta') {
            await wait('document.getElementById("map-count").textContent.includes(" dari ")');
            assert.equal(await evaluate('document.querySelectorAll("[data-public-category]").length'), 2);
        }
        await noOverflow(name);
        await screenshot(name);
    }
    await evaluate('document.querySelector("[data-nav-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("[data-nav-toggle]").getAttribute("aria-expanded")'), 'true');
    await command('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Escape', code: 'Escape'});
    assert.equal(await evaluate('document.querySelector("[data-nav-toggle]").getAttribute("aria-expanded")'), 'false');
    checks.push({name: 'public_mobile_menu_keyboard', status: 'PASS'});
    await viewport(false);
    await go('http://127.0.0.1:8140', '/admin/login');
    await evaluate('document.querySelector("[name=email]").value=' + JSON.stringify(fixture.email) + ';document.querySelector("[name=password]").value=' + JSON.stringify(fixture.password) + ';document.querySelector("form").requestSubmit()');
    await wait('location.pathname === "/admin" && document.readyState === "complete"');
    await noOverflow('admin-desktop');
    await screenshot('admin-desktop');
    await go('http://127.0.0.1:8140', '/admin/publication');
    assert.equal(await evaluate('document.querySelectorAll(".publication-category input[type=checkbox]").length'), 2);
    await noOverflow('publication-desktop');
    await screenshot('publication-desktop');
    const original = await evaluate('document.querySelector(".publication-category input").checked');
    await evaluate('document.querySelector(".publication-category input").click()');
    await wait('document.querySelector(".publication-category .publication-feedback").textContent === "Tersimpan."');
    assert.equal(await evaluate('document.querySelector(".publication-category input").checked'), !original);
    await go('http://127.0.0.1:8140', '/admin/publication');
    assert.equal(await evaluate('document.querySelector(".publication-category input").checked'), !original);
    await evaluate('document.querySelector(".publication-category input").click()');
    await wait('document.querySelector(".publication-category .publication-feedback").textContent === "Tersimpan."');
    checks.push({name: 'publication_switch_save_reload_restore_isolated_database', status: 'PASS'});
    for (const path of ['/admin/houses', '/admin/houses/create', '/admin/facilities', '/admin/layers', '/admin/features', '/admin/features/create']) {
        await go('http://127.0.0.1:8140', path);
        await noOverflow(path + '_desktop');
    }
    await viewport(true);
    for (const path of ['/admin', '/admin/publication', '/admin/houses', '/admin/houses/create', '/admin/facilities', '/admin/layers', '/admin/features', '/admin/features/create']) {
        await go('http://127.0.0.1:8140', path);
        await noOverflow(path + '_mobile');
    }
    await go('http://127.0.0.1:8140', '/admin');
    await evaluate('document.querySelector("[data-sidebar-toggle]").click()');
    assert.equal(await evaluate('document.querySelector("[data-sidebar-toggle]").getAttribute("aria-expanded")'), 'true');
    assert(await evaluate('[...document.querySelectorAll(".admin-nav a")].every(a=>getComputedStyle(a).display!=="none" && parseFloat(getComputedStyle(a).fontSize)>0)'));
    assert(await evaluate('[...document.querySelectorAll(".admin-nav a span")].every(span=>span.getBoundingClientRect().width>120)'));
    await screenshot('admin-mobile-menu');
    await command('Input.dispatchKeyEvent', {type: 'keyDown', key: 'Escape', code: 'Escape'});
    assert.equal(await evaluate('document.querySelector("[data-sidebar-toggle]").getAttribute("aria-expanded")'), 'false');
    assert(await evaluate('document.activeElement === document.querySelector("[data-sidebar-toggle]")'));
    checks.push({name: 'admin_mobile_all_links_escape_focus_restore', status: 'PASS'});
    await screenshot('admin-mobile');
    assert.deepEqual(exceptions, []);
    await writeFile(output + '/results.json', JSON.stringify({status: 'PASS', checks, exceptions, public_server: 'http://127.0.0.1:8000', admin_database: 'isolated SQLite fixture'}, null, 2));
    console.log(JSON.stringify({status: 'PASS', checks, exceptions}));
} finally {
    await fetch(debug + '/json/close/' + target.id);
    socket.close();
}
