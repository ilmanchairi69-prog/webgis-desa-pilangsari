document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('public-map');
    if (!root) return;
    const count = document.getElementById('map-count');
    const message = document.getElementById('map-message');
    const state = document.getElementById('map-state');
    const search = document.getElementById('map-search');
    const active = new Set(['houses', 'facilities']);
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let map, cluster, geometryLayer;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
        if (!window.L || !window.PilangsariCluster) throw new Error('Pustaka peta belum tersedia');
        map = L.map(root, {preferCanvas:true}).setView([-7.3464,110.9858],15);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            minZoom:3,maxNativeZoom:18,maxZoom:22,attribution:'Imagery &copy; Esri, Maxar, Earthstar Geographics'
        }).on('tileerror', () => { message.textContent = 'Sebagian citra gagal dimuat; data lokasi tetap tersedia.'; }).addTo(map);
        const response = await fetch(root.dataset.apiUrl, {cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
        if (!response.ok) throw new Error('API peta gagal');
        const data = await response.json();
        if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) throw new Error('Format peta tidak valid');
        let boundary;
        if (data.boundary) {
            boundary = L.geoJSON(data.boundary,{style:{color:'#1f6d3b',weight:3,fillOpacity:.1,dashArray:'7 5'},interactive:false}).addTo(map);
            if (boundary.getBounds().isValid()) map.fitBounds(boundary.getBounds(),{padding:[24,24],maxZoom:16});
        }
        document.getElementById('map-boundary-toggle').addEventListener('change', event => {
            if (boundary) event.target.checked ? map.addLayer(boundary) : map.removeLayer(boundary);
        });
        const items = data.features.filter(feature => ['houses','facilities'].includes(feature.properties?.system_key) && feature.geometry).map(feature => ({
            feature,systemKey:feature.properties.system_key,kind:feature.properties.system_key === 'houses' ? 'house' : 'facility',
            lat:feature.geometry.coordinates?.[1],lng:feature.geometry.coordinates?.[0],name:feature.properties.name || 'Lokasi',
            category:feature.properties.system_key === 'houses' ? 'Rumah Warga' : 'Fasilitas Umum'
        }));
        const popup = item => '<strong>'+escape(item.name)+'</strong><br>'+escape(item.category);
        function render(focus = false) {
            const term = search.value.trim().toLocaleLowerCase('id');
            const filtered = items.filter(item => active.has(item.systemKey) && (!term || (item.name+' '+item.category).toLocaleLowerCase('id').includes(term)));
            cluster?.destroy();
            if (geometryLayer) map.removeLayer(geometryLayer);
            const points = filtered.filter(item => item.feature.geometry.type === 'Point');
            cluster = PilangsariCluster.mount(map, points, {popup,cellSize:58});
            geometryLayer = L.geoJSON(filtered.filter(item => item.feature.geometry.type !== 'Point').map(item => item.feature), {
                style:{color:'#e0bc00',weight:3,fillOpacity:.2},
                onEachFeature:(feature,layer) => layer.bindPopup('<strong>'+escape(feature.properties.name)+'</strong><br>Fasilitas Umum')
            }).addTo(map);
            count.textContent = filtered.length+' dari '+items.length+' lokasi';
            message.textContent = items.length === 0 ? 'Belum ada objek yang memenuhi syarat publikasi kategori dan publikasi individual.' : filtered.length ? 'Peta siap. Klik penanda untuk informasi publik.' : 'Tidak ada lokasi sesuai kategori aktif dan pencarian.';
            if (focus && filtered.length) {
                const bounds = L.latLngBounds(points.map(item => [item.lat,item.lng]));
                if (geometryLayer.getBounds().isValid()) bounds.extend(geometryLayer.getBounds());
                if (bounds.isValid()) map.fitBounds(bounds,{padding:[30,30],maxZoom:18});
            }
        }
        document.querySelectorAll('[data-public-category]').forEach(button => {
            button.addEventListener('click', () => {
                const key = button.dataset.publicCategory;
                active.has(key) ? active.delete(key) : active.add(key);
                button.setAttribute('aria-pressed', String(active.has(key)));
                render();
            });
        });
        search.addEventListener('input', () => render());
        search.addEventListener('keydown', event => { if (event.key === 'Enter') {event.preventDefault();render(true);} });
        document.getElementById('map-search-btn').addEventListener('click', () => render(true));
        state.textContent = 'Publikasi kategori dan objek telah diperiksa';
        render();
        if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe(root);
    } catch (error) {
        cluster?.destroy();
        if (map && geometryLayer) map.removeLayer(geometryLayer);
        count.textContent = 'Peta belum termuat';
        state.textContent = 'Periksa koneksi';
        message.textContent = 'Data peta gagal dimuat. Coba lagi; tidak ada data lama yang ditampilkan sebagai data terbaru.';
    } finally {
        clearTimeout(timeout);
    }
});
