import assert from 'node:assert/strict';
import {mkdir, writeFile} from 'node:fs/promises';
const base='http://127.0.0.1:8000', debug='http://127.0.0.1:9258', out='storage/framework/testing/local-live-map';
await mkdir(out,{recursive:true});
for(let n=0;n<50;n++){try{if((await fetch(debug+'/json/version')).ok)break;}catch{}await new Promise(r=>setTimeout(r,150));}
const target=await(await fetch(debug+'/json/new?about:blank',{method:'PUT'})).json();
const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise((r,j)=>{ws.addEventListener('open',r,{once:true});ws.addEventListener('error',j,{once:true});});
let id=0;const pending=new Map(),exceptions=[];
ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const a=pending.get(m.id);if(a){pending.delete(m.id);clearTimeout(a.timer);m.error?a.reject(new Error('CDP '+a.method+': '+m.error.message)):a.resolve(m.result);}}if(m.method==='Runtime.exceptionThrown')exceptions.push(m.params.exceptionDetails.text);});
function cmd(method,params={}){return new Promise((resolve,reject)=>{const num=++id;const timer=setTimeout(()=>reject(new Error('CDP timeout '+method)),20000);pending.set(num,{resolve,reject,timer,method});ws.send(JSON.stringify({id:num,method,params}));});}
async function ev(expression){const x=await cmd('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(x.exceptionDetails)throw new Error('Browser evaluation failure');return x.result.value;}
async function wait(expression){const end=Date.now()+25000;while(Date.now()<end){if(await ev(expression))return;await new Promise(r=>setTimeout(r,150));}throw new Error('Browser wait timeout');}
try{
 await cmd('Page.enable');await cmd('Runtime.enable');await cmd('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
 await cmd('Page.addScriptToEvaluateOnNewDocument',{source:`Object.defineProperty(window,'PilangsariCluster',{configurable:true,get(){return this.__localCluster;},set(value){const original=value.mount;value.mount=function(map,items,options){const group=original(map,items,options);window.__localMap=map;window.__localMarkers=group;window.__localItems=items;return group;};this.__localCluster=value;}});`});
 await cmd('Page.navigate',{url:base+'/peta'});
 await wait('document.readyState === "complete" && !!window.__localMap && window.__localItems.length === 7');
 const response=await ev('fetch("/api/map/public",{cache:"no-store"}).then(async r=>({status:r.status,body:await r.json()}))');
 assert.equal(response.status,200);assert.equal(response.body.features.length,7);
 const kinds=response.body.features.reduce((acc,f)=>(acc[f.properties.kind]=(acc[f.properties.kind]||0)+1,acc),{});
 assert.equal(kinds.house,4);assert.equal(kinds.facility,3);
 const privateKeys=new Set(['card_number','head_name','head_of_family_name','family_cards','family_members','date_of_birth','rejection_note','verified_by','created_by','updated_by','password','email']);
 function privacy(value){if(value&&typeof value==='object'){for(const[key,item]of Object.entries(value)){assert(!privateKeys.has(key),'Private key in public API');privacy(item);}}}
 privacy(response.body);
 await writeFile(out+'/public-api-response.json',JSON.stringify(response,null,2));
 await ev('window.__localMap.setZoom(19,{animate:false}); true');
 await wait('window.__localMarkers.layer.getLayers().length===7 && window.__localMarkers.layer.getLayers().every(m=>!!m.getPopup())');
 const markerProof=await ev('window.__localMarkers.layer.getLayers().map(m=>({lat:m.getLatLng().lat,lng:m.getLatLng().lng,popup:m.getPopup().getContent(),kind:m.getElement().querySelector(".facility")?"facility":"house"}))');
 for(const feature of response.body.features){const[lon,lat]=feature.geometry.coordinates;assert(markerProof.some(m=>m.kind===feature.properties.kind&&Math.abs(m.lat-lat)<1e-7&&Math.abs(m.lng-lon)<1e-7),'Every marker coordinate must match API exactly');}
 await writeFile(out+'/marker-coordinates.json',JSON.stringify(markerProof,null,2));
 await ev('window.__localMap.fitBounds(window.__localItems.map(i=>[i.lat,i.lng]),{padding:[60,60],maxZoom:18,animate:false}); true');
 await wait('!!document.querySelector(".cluster-single.facility") && !!document.querySelector(".cluster-single:not(.facility)")');
 const colors=await ev('({house:getComputedStyle(document.querySelector(".cluster-single:not(.facility)")).backgroundColor,facility:getComputedStyle(document.querySelector(".cluster-single.facility")).backgroundColor})');
 assert.equal(colors.house,'rgb(28, 121, 64)');assert.equal(colors.facility,'rgb(240, 201, 0)');
 await ev('window.scrollTo({top:Math.max(0,document.getElementById("public-map").getBoundingClientRect().top+scrollY-140),behavior:"instant"});new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))');
 const imagery=await ev('(async()=>{const end=Date.now()+15000;let loaded=0;while(Date.now()<end){const tiles=[...document.querySelectorAll("#public-map img.leaflet-tile")];loaded=tiles.filter(i=>i.complete&&i.naturalWidth>0).length;if(loaded>=4&&tiles.every(i=>i.complete))return{loaded,ready:true};await new Promise(r=>setTimeout(r,150));}return{loaded,ready:false};})()');
 await wait('document.getElementById("map-count").textContent === "7 dari 7 lokasi"');
 const shot=await cmd('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});await writeFile(out+'/local-live-map.png',Buffer.from(shot.data,'base64'));
 await ev('window.__localMarkers.layer.getLayers().find(m=>m.getPopup() && !m.getElement().querySelector(".facility")).openPopup(); true');
 await wait('!!document.querySelector(".leaflet-popup-content") && getComputedStyle(document.querySelector(".leaflet-popup")).opacity === "1"');
 const popup=await ev('document.querySelector(".leaflet-popup-content").textContent');assert(!popup.includes('@'));assert(popup.includes('Lokasi rumah warga terverifikasi'));
 const popupShot=await cmd('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});await writeFile(out+'/local-live-house-popup.png',Buffer.from(popupShot.data,'base64'));
 assert.deepEqual(exceptions,[]);
 const result={status:'PASS',server:base,read_only:true,application_requests:'GET only',features:7,kinds,colors,coordinates_match:true,privacy:'PASS',popup,imagery,exceptions,screenshots:['local-live-map.png','local-live-house-popup.png']};
 await writeFile(out+'/results.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result));
}finally{await fetch(debug+'/json/close/'+target.id);ws.close();}
