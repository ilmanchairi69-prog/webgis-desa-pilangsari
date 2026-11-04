/* Lightweight grid-based marker clustering for Leaflet. Keeps this app independent of extra JS packages. */
(function (global) {
    'use strict';
    function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function clusterIcon(count) {
        const kind = count >= 30 ? 'cluster-large' : (count >= 10 ? 'cluster-medium' : 'cluster-small');
        const size = count >= 30 ? 52 : (count >= 10 ? 44 : 36);
        return L.divIcon({className:'',html:'<div class="cluster-marker '+kind+'">'+count+'</div>',iconSize:[size,size],iconAnchor:[size/2,size/2]});
    }
    function pointIcon(kind) {
        return L.divIcon({className:'',html:'<div class="cluster-single '+(kind==='facility'?'facility':(kind==='feature'?'map-feature':''))+'"></div>',iconSize:[28,28],iconAnchor:[14,14]});
    }
    function mount(map, items, options) {
        const settings = Object.assign({cellSize:58, clusterZoom:19, popup:(item)=>escapeHtml(item.name || 'Lokasi')}, options || {});
        const group = L.layerGroup().addTo(map);
        let redrawQueued = false;
        function draw() {
            group.clearLayers();
            const zoom = map.getZoom();
            const buckets = new Map();
            items.forEach(item => {
                const lat = Number(item.lat ?? item.geometry?.coordinates?.[1]);
                const lng = Number(item.lng ?? item.geometry?.coordinates?.[0]);
                if (!Number.isFinite(lat) || !Number.isFinite(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) return;
                const pixel = map.latLngToContainerPoint([lat,lng]);
                const key = (zoom >= settings.clusterZoom ? 'solo:' : '') + Math.floor(pixel.x/settings.cellSize) + ':' + Math.floor(pixel.y/settings.cellSize);
                if (!buckets.has(key)) buckets.set(key, []);
                buckets.get(key).push({item,lat,lng});
            });
            buckets.forEach(bucket => {
                const lat = bucket.reduce((sum,p)=>sum+p.lat,0)/bucket.length;
                const lng = bucket.reduce((sum,p)=>sum+p.lng,0)/bucket.length;
                if (bucket.length > 1 && zoom < settings.clusterZoom) {
                    const marker = L.marker([lat,lng], {icon:clusterIcon(bucket.length), keyboard:true, title:bucket.length+' lokasi'});
                    marker.bindTooltip(bucket.length+' lokasi', {direction:'top',opacity:.9});
                    marker.on('click', () => map.setView([lat,lng], Math.min(map.getMaxZoom() || 19, zoom + 2), {animate:true}));
                    group.addLayer(marker);
                } else {
                    bucket.forEach(({item,lat:pointLat,lng:pointLng}) => {
                        const marker = L.marker([pointLat,pointLng], {icon:pointIcon(item.kind), keyboard:true, title:item.name || 'Lokasi'});
                        marker.bindPopup(settings.popup(item));
                        group.addLayer(marker);
                    });
                }
            });
        }
        function queueDraw() { if (redrawQueued) return; redrawQueued = true; requestAnimationFrame(()=>{redrawQueued=false;draw();}); }
        map.on('zoomend moveend', queueDraw);
        draw();
        return {layer:group, refresh:draw, destroy:function(){map.off('zoomend moveend', queueDraw);map.removeLayer(group);}, escapeHtml};
    }
    global.PilangsariCluster = {mount, escapeHtml};
})(window);
