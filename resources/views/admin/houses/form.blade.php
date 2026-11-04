@extends('layouts.admin')

@section('title', 'Tambah Bangunan dan Data KK')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
.house-wizard{max-width:1050px;margin:0 auto 40px}
.wizard-progress{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:22px 0}
.wizard-step{padding:12px 8px;border:1px solid #cbd5e1;border-radius:10px;text-align:center;color:#475569;background:#f8fafc;font-size:13px}
.wizard-step.active{background:#166534;color:white;border-color:#166534}
.wizard-step.done{border-color:#16a34a;color:#166534}
.wizard-panel{padding:22px 0;border-bottom:1px solid #e2e8f0}
.wizard-panel h2{margin:0 0 8px;font-size:21px}
.wizard-help{color:#64748b;font-size:14px;margin:0 0 18px}
.wizard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.wizard-field label{display:block;font-weight:600;margin-bottom:7px}
.wizard-field input,.wizard-field textarea{box-sizing:border-box;width:100%;min-height:43px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px}
.wizard-field textarea{min-height:80px}
.wizard-span{grid-column:1/-1}
#house-location-map{height:350px;margin:14px 0;border:1px solid #cbd5e1;border-radius:12px;z-index:0}
.wizard-coordinates{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.household-card{border:1px solid #cbd5e1;border-radius:12px;padding:18px;margin:14px 0}
.household-card h3{font-size:17px;margin:0 0 14px}
.member-card{display:grid;grid-template-columns:minmax(180px,2fr) minmax(130px,1fr) minmax(145px,1fr) minmax(130px,1fr) auto;gap:10px;align-items:center;margin:10px 0}
.member-card input,.member-card select{box-sizing:border-box;width:100%;min-width:0;min-height:42px;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;background:#fff}
.member-card .member-name{min-width:0}
@media(max-width:900px){.member-card{grid-template-columns:repeat(2,minmax(0,1fr))}.member-card .member-name{grid-column:1/-1}.member-card .remove-member{grid-column:1/-1}}
.wizard-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.wizard-button{border:0;border-radius:8px;padding:11px 16px;min-height:42px;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.wizard-primary{background:#166534;color:white}
.wizard-secondary{background:#f1f5f9;color:#334155}
.wizard-danger{background:#fee2e2;color:#991b1b}
.wizard-error{background:#fef2f2;color:#991b1b;padding:14px;border-radius:8px;margin-bottom:16px}
.wizard-summary{padding:16px;border:1px solid #cbd5e1;border-radius:10px;margin:12px 0}
.wizard-summary ul{padding-left:22px}
.wizard-status{font-size:14px;color:#475569}
@media(max-width:680px){.wizard-grid,.wizard-coordinates{grid-template-columns:1fr}.wizard-span{grid-column:auto}.wizard-progress{grid-template-columns:repeat(2,minmax(0,1fr))}#house-location-map{height:290px}}
</style>
@endpush

<style id="house-form-admin-redesign">
/* Gaya khusus wizard pada dashboard internal */
.admin-content .house-wizard {
    width: 100%;
    max-width: 1080px;
    margin: 0 auto 28px;
    padding: 0;
}
.admin-content .page-heading {
    display: block;
    margin-bottom: 22px;
}
.admin-content .page-heading .eyebrow {
    display: block;
    margin-bottom: 10px;
    color: #718076;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .15em;
}
.admin-content .page-heading h1 {
    margin: 0 0 9px;
    font-size: clamp(25px, 3vw, 35px);
    line-height: 1.2;
}
.admin-content .page-heading p {
    max-width: 720px;
    font-size: 12px;
    line-height: 1.75;
}
.admin-content .house-wizard .wizard-progress {
    position: relative;
    gap: 10px;
    margin: 0 0 18px;
}
.admin-content .house-wizard .wizard-step {
    min-width: 0;
    padding: 13px 10px;
    border-color: #e1e7de;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 700;
    transition: background .18s ease, border-color .18s ease;
}
.admin-content .house-wizard .wizard-step.active {
    background: #1e6b3c;
    border-color: #1e6b3c;
    color: #fff;
}
.admin-content .house-wizard .wizard-step.done {
    background: #eff8ef;
    border-color: #b9d9bd;
    color: #1e6b3c;
}
.admin-content .house-wizard #house-create-form {
    padding: 24px;
    border: 1px solid #e3e8e0;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(18, 35, 20, .035);
}
.admin-content .house-wizard .wizard-panel {
    padding: 4px 0 22px;
    border-bottom-color: #e8ede6;
}
.admin-content .house-wizard .wizard-panel h2 {
    margin-bottom: 8px;
    font-size: 20px;
    letter-spacing: -.025em;
}
.admin-content .house-wizard .wizard-panel h3 {
    margin-top: 20px;
    font-size: 15px;
}
.admin-content .house-wizard .wizard-help {
    font-size: 12px;
    line-height: 1.75;
}
.admin-content .house-wizard .wizard-field label {
    color: #465349;
    font-size: 12px;
}
.admin-content .house-wizard .wizard-field input,
.admin-content .house-wizard .wizard-field textarea,
.admin-content .house-wizard .member-card input,
.admin-content .house-wizard .member-card select {
    min-height: 44px;
    border-color: #dfe5dc;
    border-radius: 9px;
    font-size: 12px;
}
.admin-content .house-wizard .household-card {
    padding: 18px;
    border-color: #e1e7de;
    border-radius: 12px;
    background: #fcfdfa;
}
.admin-content .house-wizard #house-location-map {
    height: 360px;
    border-color: #dfe5dc;
    border-radius: 12px;
    overflow: hidden;
}
.admin-content .house-wizard .wizard-actions {
    justify-content: flex-end;
    padding-top: 20px;
}
.admin-content .house-wizard .wizard-button {
    min-height: 43px;
    padding: 11px 16px;
    border-radius: 9px;
    font-size: 12px;
}
.admin-content .house-wizard .wizard-primary {
    background: #151b17;
    color: #fff;
}
.admin-content .house-wizard .wizard-primary:hover {
    background: #29332c;
}
.admin-content .house-wizard .wizard-secondary {
    border: 1px solid #e0e6dd;
    background: #f6f8f4;
    color: #334238;
}
.admin-content .house-wizard .wizard-danger {
    background: #fff0ed;
    color: #a3342b;
}
@media (max-width: 700px) {
    .admin-content .house-wizard #house-create-form {
        padding: 16px;
        border-radius: 12px;
    }
    .admin-content .house-wizard .wizard-progress {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .admin-content .house-wizard .wizard-step {
        padding: 11px 6px;
        font-size: 11px;
    }
    .admin-content .house-wizard #house-location-map {
        height: 290px;
    }
    .admin-content .house-wizard .wizard-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .admin-content .house-wizard .wizard-actions > * {
        min-width: 0;
    }
}
</style>

@section('content')
<section class="page-heading">
    <span class="eyebrow">PENGELOLAAN DATA DESA</span>
    <h1>Tambah Bangunan dan Data KK</h1>
    <p>Satu bangunan memiliki satu titik peta dan dapat memiliki beberapa kartu keluarga.</p>
</section>

<section class="admin-wrap house-wizard">
    @if ($errors->any())
        <div class="wizard-error" role="alert">
            <strong>Data belum dapat diproses. Periksa hal berikut:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="wizard-progress" aria-label="Tahapan formulir">
        <div class="wizard-step active" data-indicator="1">1. Bangunan</div>
        <div class="wizard-step" data-indicator="2">2. KK</div>
        <div class="wizard-step" data-indicator="3">3. Anggota</div>
        <div class="wizard-step" data-indicator="4">4. Konfirmasi</div>
    </div>

    <form method="POST" action="{{ route('admin.houses.store') }}" id="house-create-form" novalidate>
        @csrf

        <section class="wizard-panel" data-steps="1">
            <h2>Data bangunan dan lokasi</h2>
            <p class="wizard-help">Kode bangunan dibuat otomatis setelah data berhasil disimpan. Label internal bersifat opsional dan tidak ditampilkan pada peta publik.</p>

            <div class="wizard-grid">
                <div class="wizard-field">
                    <label for="rt_code">RT *</label>
                    @if (auth()->user()->isRtAdmin())
                        <input type="text" value="RT {{ auth()->user()->rt_code }}" readonly aria-label="RT tugas akun">
                        <input type="hidden" id="rt_code" name="rt_code" value="{{ auth()->user()->rt_code }}">
                    @else
                        <input id="rt_code" name="rt_code" maxlength="20" value="{{ old('rt_code') }}" placeholder="Contoh: 01">
                    @endif
                </div>
                <div class="wizard-field">
                    <label for="house_label">Label internal bangunan (opsional)</label>
                    <input id="house_label" name="house_label" maxlength="200"
                        value="{{ old('house_label') }}" placeholder="Contoh: Rumah dekat pos ronda">
                </div>
                <div class="wizard-field wizard-span">
                    <label for="address">Alamat lengkap *</label>
                    <textarea id="address" name="address" rows="2" maxlength="500" required
                        placeholder="Nama jalan/dukuh, nomor rumah, RT/RW, atau patokan">{{ old('address') }}</textarea>
                </div>
            </div>

            <h3>Lokasi GPS bangunan</h3>
            <p class="wizard-help">Klik peta atau geser penanda ke posisi bangunan yang benar. Titik harus berada di dalam batas referensi yang diperiksa oleh server.</p>
            <div id="house-location-map" aria-label="Peta pemilihan koordinat bangunan"></div>
            <div class="wizard-actions" style="justify-content:flex-start;margin:12px 0">
                <button type="button" class="wizard-button wizard-secondary" id="use-device-location">◎ Gunakan lokasi perangkat</button>
                <span id="gps-permission-status" class="wizard-help" aria-live="polite">Tekan tombol untuk meminta izin GPS dari browser.</span>
            </div>
            <p id="house-map-status" class="wizard-status" aria-live="polite">Pilih titik rumah pada peta atau gunakan GPS perangkat. Pastikan titik sesuai lokasi bangunan.</p>

            <div class="wizard-coordinates">
                <div class="wizard-field">
                    <label for="latitude">Latitude *</label>
                    <input id="latitude" name="latitude" type="number" step="any" min="-90" max="90"
                        required value="{{ old('latitude') }}" placeholder="Contoh: -7.3463">
                </div>
                <div class="wizard-field">
                    <label for="longitude">Longitude *</label>
                    <input id="longitude" name="longitude" type="number" step="any" min="-180" max="180"
                        required value="{{ old('longitude') }}" placeholder="Contoh: 111.0083">
                </div>
            </div>
        </section>

        <section class="wizard-panel" data-steps="2,3" id="households-container" hidden>
            <div id="household-stage-heading">
                <h2>Data kartu keluarga</h2>
                <p class="wizard-help">Tambahkan setiap KK yang tinggal di bangunan ini. Nama kepala keluarga tidak digunakan sebagai nama marker peta.</p>
            </div>

            @php
                $oldHouseholds = old('households', [
                    ['head_of_family_name' => '', 'members' => [['full_name' => '']]]
                ]);
            @endphp

            <div id="household-list">
                @foreach ($oldHouseholds as $i => $household)
                    <article class="household-card" data-household>
                        <div class="household-head-fields">
                            <h3>KK <span data-household-number>{{ $i + 1 }}</span></h3>
                            <div class="wizard-field">
                                <label>Nama kepala keluarga *</label>
                                <input data-head-name name="households[{{ $i }}][head_of_family_name]"
                                    maxlength="200" required
                                    value="{{ $household['head_of_family_name'] ?? '' }}"
                                    placeholder="Masukkan nama kepala keluarga">
                            </div>
                            <button type="button" class="wizard-button wizard-danger remove-household" style="margin-top:12px">
                                Hapus KK ini
                            </button>
                        </div>

                        <div class="household-member-fields">
                            <h3>Anggota untuk KK <span data-household-number>{{ $i + 1 }}</span></h3>
                            <p class="wizard-help">Masukkan anggota selain kepala keluarga jika ada. Data anggota boleh dikosongkan.</p>
                            <div data-member-list>
                                @foreach (($household['members'] ?? []) as $j => $member)
                                    <div class="member-card" data-member>
                                        <input class="member-name" name="households[{{ $i }}][members][{{ $j }}][full_name]"
                                            maxlength="200" required aria-label="Nama anggota keluarga"
                                            value="{{ $member['full_name'] ?? '' }}"
                                            placeholder="Nama anggota keluarga">
                                        <select class="member-relationship" name="households[{{ $i }}][members][{{ $j }}][relationship]" aria-label="Hubungan keluarga">
                                            <option value="">Hubungan (opsional)</option>
                                            @foreach (['Istri','Suami','Anak','Orang Tua','Saudara','Lainnya'] as $relation)
                                                <option value="{{ $relation }}" @selected(($member['relationship'] ?? '') === $relation)>{{ $relation }}</option>
                                            @endforeach
                                        </select>
                                        <input class="member-dob" type="date" name="households[{{ $i }}][members][{{ $j }}][date_of_birth]"
                                            aria-label="Tanggal lahir" max="{{ date('Y-m-d') }}"
                                            value="{{ $member['date_of_birth'] ?? '' }}" title="Tanggal lahir (opsional)">
                                        <select class="member-gender" name="households[{{ $i }}][members][{{ $j }}][gender]" aria-label="Jenis kelamin">
                                            <option value="">Jenis kelamin (opsional)</option>
                                            <option value="Laki-laki" @selected(($member['gender'] ?? '') === 'Laki-laki')>Laki-laki</option>
                                            <option value="Perempuan" @selected(($member['gender'] ?? '') === 'Perempuan')>Perempuan</option>
                                        </select>
                                        <button type="button" class="wizard-button wizard-danger remove-member">Hapus</button>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="wizard-button wizard-secondary add-member">+ Tambah anggota</button>
                        </div>
                    </article>
                @endforeach
            </div>

            <button type="button" class="wizard-button wizard-secondary" id="add-household">+ Tambah KK</button>
        </section>

        <section class="wizard-panel" data-steps="4" hidden>
            <h2>Konfirmasi data</h2>
            <div class="wizard-field" style="margin:16px 0;padding:16px;border:1px solid #d9e7d9;border-radius:12px;background:#f5faf5">
                <label style="display:flex;gap:10px;align-items:flex-start;font-weight:600;color:#254b31">
                    <input type="checkbox" name="public_visible" value="1" {{ old('public_visible') ? 'checked' : '' }} style="width:18px;min-height:18px;margin-top:2px">
                    <span>Saya menyatakan izin warga/pemilik rumah untuk menampilkan titik lokasi rumah secara umum pada peta publik telah diperoleh. Saya memahami bahwa nama kepala keluarga, nomor KK, nama anggota keluarga, dan alamat rinci tidak akan ditampilkan.</span>
                </label>
                <p class="wizard-help" style="margin:8px 0 0 28px">Jika izin belum diperoleh, jangan centang. Data tetap dapat disimpan dan diperiksa, tetapi tidak dapat dipublikasikan.</p>
            </div>
            <p class="wizard-help">Periksa ringkasan berikut sebelum menyimpan. Semua data akan disimpan dalam satu transaksi.</p>
            <div class="wizard-summary" id="confirmation-summary"></div>
            <p class="wizard-help">Status titik baru akan menjadi menunggu verifikasi dan belum dipublikasikan.</p>
        </section>

        <div class="wizard-actions">
            <button type="button" class="wizard-button wizard-secondary" id="wizard-back" hidden>Kembali</button>
            <button type="button" class="wizard-button wizard-primary" id="wizard-next">Lanjut</button>
            <button type="submit" class="wizard-button wizard-primary" id="wizard-submit" hidden>Simpan data</button>
            <a href="{{ route('admin.houses.index') }}" class="wizard-button wizard-secondary">Batal</a>
        </div>
    </form>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('house-create-form');
    const panels = Array.from(form.querySelectorAll('[data-steps]'));
    const indicators = Array.from(document.querySelectorAll('[data-indicator]'));
    const backButton = document.getElementById('wizard-back');
    const nextButton = document.getElementById('wizard-next');
    const submitButton = document.getElementById('wizard-submit');
    const householdList = document.getElementById('household-list');
    const mapStatus = document.getElementById('house-map-status');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    let currentStep = 1;

    function reindexHouseholds() {
        Array.from(householdList.querySelectorAll('[data-household]')).forEach(function (card, i) {
            card.querySelectorAll('[data-household-number]').forEach(el => el.textContent = String(i + 1));
            const head = card.querySelector('[data-head-name]');
            head.name = 'households[' + i + '][head_of_family_name]';

            const members = card.querySelector('[data-member-list]');
            Array.from(members.querySelectorAll('[data-member]')).forEach(function (member, j) {
                member.querySelector('.member-name').name = 'households[' + i + '][members][' + j + '][full_name]';
                member.querySelector('.member-relationship').name = 'households[' + i + '][members][' + j + '][relationship]';
                member.querySelector('.member-dob').name = 'households[' + i + '][members][' + j + '][date_of_birth]';
                member.querySelector('.member-gender').name = 'households[' + i + '][members][' + j + '][gender]';
            });
        });
    }

    function showStep(step) {
        currentStep = step;
        panels.forEach(function (panel) {
            const steps = panel.dataset.steps.split(',').map(Number);
            const visible = steps.includes(step);
            panel.hidden = !visible;

            // Panel KK dan anggota sama; tampilkan bagian sesuai tahap aktif.
            if (panel.id === 'households-container') {
                panel.querySelectorAll('.household-head-fields').forEach(el => el.hidden = step !== 2);
                panel.querySelectorAll('.household-member-fields').forEach(el => el.hidden = step !== 3);
                document.getElementById('household-stage-heading').hidden = step !== 2;
                document.getElementById('add-household').hidden = step !== 2;
            }
        });

        indicators.forEach(function (indicator) {
            const n = Number(indicator.dataset.indicator);
            indicator.classList.toggle('active', n === step);
            indicator.classList.toggle('done', n < step);
        });

        backButton.hidden = step === 1;
        nextButton.hidden = step === 4;
        submitButton.hidden = step !== 4;
        if (step === 4) buildSummary();

        // Leaflet perlu menghitung ulang ukuran setelah panel ditampilkan.
        if (step === 1 && window.houseLocationMap) {
            setTimeout(() => window.houseLocationMap.invalidateSize(), 100);
        }
    }

    function validateStep(step) {
        let fields = [];

        if (step === 1) {
            fields = [document.getElementById('address'), latInput, lngInput];
        } else if (step === 2) {
            fields = Array.from(householdList.querySelectorAll('[data-head-name]'));
        } else if (step === 3) {
            fields = Array.from(householdList.querySelectorAll('[data-member] input'));
        }

        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }

        if (step === 1 && Number(latInput.value) === 0 && Number(lngInput.value) === 0) {
            alert('Koordinat masih 0,0. Gunakan GPS perangkat atau pilih titik rumah pada peta.');
            return false;
        }

        if (step === 2 && householdList.querySelectorAll('[data-household]').length < 1) {
            alert('Tambahkan minimal satu kartu keluarga.');
            return false;
        }

        return true;
    }

    function buildSummary() {
        const cards = Array.from(householdList.querySelectorAll('[data-household]'));
        let html = '<p><strong>RT:</strong> ' + escapeHtml(document.getElementById('rt_code')?.value || 'Belum ditentukan') + '</p>';
        html += '<p><strong>Alamat:</strong> ' + escapeHtml(document.getElementById('address').value || '—') + '</p>';
        html += '<p><strong>Label internal:</strong> ' + escapeHtml(document.getElementById('house_label').value || 'Tidak diisi') + '</p>';
        html += '<p><strong>Latitude:</strong> ' + escapeHtml(latInput.value) + '<br><strong>Longitude:</strong> ' + escapeHtml(lngInput.value) + '</p>';
        html += '<p><strong>Jumlah bangunan:</strong> 1 titik peta</p><p><strong>Jumlah KK:</strong> ' + cards.length + '</p><ul>';

        cards.forEach(function (card, i) {
            const head = card.querySelector('[data-head-name]').value.trim();
            const members = Array.from(card.querySelectorAll('[data-member]')).map(function (member) {
                return {
                    name: member.querySelector('[name$="[full_name]"]').value.trim(),
                    relationship: member.querySelector('[name$="[relationship]"]').value,
                    dob: member.querySelector('[name$="[date_of_birth]"]').value,
                    gender: member.querySelector('[name$="[gender]"]').value
                };
            }).filter(member => member.name);
            html += '<li><strong>KK ' + (i + 1) + ':</strong> ' + escapeHtml(head) +
                ' — ' + members.length + ' anggota';
            if (members.length) {
                html += '<ul>' + members.map(function (member) {
                    const details = [member.relationship, member.dob, member.gender].filter(Boolean).map(escapeHtml);
                    return '<li>' + escapeHtml(member.name) + (details.length ? ' (' + details.join(' · ') + ')' : '') + '</li>';
                }).join('') + '</ul>';
            }
            html += '</li>';
        });

        html += '</ul>';
        document.getElementById('confirmation-summary').innerHTML = html;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
        });
    }

    nextButton.addEventListener('click', function () {
        if (!validateStep(currentStep)) return;
        if (currentStep < 4) showStep(currentStep + 1);
    });

    backButton.addEventListener('click', function () {
        if (currentStep > 1) showStep(currentStep - 1);
    });

    document.getElementById('add-household').addEventListener('click', function () {
        const cards = householdList.querySelectorAll('[data-household]');
        if (cards.length >= 20) {
            alert('Maksimal 20 KK per bangunan.');
            return;
        }

        const card = document.createElement('article');
        card.className = 'household-card';
        card.setAttribute('data-household', '');
        card.innerHTML = `
            <div class="household-head-fields">
                <h3>KK <span data-household-number></span></h3>
                <div class="wizard-field">
                    <label>Nama kepala keluarga *</label>
                    <input data-head-name maxlength="200" required placeholder="Masukkan nama kepala keluarga">
                </div>
                <button type="button" class="wizard-button wizard-danger remove-household" style="margin-top:12px">Hapus KK ini</button>
            </div>
            <div class="household-member-fields" hidden>
                <h3>Anggota untuk KK <span data-household-number></span></h3>
                <p class="wizard-help">Masukkan anggota selain kepala keluarga jika ada. Data anggota boleh dikosongkan.</p>
                <div data-member-list></div>
                <button type="button" class="wizard-button wizard-secondary add-member">+ Tambah anggota</button>
            </div>`;
        householdList.appendChild(card);
        reindexHouseholds();
    });

    householdList.addEventListener('click', function (event) {
        const card = event.target.closest('[data-household]');
        if (!card) return;

        if (event.target.closest('.remove-household')) {
            if (householdList.querySelectorAll('[data-household]').length <= 1) {
                alert('Minimal satu KK harus tersedia.');
                return;
            }
            card.remove();
            reindexHouseholds();
        }

        if (event.target.closest('.add-member')) {
            const list = card.querySelector('[data-member-list]');
            if (list.querySelectorAll('[data-member]').length >= 30) {
                alert('Maksimal 30 anggota per KK.');
                return;
            }

            const member = document.createElement('div');
            member.className = 'member-card';
            member.setAttribute('data-member', '');
            member.innerHTML = `
                <input class="member-name" maxlength="200" required aria-label="Nama anggota keluarga" placeholder="Nama anggota keluarga">
                <select class="member-relationship" aria-label="Hubungan keluarga">
                    <option value="">Hubungan (opsional)</option>
                    <option value="Istri">Istri</option><option value="Suami">Suami</option>
                    <option value="Anak">Anak</option><option value="Orang Tua">Orang Tua</option>
                    <option value="Saudara">Saudara</option><option value="Lainnya">Lainnya</option>
                </select>
                <input class="member-dob" type="date" aria-label="Tanggal lahir" max="{{ date('Y-m-d') }}" title="Tanggal lahir (opsional)">
                <select class="member-gender" aria-label="Jenis kelamin (opsional)">
                    <option value="">Jenis kelamin (opsional)</option>
                    <option value="Laki-laki">Laki-laki</option><option value="Perempuan">Perempuan</option>
                </select>
                <button type="button" class="wizard-button wizard-danger remove-member">Hapus</button>`;
            list.appendChild(member);
            reindexHouseholds();
        }

        if (event.target.closest('.remove-member')) {
            event.target.closest('[data-member]').remove();
            reindexHouseholds();
        }
    });

    form.addEventListener('submit', function (event) {
        reindexHouseholds();

        for (const step of [1, 2, 3]) {
            if (!validateStep(step)) {
                event.preventDefault();
                showStep(step);
                return;
            }
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            alert('Periksa kembali data yang wajib diisi.');
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Menyimpan...';
    });

    // Peta lokasi
    const mapElement = document.getElementById('house-location-map');

    function setCoordinates(lat, lng) {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
        mapStatus.textContent = 'Titik terpilih: ' + latInput.value + ', ' + lngInput.value;
    }

    if (window.L && mapElement) {
        const map = L.map(mapElement).setView([-7.3457585, 110.9891281], 16);
        window.houseLocationMap = map;

        L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                minZoom: 3,
                maxNativeZoom: 18,
                maxZoom: 22,
                attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics, and the GIS User Community'
            }
        ).addTo(map);

        fetch('/data/Pilangsari_Referensi.geojson', {
            headers: {'Accept': 'application/geo+json, application/json'}
        })
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(geojson => {
            const boundary = L.geoJSON(geojson, {
                style: {color:'#0284c7', weight:3, fillColor:'#38bdf8', fillOpacity:0.12},
                interactive: false
            }).addTo(map);

            if (boundary.getBounds().isValid()) {
                // Batas ditampilkan tanpa menggeser pusat awal peta.
                setTimeout(() => map.invalidateSize(), 150);
            }

            mapStatus.textContent = 'Batas referensi ditampilkan. Batas ini belum dikonfirmasi sebagai batas administrasi resmi.';
        })
        .catch(error => {
            console.error(error);
            mapStatus.textContent = 'Batas referensi gagal dimuat. Periksa GeoJSON sebelum memilih koordinat.';
        });

        const initialLat = Number(latInput.value);
        const initialLng = Number(lngInput.value);
        const hasOldCoords = latInput.value !== '' && lngInput.value !== ''
            && Number.isFinite(initialLat) && Number.isFinite(initialLng)
            && !(initialLat === 0 && initialLng === 0);

        const marker = L.marker(
            hasOldCoords ? [initialLat, initialLng] : [-7.3457585, 110.9891281],
            {draggable:true}
        ).addTo(map);

        window.houseLocationMarker = marker;

        map.on('click', event => {
            marker.setLatLng(event.latlng);
            setCoordinates(event.latlng.lat, event.latlng.lng);
        });

        marker.on('dragend', event => {
            const point = event.target.getLatLng();
            setCoordinates(point.lat, point.lng);
        });

        if (hasOldCoords) {
            map.setView([initialLat, initialLng], 17);
            setCoordinates(initialLat, initialLng);
        } else {
            mapStatus.textContent = 'Lokasi belum dipilih. Gunakan GPS perangkat atau klik peta tepat pada bangunan.';
        }
    } else {
        mapStatus.textContent = 'Pustaka peta gagal dimuat. Isi koordinat GPS hasil survei secara manual.';
    }


    const gpsButton = document.getElementById('use-device-location');
    const gpsStatus = document.getElementById('gps-permission-status');
    if (gpsButton) {
        gpsButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                gpsStatus.textContent = 'Browser ini tidak mendukung GPS. Pilih titik secara manual pada peta.';
                return;
            }
            gpsButton.disabled = true;
            gpsButton.textContent = 'Mengambil lokasi...';
            gpsStatus.textContent = 'Menunggu izin lokasi perangkat. Aktifkan GPS dan izinkan akses lokasi pada browser.';
            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                setCoordinates(lat, lng);
                if (window.houseLocationMap && window.houseLocationMarker) {
                    window.houseLocationMarker.setLatLng([lat, lng]);
                    window.houseLocationMap.setView([lat, lng], 19);
                }
                gpsStatus.textContent = 'Lokasi perangkat berhasil diambil. Periksa kembali apakah pin tepat di rumah.';
                gpsButton.disabled = false;
                gpsButton.textContent = '◎ Ambil ulang lokasi';
            }, function (error) {
                const messages = {
                    1: 'Izin lokasi ditolak. Izinkan lokasi untuk situs ini melalui ikon pengaturan di samping alamat browser.',
                    2: 'Lokasi tidak tersedia. Aktifkan GPS/perangkat lokasi lalu coba lagi.',
                    3: 'Permintaan GPS terlalu lama. Coba lagi di area dengan sinyal lebih baik.'
                };
                gpsStatus.textContent = messages[error.code] || 'GPS gagal diambil. Pilih titik secara manual pada peta.';
                gpsButton.disabled = false;
                gpsButton.textContent = '◎ Coba GPS lagi';
            }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
        });
    }

    reindexHouseholds();
    showStep(1);
});
</script>
@endpush
