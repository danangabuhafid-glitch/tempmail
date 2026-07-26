{{-- Form buat alamat (dipakai modal inbox & halaman Alamat).
     Var: $domains, $refreshTarget (selector partial yang di-refresh), $uid (prefix id unik) --}}
@php $uid = $uid ?? 'f'; @endphp

<form method="POST" action="{{ route('alias.store') }}" class="alias-form" data-ajax data-reset data-refresh="{{ $refreshTarget ?? '#alias-list' }}">
    @csrf

    {{-- Pilihan mode pembuatan --}}
    <div class="btn-group w-100 mb-3" role="group">
        <input type="radio" class="btn-check" name="mode" id="{{ $uid }}-mode-manual" value="manual" checked>
        <label class="btn btn-sm tm-seg" for="{{ $uid }}-mode-manual"><i class="bi bi-pencil me-1"></i>Manual</label>

        <input type="radio" class="btn-check" name="mode" id="{{ $uid }}-mode-prefix" value="prefix">
        <label class="btn btn-sm tm-seg" for="{{ $uid }}-mode-prefix"><i class="bi bi-tag me-1"></i>Prefix</label>

        <input type="radio" class="btn-check" name="mode" id="{{ $uid }}-mode-otomatis" value="otomatis">
        <label class="btn btn-sm tm-seg" for="{{ $uid }}-mode-otomatis"><i class="bi bi-shuffle me-1"></i>Otomatis</label>
    </div>

    {{-- Manual: ketik alias sendiri --}}
    <div class="mb-3 f-manual">
        <label class="form-label small fw-semibold">Alias</label>
        <div class="input-group">
            <input type="text" name="alias" class="form-control" placeholder="mis. belanja">
            <button type="button" class="btn btn-light border btn-shuffle" title="Isi alias acak"><i class="bi bi-shuffle"></i></button>
        </div>
    </div>

    {{-- Prefix: awalan + akhiran acak --}}
    <div class="mb-3 f-prefix d-none">
        <label class="form-label small fw-semibold">Prefix</label>
        <input type="text" name="prefix" class="form-control" placeholder="mis. belanja">
        <div class="form-text">Hasil: <code>prefix-x4k9</code>, <code>prefix-2mqa</code>, dst.</div>
    </div>

    {{-- Jumlah: untuk prefix & otomatis --}}
    <div class="mb-3 f-qty d-none">
        <label class="form-label small fw-semibold">Jumlah alamat</label>
        <input type="number" name="quantity" class="form-control" min="1" max="50" value="5">
        <div class="form-text">Maksimal 50 sekaligus.</div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Domain</label>
        <select name="domain" class="form-select" required>
            @foreach($domains as $d)
                <option value="{{ $d }}">{{ $d }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Label <span class="text-muted fw-normal">(opsional)</span></label>
        <input type="text" name="label" class="form-control" placeholder="mis. Buat daftar marketplace">
    </div>

    {{-- Alias sekali pakai: setelah masa aktif lewat, email baru DITOLAK --}}
    <div class="mb-4">
        <label class="form-label small fw-semibold">Masa aktif</label>
        <select name="ttl" class="form-select">
            <option value="">Permanen</option>
            <option value="1h">1 jam (sekali pakai)</option>
            <option value="24h">24 jam</option>
            <option value="7d">7 hari</option>
        </select>
        <div class="form-text">Setelah lewat, email ke alamat ini ditolak (bounce) — cocok untuk signup sekali pakai.</div>
    </div>

    <button type="submit" class="btn btn-tm w-100"><i class="bi bi-plus-lg me-1"></i><span class="submit-text">Simpan Alamat</span></button>
</form>
