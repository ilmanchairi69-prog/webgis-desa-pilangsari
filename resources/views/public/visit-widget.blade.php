<aside class="visit-widget" data-visit-widget data-url="{{ route('visits.statistics') }}" aria-label="Statistik kunjungan website">
    <div class="visit-panel" id="visit-statistics" hidden>
        <h2>Jumlah Kunjungan</h2>
        <p class="visit-status" data-visit-status role="status">Memuat statistik...</p>
        <dl data-visit-list hidden>
            @foreach (['today' => 'Hari Ini', 'yesterday' => 'Kemarin', 'this_week' => 'Minggu Ini', 'last_week' => 'Minggu Lalu', 'this_month' => 'Bulan Ini', 'last_month' => 'Bulan Lalu', 'total' => 'Total Kunjungan'] as $key => $label)
                <div><dt>{{ $label }}</dt><dd data-visit-value="{{ $key }}">&mdash;</dd></div>
            @endforeach
        </dl>
        <p class="visit-note">Terhitung sejak pencatatan dimulai.</p>
    </div>
    <button class="visit-toggle" type="button" aria-expanded="false" aria-controls="visit-statistics" aria-label="Buka statistik kunjungan">
        <span class="visit-icon" aria-hidden="true">@include('public.portal-icon', ['name' => 'globe'])</span><span><strong>Kunjungan</strong><span><b data-visit-today>&hellip;</b> Hari Ini</span></span><span class="visit-chevron" aria-hidden="true">&#8963;</span>
    </button>
</aside>
