document.addEventListener('DOMContentLoaded', async () => {
    const mapElement = document.getElementById('village-map');
    if (!mapElement || typeof L === 'undefined') return;

    // Tampilan awal hanya berpusat secara umum; bukan batas resmi desa.
    const initialCenter = [-7.3463, 111.0083];
    const map = L.map(mapElement, { zoomControl: true }).setView(initialCenter, 13);

    // Peta dasar jalan raya; tidak bergantung pada layer data desa.
    L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        {
            minZoom: 3,
            maxNativeZoom: 18,
            maxZoom: 22,
            attribution: 'Imagery &copy; <a href="https://www.esri.com/">Esri</a>, Maxar, Earthstar Geographics, and the GIS User Community'
        }
    ).addTo(map);
    // Penanda referensi desa; bukan batas administrasi resmi.

    const layerList = document.querySelector('.layer-list');
    // PILANGSARI_GADM_BOUNDARY_START
    // Batas referensi GADM; bukan batas resmi yang telah diverifikasi.
    const referenceBoundary = L.layerGroup().addTo(map);
    const referenceBoundaryUrl = '/data/Pilangsari_Referensi.geojson';

    try {
        const boundaryResponse = await fetch(referenceBoundaryUrl, {
            headers: { 'Accept': 'application/geo+json, application/json' }
        });
        if (!boundaryResponse.ok) {
            throw new Error(`GeoJSON batas gagal dimuat: HTTP ${boundaryResponse.status}`);
        }

        const boundaryGeoJson = await boundaryResponse.json();
        const boundaryGeoLayer = L.geoJSON(boundaryGeoJson, {
            style: {
                color: '#38bdf8',
                weight: 3,
                opacity: 1,
                fillColor: '#2563eb',
                fillOpacity: 0.20
            },
            onEachFeature: (feature, layer) => {
                const p = feature.properties || {};
                layer.bindPopup(
                    '<strong>Desa Pilangsari</strong>' +
                    '<br>Kecamatan: Gesi' +
                    '<br>Kabupaten: Sragen' +
                    '<br><small>Batas referensi GADM; belum diverifikasi sebagai batas resmi.</small>'
                );
            }
        });

        boundaryGeoLayer.addTo(referenceBoundary);

        if (boundaryGeoLayer.getBounds().isValid()) {
            map.fitBounds(boundaryGeoLayer.getBounds(), { padding: [24, 24] });
        }

        // Kontrol tampil/sembunyi untuk polygon referensi
        const boundaryLabel = document.createElement('label');
        boundaryLabel.className = 'layer-row';

        const boundarySymbol = document.createElement('span');
        boundarySymbol.className = 'layer-symbol boundary-symbol';
        boundarySymbol.textContent = '◇';
        boundarySymbol.style.color = '#38bdf8';

        const boundaryCopy = document.createElement('span');
        boundaryCopy.className = 'layer-copy';
        const boundaryTitle = document.createElement('strong');
        boundaryTitle.textContent = 'Batas Desa Pilangsari';
        const boundaryDetail = document.createElement('small');
        boundaryDetail.textContent = 'Referensi GADM · belum diverifikasi resmi';
        boundaryCopy.append(boundaryTitle, boundaryDetail);

        const boundaryCheckbox = document.createElement('input');
        boundaryCheckbox.type = 'checkbox';
        boundaryCheckbox.checked = true;
        boundaryCheckbox.id = 'toggle-gadm-boundary';
        boundaryCheckbox.addEventListener('change', () => {
            if (boundaryCheckbox.checked) referenceBoundary.addTo(map);
            else map.removeLayer(referenceBoundary);
        });

        const boundarySwitch = document.createElement('span');
        boundarySwitch.className = 'switch';

        boundaryLabel.append(
            boundarySymbol, boundaryCopy, boundaryCheckbox, boundarySwitch
        );

        if (layerList) {
            layerList.prepend(boundaryLabel);
            const existingMessage = layerList.querySelector('.form-help');
            if (existingMessage &&
                existingMessage.textContent.includes('Belum ada layer publik')) {
                existingMessage.textContent = 'Polygon ini adalah batas referensi GADM, bukan layer publik dari database.';
            }
        }
    } catch (error) {
        console.error('Gagal memuat polygon referensi Pilangsari:', error);
    }
    // PILANGSARI_GADM_BOUNDARY_END
        const layerCount = document.getElementById('layer-count');
    const search = document.getElementById('map-search');
    const results = document.getElementById('search-results');
    const clear = document.getElementById('search-clear');
    const loadedLayers = [];
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));

    if (layerList) {
        layerList.replaceChildren();
        const message = document.createElement('p');
        message.className = 'form-help';
        message.textContent = 'Memuat layer yang telah dipublikasikan dan diverifikasi.';
        layerList.appendChild(message);
    }

    try {
        const response = await fetch('/api/v1/map/layers', {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error(`API layer merespons HTTP ${response.status}`);

        const payload = await response.json();
        const layers = Array.isArray(payload.data) ? payload.data : [];

        if (layerCount) layerCount.textContent = String(layers.length).padStart(2, '0');
        if (layerList) layerList.replaceChildren();

        if (!layers.length && layerList) {
            const empty = document.createElement('p');
            empty.className = 'form-help';
            empty.textContent = 'Belum ada layer publik. Data draft tidak ditampilkan.';
            layerList.appendChild(empty);
        }

        for (const item of layers) {
            const geoResponse = await fetch(
                `/api/v1/map/layers/${encodeURIComponent(item.slug)}/features`,
                { headers: { 'Accept': 'application/json' } }
            );
            if (!geoResponse.ok) {
                console.warn(`Layer ${item.name} gagal dimuat: HTTP ${geoResponse.status}`);
                continue;
            }

            const geojson = await geoResponse.json();
                        // PILANGSARI_HOUSE_CLUSTER_START
            // Clustering hanya berlaku untuk layer titik rumah yang sudah lolos filter API publik.
            const isHouseLayer = item.slug === 'rumah-warga-desa-pilangsari';
            const clusterGroup = (
                isHouseLayer &&
                typeof L.markerClusterGroup === 'function'
            ) ? L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                zoomToBoundsOnClick: true,
                removeOutsideVisibleBounds: true,
                maxClusterRadius: 55,
                iconCreateFunction: function (cluster) {
                    const count = cluster.getChildCount();
                    let sizeClass = 'marker-cluster-small';

                    if (count >= 100) {
                        sizeClass = 'marker-cluster-large';
                    } else if (count >= 10) {
                        sizeClass = 'marker-cluster-medium';
                    }

                    return L.divIcon({
                        html: '<div><span>' + count + '</span></div>',
                        className: 'marker-cluster ' + sizeClass,
                        iconSize: L.point(40, 40)
                    });
                }
            }) : null;

            const geoLayer = L.geoJSON(geojson, {
                style: () => ({
                    color: item.color || '#16803c',
                    weight: 2,
                    fillOpacity: 0.18
                }),
                pointToLayer: (feature, latlng) => {
                    if (isHouseLayer && clusterGroup) {
                        const houseIcon = L.divIcon({
                            className: 'pilangsari-house-marker-wrap',
                            html: '<span class="pilangsari-house-marker" aria-label="Titik rumah"></span>',
                            iconSize: [18, 18],
                            iconAnchor: [9, 9]
                        });

                        return L.marker(latlng, {
                            icon: houseIcon,
                            keyboard: true,
                            title: 'Lokasi rumah'
                        });
                    }

                    return L.circleMarker(latlng, {
                        radius: 6,
                        color: item.color || '#16803c',
                        fillColor: item.color || '#16803c',
                        fillOpacity: 0.85
                    });
                },
                onEachFeature: (feature, layer) => {
                    const p = feature.properties || {};
                    layer.bindPopup(
                        `<strong>${escapeHtml(p.name || item.name)}</strong>` +
                        (p.description ? `<br>${escapeHtml(p.description)}` : '')
                    );
                }
            });

            // Marker rumah dimasukkan satu per satu ke cluster; layer lain tetap seperti semula.
            const displayLayer = clusterGroup || geoLayer;

            if (clusterGroup) {
                geoLayer.eachLayer((childLayer) => {
                    clusterGroup.addLayer(childLayer);
                });
            }

            const checkboxId = `layer-${item.id}`;
            const label = document.createElement('label');
            label.className = 'layer-row';

            const copy = document.createElement('span');
            copy.className = 'layer-copy';
            const title = document.createElement('strong');
            title.textContent = item.name;
            const detail = document.createElement('small');
            detail.textContent = `${item.layer_type} · ${geojson.features?.length || 0} objek`;
            copy.append(title, detail);

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = checkboxId;
            checkbox.checked = true;
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) displayLayer.addTo(map);
                else map.removeLayer(displayLayer);
            });

            const symbol = document.createElement('span');
            symbol.className = 'layer-symbol';
            symbol.textContent = '\u25CF';
            symbol.style.color = item.color || '#16803c';

            const switchEl = document.createElement('span');
            switchEl.className = 'switch';

            label.append(symbol, copy, checkbox, switchEl);
            layerList?.appendChild(label);

            displayLayer.addTo(map);
            loadedLayers.push({
                item,
                geoLayer,
                displayLayer,
                features: geojson.features || []
            });
            // PILANGSARI_HOUSE_CLUSTER_END
        }
    } catch (error) {
        console.error('Gagal memuat data WebGIS:', error);
        if (layerList) {
            layerList.replaceChildren();
            const message = document.createElement('p');
            message.className = 'error-message';
            message.textContent = 'Data peta gagal dimuat dari API. Periksa koneksi server.';
            layerList.appendChild(message);
        }
    }

    map.on('mousemove', (event) => {
        const output = document.getElementById('map-coordinates');
        if (output) output.textContent =
            `${event.latlng.lat.toFixed(6)}, ${event.latlng.lng.toFixed(6)}`;
    });

    document.getElementById('reset-map')?.addEventListener('click', () => {
        map.setView(initialCenter, 13);
    });

    const runSearch = async () => {
        if (!search || !results) return;
        const query = search.value.trim();
        results.replaceChildren();
        if (query.length < 2) return;

        try {
            const response = await fetch(
                `/api/v1/map/search?q=${encodeURIComponent(query)}`,
                { headers: { 'Accept': 'application/json' } }
            );
            if (!response.ok) throw new Error('Pencarian API gagal');
            const payload = await response.json();
            const matches = payload.data || [];

            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'search-result';
                empty.textContent = 'Tidak ada lokasi terverifikasi yang cocok.';
                results.appendChild(empty);
                return;
            }

            matches.forEach((place) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'search-result';
                button.textContent = place.name;
                button.addEventListener('click', () => {
                    if (place.latitude != null && place.longitude != null) {
                        map.setView([place.latitude, place.longitude], 17);
                    } else {
                        const target = loadedLayers.find(entry =>
                            entry.features.some(f => String(f.id) === String(place.id))
                        );
                        const feature = target?.features.find(f =>
                            String(f.id) === String(place.id)
                        );
                        if (feature) {
                            const geo = L.geoJSON(feature);
                            const bounds = geo.getBounds();
                            if (bounds.isValid()) map.fitBounds(bounds, { padding: [20, 20] });
                        }
                    }
                });
                results.appendChild(button);
            });
        } catch (error) {
            console.error(error);
            const message = document.createElement('div');
            message.className = 'search-result';
            message.textContent = 'Pencarian gagal. Periksa koneksi API.';
            results.appendChild(message);
        }
    };

    search?.addEventListener('input', runSearch);
    clear?.addEventListener('click', () => {
        if (search) {
            search.value = '';
            search.focus();
        }
        results?.replaceChildren();
    });

    document.getElementById('toggle-boundary')?.closest('label')?.remove();
    document.getElementById('toggle-facilities')?.closest('label')?.remove();

    const parcelToggle = document.getElementById('toggle-parcels');
    if (parcelToggle) {
        parcelToggle.checked = false;
        parcelToggle.disabled = true;
    }
});







