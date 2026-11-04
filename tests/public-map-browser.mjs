import {writeFile, mkdir} from 'node:fs/promises';
import assert from 'node:assert/strict';

const base = process.env.AUDIT_BASE_URL || 'http://127.0.0.1:8097';
const browser = process.env.AUDIT_BROWSER_URL || 'http://127.0.0.1:9228';
const output = process.env.AUDIT_OUTPUT || 'storage/framework/testing/release-browser';
const viewports = [{name:'desktop',width:1440,height:900,mobile:false},{name:'laptop',width:1366,height:768,mobile:false},{name:'tablet',width:768,height:1024,mobile:true},{name:'mobile',width:390,height:844,mobile:true}];
const target = await (await fetch(browser+'/json/new?about:blank',{method:'PUT'})).json();
assert(target, 'Browser audit target must exist');
const socket = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {socket.addEventListener('open',resolve,{once:true}); socket.addEventListener('error',reject,{once:true});});
let nextId=0;
const pending = new Map();
const errors=[];
const failedRequests=[];
const httpErrors=[];
let scenario='live';
socket.addEventListener('message', event => {
    const data=JSON.parse(event.data);
    if (data.id) {
        const entry=pending.get(data.id); if (!entry) return;
        pending.delete(data.id); clearTimeout(entry.timeout);
        data.error ? entry.reject(new Error(JSON.stringify(data.error))) : entry.resolve(data.result);
    }
    if (data.method === 'Runtime.exceptionThrown') errors.push(data.params.exceptionDetails.text+': '+(data.params.exceptionDetails.exception?.description || ''));
    if (data.method === 'Runtime.consoleAPICalled' && data.params.type === 'error') errors.push(data.params.args.map(arg=>arg.value || arg.description).join(' '));
    if (data.method === 'Network.loadingFailed' && !data.params.canceled) failedRequests.push(data.params.errorText);
    if (data.method === 'Network.responseReceived' && data.params.response.status >= 400) httpErrors.push({url:data.params.response.url,status:data.params.response.status});
    if (data.method === 'Fetch.requestPaused') {
        const url=data.params.request.url;
        let payload=url.includes('/api/v1/map/layers') ? {data:[]} : {type:'FeatureCollection',features:[],boundary:null};
        if(['fixture','partial-layer','partial-public','timeout-public','malformed-layer'].includes(scenario)) {
            const house={type:'Feature',geometry:{type:'Point',coordinates:[110.9874,-7.3574]},properties:{kind:'house',name:'Rumah generik',category:'Rumah warga',layer_id:1,layer_name:'Rumah uji'}};
            const facility={type:'Feature',geometry:{type:'Point',coordinates:[110.9875,-7.3575]},properties:{kind:'facility',name:'Sekolah <img src=x onerror="window.__xss=true">',category:'Pendidikan'}};
            const polygon={type:'Feature',geometry:{type:'Polygon',coordinates:[[[110.9871,-7.3571],[110.9872,-7.3571],[110.9872,-7.3572],[110.9871,-7.3571]]]},properties:{kind:'feature',name:'Area uji',category:'Area'}};
            if(url.includes('/rumah-uji/features')) payload={type:'FeatureCollection',features:[house]};
            else if(url.includes('/area-uji/features')) payload={type:'FeatureCollection',features:[polygon]};
            else if(url.includes('/api/v1/map/layers')) payload={data:[{id:1,name:'Rumah uji',slug:'rumah-uji',color:'#166534'},{id:2,name:'Area uji',slug:'area-uji',color:'#2563eb'}]};
            else payload={type:'FeatureCollection',features:[house,facility],boundary:null};
        }
        if (scenario==='scale' && url.includes('/api/map/public')) {
            payload={type:'FeatureCollection',boundary:null,features:Array.from({length:1000},(_,index)=>({type:'Feature',geometry:{type:'Point',coordinates:[110.987+(index%25)*0.00001,-7.357+Math.floor(index/25)*0.00001]},properties:{kind:'facility',name:'Fasilitas uji '+index,category:'Pengujian'}}))};
        }
        if (scenario === 'timeout-public' && url.includes('/api/map/public')) return;
        const failed = scenario === 'error' || (scenario === 'partial-layer' && url.includes('/area-uji/features')) || (scenario === 'partial-public' && url.includes('/api/map/public'));
        if (scenario === 'malformed-layer' && url.includes('/area-uji/features')) payload={unexpected:[]};
        command('Fetch.fulfillRequest',{requestId:data.params.requestId,responseCode:failed?503:200,responseHeaders:[{name:'Content-Type',value:'application/json'},{name:'Cache-Control',value:'no-store'}],body:Buffer.from(JSON.stringify(payload)).toString('base64')}).catch(error=>errors.push(error.message));
    }
});
function command(method, params={}) {
    return new Promise((resolve,reject) => {
        const id=++nextId;
        const timeout=setTimeout(()=>{pending.delete(id); reject(new Error('Timeout: '+method));},15000);
        pending.set(id,{resolve,reject,timeout}); socket.send(JSON.stringify({id,method,params}));
    });
}
async function evaluate(expression) {
    const result=await command('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
}
async function waitFor(expression) {
    const deadline=Date.now()+30000;
    while(Date.now()<deadline) {
        if(await evaluate(expression)) return;
        await new Promise(resolve=>setTimeout(resolve,250));
    }
    throw new Error('Page condition timed out: '+expression);
}
const results=[];
try {
    await command('Page.enable'); await command('Runtime.enable'); await command('Network.enable');
    await mkdir(output,{recursive:true});
    for (const viewport of viewports) {
        await command('Emulation.setDeviceMetricsOverride',{width:viewport.width,height:viewport.height,deviceScaleFactor:1,mobile:viewport.mobile});
        await command('Page.navigate',{url:base+'/peta'});
        await waitFor("document.getElementById('map-state') && !document.getElementById('map-count').textContent.includes('Memuat')");
        await waitFor("document.getElementById('map-layers').querySelectorAll('input').length > 1 || document.getElementById('map-count').textContent.includes('belum termuat')");
        const initial=await evaluate(`({count:document.getElementById('map-count').textContent,message:document.getElementById('map-message').textContent,mapHeight:document.getElementById('public-map').getBoundingClientRect().height,overflow:document.documentElement.scrollWidth>innerWidth+1,attribution:document.querySelector('.leaflet-control-attribution')?.textContent,layers:document.querySelectorAll('#map-layers input').length})`);
        assert(!initial.count.includes('belum termuat'), initial.message);
        assert(initial.mapHeight>250, 'Map must have usable height');
        assert(!initial.overflow,'Page must not overflow horizontally');
        assert(initial.attribution?.includes('Esri'),'Attribution must remain visible');
        await evaluate("document.querySelectorAll('#map-layers input').forEach(input=>{if(input.id!=='map-boundary-toggle'){input.checked=false;input.dispatchEvent(new Event('change'));}})");
        assert((await evaluate("document.getElementById('map-count').textContent")).startsWith('0 dari '));
        await evaluate("document.querySelectorAll('#map-layers input').forEach(input=>{input.checked=true;input.dispatchEvent(new Event('change'));})");
        await evaluate("document.getElementById('map-search').value='ZZZ-tidak-ada-objek';document.getElementById('map-search-btn').click()");
        assert((await evaluate("document.getElementById('map-count').textContent")).startsWith('0 dari '));
        await evaluate("document.getElementById('map-search').value='';document.getElementById('map-search-btn').click()");
        const categoryResult=await evaluate(`(()=>{const select=document.getElementById('map-category');if(select.options.length<2)return null;select.selectedIndex=1;select.dispatchEvent(new Event('change'));return document.getElementById('map-count').textContent;})()`);
        if (categoryResult) assert(!categoryResult.startsWith('0 dari '), 'Existing category must show matching locations');
        await evaluate("document.getElementById('map-category').selectedIndex=0;document.getElementById('map-category').dispatchEvent(new Event('change'));document.getElementById('public-map').scrollIntoView({block:'center'})");
        await waitFor("[...document.querySelectorAll('.leaflet-tile')].every(tile=>tile.complete)");
        assert(await evaluate("document.querySelectorAll('.leaflet-overlay-pane canvas, .leaflet-overlay-pane path').length>0"), 'Village boundary renderer must exist');
        const screenshot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        await writeFile(output+'/'+viewport.name+'.png',Buffer.from(screenshot.data,'base64'));
        results.push({viewport:viewport.name,...initial});
    }
    let csrfStatus='NOT TESTED: read-only public target';
    if (base==='http://127.0.0.1:8101') {
        const csrf=await fetch(base+'/admin/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:'csrf@example.test',password:'invalid'})});
        assert.equal(csrf.status,419,'Isolated web mutations must enforce CSRF');
        csrfStatus=csrf.status;
    }
    assert.equal(errors.length,0,'Browser console must have no errors: '+JSON.stringify(errors));
    assert.equal(failedRequests.length,0,'Network requests must succeed');
    assert.equal(httpErrors.length,0,'HTTP responses must succeed');
    const live={errors:[...errors],failedRequests:[...failedRequests],httpErrors:[...httpErrors]};
    await command('Fetch.enable',{patterns:[{urlPattern:'*/api/map/public'},{urlPattern:'*/api/v1/map/layers*'}]});
    const scenarios=[];
    scenario='fixture';
    for (const viewport of viewports) {
        await command('Emulation.setDeviceMetricsOverride',{...viewport,deviceScaleFactor:1});
        await command('Page.navigate',{url:base+'/peta'});
        await waitFor("document.getElementById('map-count')?.textContent === '3 dari 3 lokasi'");
        await evaluate("document.getElementById('map-category').value='Rumah warga';document.getElementById('map-category').dispatchEvent(new Event('change'))");
        assert.equal(await evaluate("document.getElementById('map-count').textContent"),'1 dari 3 lokasi');
        await evaluate("document.getElementById('map-category').value='Pendidikan';document.getElementById('map-category').dispatchEvent(new Event('change'));document.querySelector('.leaflet-marker-icon').click()");
        await waitFor("!!document.querySelector('.leaflet-popup-content')");
        assert(await evaluate("document.querySelector('.leaflet-popup-content').textContent.includes('<img') && !document.querySelector('.leaflet-popup-content img') && !window.__xss"),'Popup data must be escaped');
        await waitFor("(()=>{const r=document.querySelector('.leaflet-popup-content-wrapper').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1})()");
        assert(!(await evaluate("document.documentElement.scrollWidth>innerWidth+1")),'Fixture page must not overflow');
        await evaluate("document.getElementById('map-category').value='';document.getElementById('map-category').dispatchEvent(new Event('change'));const input=document.querySelectorAll('#map-layers input')[2];input.checked=false;input.dispatchEvent(new Event('change'))");
        assert.equal(await evaluate("document.getElementById('map-count').textContent"),'2 dari 3 lokasi');
        assert.equal(errors.length,0,'Fixture rendering must not produce console errors');
        scenarios.push({scenario:'fixture',viewport:viewport.name,result:'House/facility/polygon, category, layer toggle, deduplication and escaped popup passed'});
    }
    for (const name of ['partial-layer','partial-public','timeout-public','malformed-layer']) {
        scenario=name; errors.length=0;failedRequests.length=0;httpErrors.length=0;
        const started=Date.now();
        await command('Page.navigate',{url:base+'/peta'});
        await waitFor("document.getElementById('map-count')?.textContent === '2 dari 2 lokasi'");
        assert((await evaluate("document.getElementById('map-state').textContent")).includes('Sebagian data'));
        assert((await evaluate("document.getElementById('map-message').textContent")).includes('Muat ulang'));
        assert.equal(errors.length,0,'An unavailable source must not produce a JavaScript exception');
        scenarios.push({scenario:name,result:'Available eligible data remains visible with an explicit partial-data warning',durationMs:Date.now()-started});
    }
    scenario='scale';errors.length=0;failedRequests.length=0;httpErrors.length=0;
    const scaleStarted=Date.now();
    await command('Page.navigate',{url:base+'/peta'});
    await waitFor("document.getElementById('map-count')?.textContent === '1000 dari 1000 lokasi'");
    const searchDuration=await evaluate("(()=>{document.getElementById('map-search').value='Fasilitas uji 999';const start=performance.now();document.getElementById('map-search-btn').click();return performance.now()-start})()");
    assert.equal(await evaluate("document.getElementById('map-count').textContent"),'1 dari 1000 lokasi');
    assert.equal(errors.length,0,'Scaled rendering must not produce a console error');
    scenarios.push({scenario:'scale',points:1000,loadAndSearchMs:Date.now()-scaleStarted,searchSynchronousMs:searchDuration,result:'PASS'});
    for (const name of ['empty','error']) {
        scenario=name;
        errors.length=0;failedRequests.length=0;httpErrors.length=0;
        await command('Page.navigate',{url:base+'/peta'});
        await waitFor(name==='empty' ? "document.getElementById('map-count')?.textContent === '0 dari 0 lokasi'" : "document.getElementById('map-count')?.textContent === 'Peta belum termuat'");
        const message=await evaluate("document.getElementById('map-message').textContent");
        assert(message.includes(name==='empty'?'Belum ada lokasi':'tidak dapat dimuat'));
        scenarios.push({scenario:name,message,expectedConsoleErrors:errors.length});
    }
    await command('Fetch.disable');
    const summary={results,...live,scenarios,csrfStatus};
    await writeFile(output+'/results.json',JSON.stringify(summary,null,2));
    console.log(JSON.stringify(summary,null,2));
} finally {
    await fetch(browser+'/json/close/'+target.id);
    socket.close();
}
