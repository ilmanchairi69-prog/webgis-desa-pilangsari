import assert from 'node:assert/strict';
import {readFile, writeFile} from 'node:fs/promises';

const pointer = JSON.parse(await readFile('storage/framework/testing/independent-publication-current.json', 'utf8'));
const fixture = JSON.parse(await readFile(pointer.manifest, 'utf8'));
assert.match(fixture.database.replaceAll('\\', '/'), /\/storage\/framework\/testing\/independent-publication-[^/]+\/fixture\.sqlite$/);
assert.equal(fixture.map_layers, 0);
assert.equal(fixture.map_features, 0);
assert.equal(fixture.house_map_feature_id, null);
const base = fixture.base_url;
let debugReady = false;
for (let attempt=0;attempt<50;attempt++) {
    try {const response=await fetch(fixture.debug_url+'/json/version');if(response.ok){debugReady=true;break;}} catch {}
    await new Promise(resolve=>setTimeout(resolve,200));
}
assert(debugReady, 'Dedicated Chrome debugging endpoint must be available');
const target = await (await fetch(fixture.debug_url+'/json/new?about:blank', {method:'PUT'})).json();
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {socket.addEventListener('open', resolve, {once:true});socket.addEventListener('error', reject, {once:true});});
let nextId = 0;
const pending = new Map(), exceptions = [], scenarios = [], screenshotReadiness = [];
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (message.id) {
        const action = pending.get(message.id);
        if (action) {pending.delete(message.id);clearTimeout(action.timer);message.error ? action.reject(new Error('CDP failure '+action.method+': '+message.error.message)) : action.resolve(message.result);}
    }
    if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
});
function command(method, params = {}) {
    return new Promise((resolve, reject) => {
        const id = ++nextId;
        const timer = setTimeout(() => {pending.delete(id);reject(new Error('CDP timeout: '+method));}, 20000);
        pending.set(id, {resolve, reject, timer, method});socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const value = await command('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
    if (value.exceptionDetails) throw new Error('Browser evaluation failed');
    return value.result.value;
}
async function wait(expression) {
    const end = Date.now()+25000;
    while (Date.now()<end) {if (await evaluate(expression)) return;await new Promise(resolve=>setTimeout(resolve, 120));}
    throw new Error('Browser state timeout: '+expression);
}
async function navigate(path) {
    await command('Page.navigate', {url:base+path});
    await wait('location.pathname === '+JSON.stringify(path)+' && document.readyState === "complete"');
}
async function api(path = '/api/map/public') {
    return await evaluate('fetch('+JSON.stringify(path)+',{cache:"no-store"}).then(async r=>({status:r.status,body:await r.json()}))');
}
async function post(path, data = {}) {
    const result = await evaluate('fetch('+JSON.stringify(path)+', {method:"POST",headers:{"X-CSRF-TOKEN":document.querySelector("meta[name=csrf-token]").content,"Accept":"application/json","Content-Type":"application/json"},body:JSON.stringify('+JSON.stringify(data)+')}).then(async r=>({status:r.status,text:await r.text()}))');
    assert.equal(result.status, 200, 'Workflow POST must succeed: '+path);
}
function assertPrivacy(value) {
    const text = JSON.stringify(value);
    for (const secret of ['PRIVATE-', '9999999999999999', '1991-02-03', 'head_name', 'head_of_family_name', 'card_number', 'family_members', 'rejection_note', 'verified_by', 'created_by']) assert(!text.includes(secret), 'Public privacy failure');
}
function assertCoordinates(feature, expected) {
    assert.equal(feature.geometry.type, 'Point');
    assert(Math.abs(feature.geometry.coordinates[0]-expected.longitude)<0.0000001);
    assert(Math.abs(feature.geometry.coordinates[1]-expected.latitude)<0.0000001);
}
async function screenshot(name) {
    await evaluate('window.scrollTo({top:Math.max(0,document.getElementById("public-map").getBoundingClientRect().top+scrollY-140),behavior:"instant"});new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
    const imagery = await evaluate(`(async () => {
        const deadline = Date.now()+15000;
        let loaded = 0;
        while (Date.now()<deadline) {
            const tiles = [...document.querySelectorAll('#public-map img.leaflet-tile')];
            loaded = tiles.filter(image=>image.complete && image.naturalWidth>0 && image.classList.contains('leaflet-tile-loaded')).length;
            const loading = tiles.filter(image=>!image.complete).length;
            if (loaded>=4 && loading===0) return {loaded,loading,ready:true};
            await new Promise(resolve=>setTimeout(resolve,150));
        }
        return {loaded,ready:false};
    })()`);
    screenshotReadiness.push({screenshot:name,...imagery});
    const shot = await command('Page.captureScreenshot', {format:'png',captureBeyondViewport:false});
    await writeFile(fixture.directory+'/'+name+'.png', Buffer.from(shot.data, 'base64'));
}
try {
    await command('Page.enable');await command('Runtime.enable');await command('Network.clearBrowserCookies');
    await command('Page.addScriptToEvaluateOnNewDocument', {source:`
        Object.defineProperty(window, 'PilangsariCluster', {
            configurable:true, get(){return this.__testClusterLibrary;},
            set(value){const original=value.mount;value.mount=function(map,items,options){const result=original(map,items,options);window.__publicationMap=map;window.__publicationCluster=result;return result;};this.__testClusterLibrary=value;}
        });
    `});
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await navigate('/admin/login');
    await evaluate('document.querySelector("[name=email]").value='+JSON.stringify(fixture.email)+';document.querySelector("[name=password]").value='+JSON.stringify(fixture.password)+';document.querySelector("form").requestSubmit()');
    await wait('location.pathname === "/admin"');
    assert.equal((await api()).body.features.length, 0, 'Unverified/draft/archived/invalid objects must stay hidden');
    await post('/admin/houses/'+fixture.house.id+'/verify');
    assert.equal((await api()).body.features.length, 0, 'Verified draft house must stay hidden');
    await post('/admin/houses/'+fixture.house.id+'/publish', {publication_declaration:true});
    const houseOnly = await api();
    assert.equal(houseOnly.body.features.length,1);
    assert.equal(houseOnly.body.features[0].properties.kind,'house');
    assertCoordinates(houseOnly.body.features[0],fixture.house);
    await post('/admin/facilities/'+fixture.facility.id+'/publish');
    const publicResponse = await api();
    const versionedResponse = await api('/api/v1/map/objects');
    for (const response of [publicResponse,versionedResponse]) {
        assert.equal(response.status,200);assert.equal(response.body.features.length,2);assertPrivacy(response.body);
        assertCoordinates(response.body.features.find(f=>f.properties.kind==='house'),fixture.house);
        assertCoordinates(response.body.features.find(f=>f.properties.kind==='facility'),fixture.facility);
    }
    await writeFile(fixture.directory+'/public-api-response.json',JSON.stringify(publicResponse,null,2));
    await writeFile(fixture.directory+'/versioned-api-response.json',JSON.stringify(versionedResponse,null,2));
    scenarios.push({name:'verify_publish_without_layer_or_feature',status:'PASS'});
    await navigate('/peta');
    await wait('document.getElementById("map-count").textContent === "2 dari 2 lokasi" && !!window.__publicationMap');
    await evaluate('window.__publicationMap.fitBounds([['+fixture.house.latitude+','+fixture.house.longitude+'],['+fixture.facility.latitude+','+fixture.facility.longitude+']],{padding:[70,70],maxZoom:18,animate:false}); true');
    await wait('window.__publicationCluster.layer.getLayers().length === 2 && document.querySelectorAll(".cluster-single").length === 2');
    const markers = await evaluate('window.__publicationCluster.layer.getLayers().map(marker=>({lat:marker.getLatLng().lat,lng:marker.getLatLng().lng,popup:marker.getPopup().getContent(),kind:marker.getElement().querySelector(".facility")?"facility":"house",color:getComputedStyle(marker.getElement().querySelector(".cluster-single")).backgroundColor}))');
    for(const marker of markers) {
        const expected=fixture[marker.kind];assert(Math.abs(marker.lat-expected.latitude)<0.0000001);assert(Math.abs(marker.lng-expected.longitude)<0.0000001);assertPrivacy(marker.popup);
        assert.equal(marker.color,marker.kind==='house'?'rgb(28, 121, 64)':'rgb(240, 201, 0)');
    }
    await writeFile(fixture.directory+'/marker-coordinates.json',JSON.stringify(markers,null,2));
    await screenshot('desktop-map');
    for(const kind of ['house','facility']) {
        await evaluate('window.__publicationCluster.layer.getLayers().find(marker=>'+(kind==='facility'?'!!':'!')+'marker.getElement().querySelector(".facility")).openPopup(); true');
        await wait('!!document.querySelector(".leaflet-popup-content")');
        assertPrivacy(await evaluate('document.querySelector(".leaflet-popup-content").textContent'));
        await screenshot('desktop-'+kind+'-popup');
    }
    scenarios.push({name:'actual_leaflet_marker_coordinates_colors_and_private_popups',status:'PASS'});
    await evaluate('window.__publicationMap.closePopup();document.querySelector("[data-public-category=houses]").click()');
    assert.equal(await evaluate('document.getElementById("map-count").textContent'),'1 dari 2 lokasi');
    assert.equal((await api()).body.features.length,2,'Frontend category filter must not change persistent publication');
    await evaluate('document.querySelector("[data-public-category=houses]").click()');
    await command('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await navigate('/peta');
    await wait('document.getElementById("map-count").textContent === "2 dari 2 lokasi"');
    await evaluate('window.__publicationMap.fitBounds([['+fixture.house.latitude+','+fixture.house.longitude+'],['+fixture.facility.latitude+','+fixture.facility.longitude+']],{padding:[50,50],maxZoom:18,animate:false}); true');
    await wait('document.querySelectorAll(".cluster-single").length === 2');
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);
    await screenshot('mobile-map');
    scenarios.push({name:'desktop_mobile_and_frontend_only_category_filters',status:'PASS'});
    await navigate('/admin');
    await post('/admin/houses/'+fixture.house.id+'/unpublish');
    assert.equal((await api()).body.features.length,1);
    await post('/admin/facilities/'+fixture.facility.id+'/unpublish');
    assert.equal((await api()).body.features.length,0);
    scenarios.push({name:'independent_withdrawals_hide_each_marker',status:'PASS'});
    assert.deepEqual(exceptions,[]);
    const result={status:'PASS',map_layers:0,map_features:0,house_map_feature_id:null,scenarios,exceptions,screenshots:['desktop-map.png','desktop-house-popup.png','desktop-facility-popup.png','mobile-map.png'],marker_coordinates:markers,screenshot_readiness:screenshotReadiness};
    await writeFile(fixture.directory+'/browser-results.json',JSON.stringify(result,null,2));
    console.log(JSON.stringify(result));
} finally {
    await fetch(fixture.debug_url+'/json/close/'+target.id);socket.close();
}
