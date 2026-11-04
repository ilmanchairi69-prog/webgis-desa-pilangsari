<aside class="complaint-widget" data-complaint-widget>
    <section class="complaint-panel" id="complaint-panel" aria-labelledby="complaint-heading" hidden>
        <div class="complaint-heading"><div><h2 id="complaint-heading">Pengaduan dan Aspirasi</h2><p>Sampaikan informasi dan aspirasi Anda kepada pemerintah desa.</p></div><button type="button" data-complaint-close aria-label="Tutup formulir">&times;</button></div>
        <form data-complaint-form action="{{ route('complaints.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="submission_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <label>Nama Lengkap <span>*</span><input name="name" required maxlength="120" placeholder="Masukkan nama lengkap Anda" autocomplete="name"></label>
            <label>Nomor Telepon/WA <span>*</span><input name="phone" type="tel" required maxlength="25" placeholder="Masukkan nomor HP/WhatsApp" autocomplete="tel"></label>
            <label>Kategori Pengaduan <span>*</span><select name="category" required><option value="">Pilih kategori</option>@foreach(\App\Models\Complaint::CATEGORIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
            <label>Pengaduan <span>*</span><textarea name="description" required maxlength="5000" rows="4" placeholder="Masukkan kesan, informasi, atau detail aduan Anda"></textarea><small><span data-complaint-count>0</span>/5.000 karakter</small></label>
            <label>Lampiran <small>(opsional, JPG, PNG, WEBP atau PDF; maks. 5 MB)</small><input name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"></label>
            @if(config('services.turnstile.site_key') && config('services.turnstile.secret'))<div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-callback="complaintTurnstileSuccess" data-expired-callback="complaintTurnstileExpired" data-error-callback="complaintTurnstileError"></div>@else<p class="complaint-unavailable">Layanan pengaduan belum tersedia saat ini.</p>@endif
            <p class="complaint-feedback" data-complaint-feedback role="status" aria-live="polite"></p>
            <button class="complaint-submit" type="submit" disabled>Kirim</button>
        </form>
    </section>
    <button class="complaint-toggle" type="button" data-complaint-toggle aria-controls="complaint-panel" aria-expanded="false"><span aria-hidden="true">&#9743;</span> <span>Pengaduan</span></button>
</aside>
