import assert from 'node:assert/strict';
import {readFile, writeFile, mkdir} from 'node:fs/promises';

const credentials = JSON.parse(await readFile('storage/framework/testing/hardening-browser-credentials.json', 'utf8'));
const base = credentials.base_url;
assert.equal(base, 'http://127.0.0.1:8101', 'Workflow may only target the isolated fixture server');
assert.match(credentials.database, /hardening.*sqlite/i, 'Workflow requires a dedicated test database');
const browser = process.env.AUDIT_BROWSER_URL || 'http://127.0.0.1:9230';
const output = 'storage/framework/testing/admin-workflow-browser';
const target = await (await fetch(browser+'/json/new?about:blank', {method:'PUT'})).json();
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, {once:true});
    socket.addEventListener('error', reject, {once:true});
});
let nextId=0;
const pending=new Map(), errors=[], results=[];
socket.addEventListener('message', event => {
    const data=JSON.parse(event.data);
    if (data.id) {
        const entry=pending.get(data.id);
        if (!entry) return;
        pending.delete(data.id); clearTimeout(entry.timeout);
        data.error ? entry.reject(new Error(data.error.message)) : entry.resolve(data.result);
    }
    if (data.method==='Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.text);
    if (data.method==='Runtime.consoleAPICalled' && data.params.type==='error') errors.push('console.error');
    if (data.method==='Page.javascriptDialogOpening') command('Page.handleJavaScriptDialog', {accept:true}).catch(error => errors.push(error.message));
});
function command(method, params={}) {
    return new Promise((resolve, reject) => {
        const id=++nextId, timeout=setTimeout(() => {pending.delete(id); reject(new Error('Timeout: '+method));}, 15000);
        pending.set(id, {resolve,reject,timeout}); socket.send(JSON.stringify({id,method,params}));
    });
}
async function evaluate(expression) {
    const value=await command('Runtime.evaluate', {expression,returnByValue:true,awaitPromise:true});
    if (value.exceptionDetails) throw new Error('Browser expression failed: '+value.exceptionDetails.text);
    return value.result.value;
}
async function waitFor(expression) {
    const deadline=Date.now()+25000;
    while (Date.now()<deadline) {
        if (await evaluate(expression)) return;
        await new Promise(resolve=>setTimeout(resolve,200));
    }
    const state=await evaluate("({path:location.pathname,title:document.title,validation:document.querySelector('.form-alert,.flash-error')?.textContent?.slice(0,160)})");
    throw new Error('Browser condition timed out: '+JSON.stringify(state));
}
async function navigate(path) {
    await command('Page.navigate', {url:base+path});
    await waitFor(`location.pathname===${JSON.stringify(path.split('?')[0])} && document.readyState==='complete'`);
}
async function fill(selector, value) {
    await waitFor(`!!document.querySelector(${JSON.stringify(selector)})`);
    await evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});if(!el)throw new Error('Field missing');el.value=${JSON.stringify(value)};el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));})()`);
}
async function click(selector) {
    await evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
}
async function submit(selector, destination) {
    await evaluate(`document.querySelector(${JSON.stringify(selector)}).requestSubmit()`);
    await waitFor(`location.pathname===${JSON.stringify(destination)} && document.readyState==='complete'`);
}
async function login(role) {
    await command('Network.clearBrowserCookies');
    await navigate('/admin/login');
    await fill('input[name=email]',credentials.accounts[role].email);
    await fill('input[name=password]',credentials.accounts[role].password);
    await submit('form[action$="/admin/login"]','/admin');
}
async function api(path) {
    return evaluate(`fetch(${JSON.stringify(path)},{headers:{Accept:'application/json'},cache:'no-store'}).then(async response=>({status:response.status,body:await response.json()}))`);
}
async function assertResponsive(label) {
    for (const view of [{name:'desktop',width:1440,height:900,mobile:false},{name:'laptop',width:1366,height:768,mobile:false},{name:'tablet',width:768,height:1024,mobile:true},{name:'mobile',width:390,height:844,mobile:true}]) {
        await command('Emulation.setDeviceMetricsOverride', {...view,deviceScaleFactor:1});
        await new Promise(resolve=>setTimeout(resolve,100));
        assert(!(await evaluate('document.documentElement.scrollWidth>innerWidth+1')), label+' horizontal overflow: '+view.name);
        results.push({scenario:label,viewport:view.name,overflow:false});
    }
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
}
try {
    await mkdir(output,{recursive:true});
    await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');
    await login('rt');
    await assertResponsive('RT dashboard');
    const forbidden=await api('/admin/houses/'+credentials.other_house_id+'/edit');
    assert.equal(forbidden.status,403,'RT cannot read another RT record');
    assert.equal((await api('/admin/verification')).status,403,'RT cannot enter verification');
    results.push({scenario:'RT direct cross-RT and verification access',result:'403'});
    await navigate('/admin/houses/create');
    await assertResponsive('House wizard');
    await fill('#house_label','Bangunan pengujian browser');
    await fill('#address','Alamat sintetis terisolasi');
    await fill('#latitude','-7.3574198'); await fill('#longitude','110.9874323');
    await click('#wizard-next');
    await fill('[data-head-name]','Kepala Keluarga Sintetis');
    await click('#wizard-next');
    await fill('.member-name','Anggota Sintetis');
    await fill('.member-relationship','Anak');
    await fill('.member-dob','2010-01-01');
    await fill('.member-gender','Perempuan');
    await click('#wizard-next');
    await click('input[name=public_visible]');
    await click('#wizard-submit');
    await waitFor("location.pathname==='/admin/houses' && document.readyState==='complete'");
    assert.equal(await evaluate("document.querySelectorAll('.flash-error').length"),0,'Creation has no validation errors');
    const createdCode=await evaluate(String.raw`document.querySelector('.flash-success,.alert-success')?.textContent.match(/BGN-[A-Z0-9-]+/)?.[0]`);
    assert(createdCode,'Creation flash identifies the new building');
    const ownLink=await evaluate(String.raw`[...document.querySelectorAll('tr')].find(row=>row.textContent.includes(${JSON.stringify(createdCode)}))?.querySelector('a[href]')?.getAttribute('href')`);
    assert(ownLink,'Created house has a detail link');
    const houseId=Number(ownLink.match(/(\d+)$/)[1]);
    assert.notEqual(houseId,credentials.other_house_id);
    await navigate('/admin/houses/'+houseId);
    assert(await evaluate("document.body.textContent.includes('Kepala Keluarga Sintetis') && document.body.textContent.includes('Anggota Sintetis')"));
    await navigate('/admin/houses/'+houseId+'/edit');
    await assertResponsive('House edit');
    await fill('#house_label','Bangunan pengujian diperbarui');
    await fill('input[name$="[head_name]"]','Kepala Keluarga diperbarui');
    await fill('.family-member-name','Anggota diperbarui');
    await fill('.family-member-relationship','Saudara');
    await fill('.family-member-dob','2011-02-03');
    await fill('.family-member-gender','Laki-laki');
    await submit('form[action$="/admin/houses/'+houseId+'"]','/admin/houses/'+houseId);
    assert(await evaluate("document.body.textContent.includes('Bangunan pengujian diperbarui')"));
    await navigate('/admin/houses/'+houseId+'/edit');
    assert(await evaluate("document.querySelector('.family-member-name').value==='Anggota diperbarui' && document.querySelector('.family-member-relationship').value==='Saudara' && document.querySelector('.family-member-dob').value==='2011-02-03' && document.querySelector('.family-member-gender').value==='Laki-laki'"),'Member fields persist through the real form and database');
    results.push({scenario:'RT create household/member, edit house/head/member and retain private data',result:'PASS'});
    await login('super');
    await assertResponsive('Super Admin dashboard');
    await navigate('/admin/layers');
    const editLayer=await evaluate(String.raw`[...document.querySelectorAll('a[href]')].find(a=>/\/admin\/layers\/\d+\/edit$/.test(a.getAttribute('href')))?.getAttribute('href')`);
    assert(editLayer,'House layer is visible to Super Admin');
    const layerPath=new URL(editLayer,base).pathname;
    await navigate(layerPath);
    await fill('select[name=status]','published');
    await evaluate("document.querySelector('input[type=checkbox][name=is_public]').checked=true");
    await submit('form[action*="/admin/layers/"]','/admin/layers');
    await navigate('/admin/verification');
    await assertResponsive('Verification');
    await submit('form[action$="/houses/'+houseId+'/verify"]','/admin/verification');
    await waitFor(`!!document.querySelector('form[action$="/houses/${houseId}/publish"]')`);
    await evaluate(`document.querySelector('form[action$="/houses/${houseId}/publish"]').closest('details').open=true`);
    await click('form[action$="/houses/'+houseId+'/publish"] input[name=publication_declaration]');
    await submit('form[action$="/houses/'+houseId+'/publish"]','/admin/verification');
    let publicData=await api('/api/map/public');
    assert.equal(publicData.status,200);
    const house=publicData.body.features.find(feature=>feature.properties.kind==='house' && feature.properties.id===houseId);
    assert(house,'Eligible published house appears in public API');
    const serialized=JSON.stringify(publicData.body);
    assert(!serialized.includes('Kepala Keluarga Sintetis')&&!serialized.includes('Anggota Sintetis')&&!serialized.includes('Kepala Keluarga diperbarui')&&!serialized.includes('Anggota diperbarui')&&!serialized.includes('Alamat sintetis'),'Public response excludes private family/address');
    await navigate('/peta');
    await waitFor("document.getElementById('map-count') && !document.getElementById('map-count').textContent.includes('Memuat')");
    await fill('#map-category','Rumah warga');
    await waitFor("document.querySelectorAll('.leaflet-marker-icon').length>0");
    await click('.leaflet-marker-icon');
    await waitFor("!!document.querySelector('.leaflet-popup-content')");
    assert(await evaluate("document.querySelector('.leaflet-popup-content').textContent.includes('Lokasi rumah')"));
    assert(!(await evaluate("document.querySelector('.leaflet-popup-content').textContent.includes('Kepala Keluarga Sintetis')")));
    results.push({scenario:'Super Admin layer settings, verify, publish, public API and Leaflet popup',result:'PASS'});
    await navigate('/admin/verification');
    await submit('form[action$="/houses/'+houseId+'/unpublish"]','/admin/verification');
    publicData=await api('/api/map/public');
    assert(!publicData.body.features.some(feature=>feature.properties.kind==='house' && feature.properties.id===houseId),'Withdrawn house disappears from public API');
    await navigate('/admin/audit-logs');
    assert(await evaluate("document.body.textContent.includes('house.published') && document.body.textContent.includes('house.unpublished')"),'Publication and withdrawal audit visible');
    results.push({scenario:'Super Admin withdrawal removes public feature and records audit',result:'PASS'});
    assert.deepEqual(errors,[],'No JavaScript exceptions or console errors on the workflow');
    const summary={database:'isolated SQLite fixture',results,errors};
    await writeFile(output+'/results.json',JSON.stringify(summary,null,2));
    console.log(JSON.stringify(summary,null,2));
} finally {
    await fetch(browser+'/json/close/'+target.id);
    socket.close();
}
