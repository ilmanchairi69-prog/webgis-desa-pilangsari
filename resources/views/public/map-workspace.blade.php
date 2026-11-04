<div class="portal-map-workspace">
    <div class="map-toolbar"><div class="map-search"><label for="map-search">Cari lokasi di peta</label><div class="search-row"><input type="search" id="map-search" placeholder="Nama atau kategori objek"><button type="button" id="map-search-btn">@include('public.portal-icon', ['name' => 'search']) <span>Cari</span></button></div></div><div class="map-count"><span class="count-dot"></span><strong id="map-count" aria-live="polite">Memuat peta…</strong><small id="map-state">Data publik terverifikasi</small></div></div>
    <div class="map-filters" aria-label="Kategori peta publik">
        <button type="button" data-public-category="houses" aria-pressed="true"><i class="legend-house"></i> Rumah Warga</button>
        <button type="button" data-public-category="facilities" aria-pressed="true"><i class="legend-facility"></i> Fasilitas Umum</button>
        <label><input id="map-boundary-toggle" type="checkbox" checked> Batas referensi desa</label>
    </div>
    <div id="public-map" class="public-map" data-api-url="{{ route('api.map.public') }}" aria-label="Peta interaktif Desa Pilangsari"></div>
    <div id="map-message" class="map-message" aria-live="polite">Memuat batas referensi dan data publik…</div>
    <div class="map-legend"><span><i class="legend-boundary"></i>Batas referensi desa</span><span><i class="legend-facility"></i>Fasilitas umum</span><span><i class="legend-house"></i>Lokasi rumah publik</span><span class="legend-note">Data keluarga tidak ditampilkan.</span></div>
</div>
