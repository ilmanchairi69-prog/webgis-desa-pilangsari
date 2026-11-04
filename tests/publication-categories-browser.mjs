import assert from 'node:assert/strict';
import {readFile, writeFile} from 'node:fs/promises';
const base = 'http://127.0.0.1:8124';
const credentials = JSON.parse(await readFile('storage/framework/testing/publication-browser-credentials.json','utf8'));
assert(credentials.database.replaceAll('\\','/').endsWith('/storage/framework/testing/publication-browser.sqlite'), 'Only isolated browser DB is allowed');
const target = await (await fetch('http://127.0.0.1:9242/json/new?about:blank',{method:'PUT'})).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
let id=0;const pending=new Map();const exceptions=[];const results=[];
ws.addEventListener('message',event=>{
    const data=JSON.parse(event.data);
    if(data.id){const entry=pending.get(data.id);if(entry){pending.delete(data.id);clearTimeout(entry.timer);data.error?entry.reject(new Error('CDP command failed')):entry.resolve(data.result);}}
    if(data.method==='Runtime.exceptionThrown')exceptions.push(data.params.exceptionDetails.text);
});
function command(method,params={}){
    return new Promise((resolve,reject)=>{const ident=++id;const timer=setTimeout(()=>{pending.delete(ident);reject(new Error('Browser command timeout: '+method));},20000);pending.set(ident,{resolve,reject,timer});ws.send(JSON.stringify({id:ident,method,params}));});
}
async function evaluate(expression){
    const r=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
    if(r.exceptionDetails)throw new Error('Browser evaluation exception');
    return r.result.value;
}
async function wait(expression){
    const until=Date.now()+20000;
    while(Date.now()<until){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,150));}
    throw new Error('Browser state condition timed out');
}
async function navigate(path){
    await command('Page.navigate',{url:base+path});
    await wait('location.pathname === '+JSON.stringify(path)+' && document.readyState === "complete"');
}
async function toggle(key){
    await evaluate('document.querySelector("[data-url$=\\"/'+key+'\\"] input").click()');
    await wait('document.querySelector("[data-url$=\\"/'+key+'\\"] .publication-feedback").textContent === "Tersimpan."');
}
async function publicCount(){
    return await evaluate('fetch("/api/map/public",{cache:"no-store"}).then(r=>r.json()).then(d=>d.features.length)');
}
try{
    await command('Page.enable');await command('Runtime.enable');await command('Network.clearBrowserCookies');
    await command('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await navigate('/admin/login');
    await evaluate('document.querySelector("[name=email]").value='+JSON.stringify(credentials.email)+';document.querySelector("[name=password]").value='+JSON.stringify(credentials.password)+';document.querySelector("form").requestSubmit()');
    await wait('location.pathname === "/admin"');
    await navigate('/admin/publication');
    assert.equal(await evaluate('document.querySelectorAll(".publication-category input[type=checkbox]").length'),2);
    assert.equal(await publicCount(),0);
    await toggle('houses');assert.equal(await publicCount(),1);
    assert.equal(await evaluate('document.querySelector("[data-url$=\\"/facilities\\"] input").checked'),false);
    await toggle('facilities');assert.equal(await publicCount(),2);
    await navigate('/admin/publication');
    assert.equal(await evaluate('[...document.querySelectorAll(".publication-category input")].every(i=>i.checked)'),true);
    results.push({scenario:'checkbox_save_reload_independence',status:'PASS'});
    await navigate('/peta');
    await wait('document.getElementById("map-count").textContent === "2 dari 2 lokasi"');
    assert.equal(await evaluate('document.querySelectorAll("[data-public-category]").length'),2);
    assert.equal(await evaluate('!!document.getElementById("map-category") || !!document.getElementById("map-layers")'),false);
    await evaluate('document.querySelector("[data-public-category=houses]").click()');
    assert.equal(await evaluate('document.getElementById("map-count").textContent'),'1 dari 2 lokasi');
    await wait('!!document.querySelector(".cluster-single.facility")');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cluster-single.facility")).backgroundColor'),'rgb(240, 201, 0)');
    await evaluate('document.querySelector(".leaflet-marker-icon").click()');
    assert(await evaluate('document.querySelector(".leaflet-popup-content").textContent.includes("Browser Facility")'));
    await evaluate('document.querySelector("[data-public-category=houses]").click();document.querySelector("[data-public-category=facilities]").click()');
    assert.equal(await evaluate('document.getElementById("map-count").textContent'),'1 dari 2 lokasi');
    await wait('!!document.querySelector(".cluster-single:not(.facility)")');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".cluster-single:not(.facility)")).backgroundColor'),'rgb(28, 121, 64)');
    assert.equal(await evaluate('fetch("/api/map/public",{cache:"no-store"}).then(r=>r.text()).then(t=>t.includes("PRIVATE-"))'),false);
    results.push({scenario:'fixed_filters_green_yellow_popups_and_privacy',status:'PASS'});
    for(const viewport of [{name:'desktop',width:1440,height:900,mobile:false},{name:'mobile',width:390,height:844,mobile:true}]){
        await command('Emulation.setDeviceMetricsOverride',{...viewport,deviceScaleFactor:1});
        await navigate('/peta');await wait('document.getElementById("map-count").textContent === "2 dari 2 lokasi"');
        assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth+1'),false);
        results.push({scenario:viewport.name+'_map_no_overflow',status:'PASS'});
    }
    await navigate('/admin/publication');
    await toggle('houses');assert.equal(await publicCount(),1);
    assert.equal(await evaluate('document.querySelector("[data-url$=\\"/facilities\\"] input").checked'),true);
    const csrfStatus=(await fetch(base+"/admin/publication/facilities",{method:"PATCH",headers:{"Accept":"application/json","Content-Type":"application/json"},body:JSON.stringify({is_public:false})})).status;
    assert.equal(csrfStatus,419);
    results.push({scenario:'unpublish_independence_and_csrf',status:'PASS'});
    assert.equal(exceptions.length,0);
    await writeFile('storage/framework/testing/publication-browser-results.json',JSON.stringify({status:'PASS',results,exceptions,csrfStatus},null,2));
    console.log(JSON.stringify({status:'PASS',results,exceptions,csrfStatus}));
}finally{
    await fetch('http://127.0.0.1:9242/json/close/'+target.id);ws.close();
}
