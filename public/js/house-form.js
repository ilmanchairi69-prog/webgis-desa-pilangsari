document.addEventListener('DOMContentLoaded',function(){
    // Nested household form management.
    const cardList=document.getElementById('family-card-list');
    const addCardButton=document.getElementById('add-card');
    const cardTemplate=document.getElementById('card-template');
    const memberTemplate=document.getElementById('member-template');
    let nextCardIndex=Math.max(-1,...Array.from(cardList?.querySelectorAll('[data-card-index]')||[]).map(el=>Number(el.dataset.cardIndex)||0))+1;
    function setCardNames(card,cardIndex){
        card.querySelectorAll('input[data-name],textarea[data-name],select[data-name]').forEach(el=>{
            const name=el.dataset.name;
            const member=el.closest('.member-row');
            if(member){if(member.dataset.memberIndex===undefined)member.dataset.memberIndex=String(member.parentElement.querySelectorAll('.member-row').length-1);el.name=`family_cards[${cardIndex}][members][${member.dataset.memberIndex}][${name}]`;}
            else el.name=`family_cards[${cardIndex}][${name}]`;
        });
        card.querySelectorAll('.member-row').forEach((row,index)=>{if(row.dataset.memberIndex===undefined)row.dataset.memberIndex=String(index);row.querySelectorAll('input,select,textarea').forEach(el=>{const key=el.name.match(/\[([^\]]+)\]$/)?.[1]||el.dataset.name;if(key)el.name=`family_cards[${cardIndex}][members][${row.dataset.memberIndex}][${key}]`;});});
    }
    function addMember(card){
        const list=card.querySelector('.member-list');if(!list)return;
        const template=memberTemplate.content.cloneNode(true);const row=template.querySelector('.member-row');
        const next=Math.max(-1,...Array.from(list.querySelectorAll('.member-row')).map(el=>Number(el.dataset.memberIndex)||0))+1;row.dataset.memberIndex=String(next);
        row.querySelectorAll('[data-name]').forEach(el=>{el.name=`family_cards[${card.dataset.cardIndex}][members][${next}][${el.dataset.name}]`;});list.appendChild(template);
    }
    cardList?.querySelectorAll('.family-card-editor').forEach(card=>{
        card.querySelectorAll('.member-row').forEach((row,index)=>{row.dataset.memberIndex=String(index);row.querySelectorAll('input,select,textarea').forEach(el=>{const match=el.name.match(/\[([^\]]+)\]$/);if(match)el.name=`family_cards[${card.dataset.cardIndex}][members][${index}][${match[1]}]`;});});
    });
    addCardButton?.addEventListener('click',function(){
        const clone=cardTemplate.content.cloneNode(true);const card=clone.querySelector('.family-card-editor');card.dataset.cardIndex=String(nextCardIndex++);
        setCardNames(card,card.dataset.cardIndex);card.querySelectorAll('.member-row').forEach((row,index)=>{row.dataset.memberIndex=String(index);row.querySelectorAll('[data-name]').forEach(el=>el.name=`family_cards[${card.dataset.cardIndex}][members][${index}][${el.dataset.name}]`);});
        cardList.appendChild(clone);
    });
    cardList?.addEventListener('click',function(e){
        const removeCard=e.target.closest('[data-remove-card]');
        if(removeCard){const cards=cardList.querySelectorAll('.family-card-editor');if(cards.length>1)removeCard.closest('.family-card-editor').remove();return;}
        const removeMember=e.target.closest('[data-remove-member]');
        if(removeMember){const row=removeMember.closest('.member-row');const list=row.parentElement;if(list.querySelectorAll('.member-row').length>1)row.remove();return;}
        const addMemberButton=e.target.closest('[data-add-member]');if(addMemberButton)addMember(addMemberButton.closest('.family-card-editor'));
    });
    // Leaflet click-to-set coordinates. Server-side polygon validation remains authoritative.
    const mapRoot=document.getElementById('house-picker-map');if(!mapRoot||!window.L)return;
    const latField=document.getElementById('house-lat'),lngField=document.getElementById('house-lng'),status=document.getElementById('house-picker-status');
    const center=[-7.3464,110.9858];const map=L.map(mapRoot,{maxZoom:22}).setView(center,15);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{
        minZoom: 3,
        maxNativeZoom: 18,
        maxZoom: 22,
        keepBuffer: 4,
        attribution: 'Imagery &copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics, and the GIS User Community'
    }).addTo(map);
    let boundaryLayer=null,pointMarker=null;
    fetch('/data/Pilangsari_Referensi.geojson').then(r=>r.json()).then(geo=>{boundaryLayer=L.geoJSON(geo,{style:{color:'#1e6b3c',weight:3,fillOpacity:.08},interactive:false}).addTo(map);const b=boundaryLayer.getBounds();if(b.isValid())map.fitBounds(b,{padding:[20,20],maxZoom:16});}).catch(()=>{if(status)status.textContent='Batas referensi tidak dapat dimuat. Koordinat tetap akan diperiksa oleh server.';});
    function showPoint(lat,lng){if(!Number.isFinite(lat)||!Number.isFinite(lng)||lat < -90||lat > 90||lng < -180||lng > 180){if(status)status.textContent='Koordinat tidak valid. Periksa latitude dan longitude.';return;}if(pointMarker){pointMarker.setLatLng([lat,lng]);}else{pointMarker=L.marker([lat,lng],{draggable:true}).addTo(map);pointMarker.on('dragend',()=>{const p=pointMarker.getLatLng();latField.value=p.lat.toFixed(7);lngField.value=p.lng.toFixed(7);});}latField.value=lat.toFixed(7);lngField.value=lng.toFixed(7);status.textContent='Koordinat terpilih: '+latField.value+', '+lngField.value+'. Simpan untuk validasi batas di server.';}
    map.on('click',e=>showPoint(e.latlng.lat,e.latlng.lng));
    function syncFromInputs(){const latText=latField.value.trim(),lngText=lngField.value.trim();if(!latText||!lngText){if(status)status.textContent='Isi latitude dan longitude dengan angka yang valid.';return;}const lat=Number(latText),lng=Number(lngText);if(Number.isFinite(lat)&&Number.isFinite(lng)&&lat>=-90&&lat<=90&&lng>=-180&&lng<=180){showPoint(lat,lng);map.panTo([lat,lng]);}else if(status){status.textContent='Koordinat tidak valid. Latitude harus -90 sampai 90 dan longitude -180 sampai 180.';}}
    latField?.addEventListener('change',syncFromInputs);lngField?.addEventListener('change',syncFromInputs);
    document.getElementById('locate-me')?.addEventListener('click',function(){if(!navigator.geolocation){status.textContent='Browser tidak mendukung geolokasi.';return;}status.textContent='Meminta izin lokasi perangkat…';navigator.geolocation.getCurrentPosition(pos=>{const lat=pos.coords.latitude,lng=pos.coords.longitude;map.setView([lat,lng],18);showPoint(lat,lng);},()=>{status.textContent='Lokasi tidak tersedia atau izin tidak diberikan. Klik peta secara manual.';},{enableHighAccuracy:true,timeout:10000});});
    if(latField?.value&&lngField?.value){const lat=parseFloat(latField.value),lng=parseFloat(lngField.value);if(Number.isFinite(lat)&&Number.isFinite(lng)){showPoint(lat,lng);map.setView([lat,lng],18);}}
    setTimeout(()=>map.invalidateSize(),100);
});
