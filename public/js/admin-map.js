document.addEventListener('DOMContentLoaded',async function(){
    const root=document.getElementById('admin-map');if(!root||!window.L)return;
    const map=L.map(root,{preferCanvas:true}).setView([-7.3464,110.9858],15);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{
        minZoom: 3,
        maxNativeZoom: 18,
        maxZoom: 22,
        keepBuffer: 4,
        attribution: 'Imagery &copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics, and the GIS User Community'
    }).addTo(map);
    const esc=window.PilangsariCluster?.escapeHtml||String;
    try{const r=await fetch('/data/Pilangsari_Referensi.geojson');if(r.ok){const b=L.geoJSON(await r.json(),{style:{color:'#1e6b3c',weight:3,fillOpacity:.08},interactive:false}).addTo(map);if(b.getBounds().isValid())map.fitBounds(b.getBounds(),{padding:[20,20],maxZoom:16});}}catch(e){console.warn('Batas referensi tidak dapat dimuat',e);}
    const points=(window.adminHousePoints||[]).map(p=>({lat:p.lat,lng:p.lng,kind:'house',name:p.code,code:p.code,rt:p.rt,heads:p.heads,status:p.status,address:p.address}));
    if(points.length){window.PilangsariCluster.mount(map,points,{cellSize:58,popup:p=>'<strong>'+esc(p.code)+'</strong><br>RT '+esc(p.rt)+'<br>'+esc(p.heads||'Belum ada nama kepala keluarga')+'<br><small>'+esc(p.address||'')+'</small><br><small>Status: '+esc(p.status)+'</small>'});if(points.length===1)map.setView([points[0].lat,points[0].lng],18);}
    else{L.popup({closeButton:false}).setLatLng([-7.3464,110.9858]).setContent('Tidak ada rumah pada hasil pencarian ini.').openOn(map);}
});
