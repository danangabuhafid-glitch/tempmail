@extends('layouts.app')

@section('title', 'Alamat Email — TempMail')

@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-plus-circle me-1" style="color: var(--tm-accent)"></i>Buat Alamat Baru</h5>
                <p class="text-muted small mb-3">Berkat catch-all, alamat apa pun sebenarnya sudah aktif — form ini untuk menyimpan &amp; memberi label alamat yang sengaja kamu pakai. Mode Prefix &amp; Otomatis bisa membuat sampai 50 alamat sekaligus.</p>

                @include('alias._form', ['refreshTarget' => '#alias-list', 'uid' => 'pg'])
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-at me-1" style="color: var(--tm-accent)"></i>Alamat Tersimpan</h5>
                <div id="alias-list" data-refresh-url="{{ route('alias.index') }}">
                    @include('alias._list')
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Ubah label alamat lewat dialog kecil (delegasi — tetap hidup setelah refresh AJAX)
    document.getElementById('alias-list').addEventListener('click', async function (e) {
        const editBtn = e.target.closest('.btn-edit-label');
        if (editBtn) {
            const { value, isConfirmed } = await Swal.fire({
                title: 'Label alamat',
                input: 'text',
                inputValue: editBtn.dataset.label || '',
                inputPlaceholder: 'mis. buat daftar Netflix',
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#6d5df6',
                inputAttributes: { maxlength: 100 },
            });
            if (!isConfirmed) return;

            try {
                const res = await fetch(editBtn.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ _method: 'PATCH', label: value || '' }),
                });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    tmToast('success', data.message);
                    tmRefresh('#alias-list');
                } else {
                    tmToast('error', data.message || 'Gagal menyimpan label.');
                }
            } catch (err) {
                tmToast('error', 'Tidak bisa menghubungi server.');
            }
            return;
        }

        // QR code alamat — scan dari HP untuk langsung memakai alamatnya
        const qrBtn = e.target.closest('.btn-qr');
        if (qrBtn) {
            Swal.fire({
                title: qrBtn.dataset.address,
                html: '<div id="swal-qr" style="display:flex;justify-content:center;padding:8px;background:#fff;border-radius:12px"></div>'
                    + '<p class="small mt-2" style="color:#7a8099">Scan dari HP — atau salin: '
                    + `<button type="button" class="btn btn-sm btn-light border" data-copy="${qrBtn.dataset.address}">salin alamat</button></p>`,
                showConfirmButton: false,
                showCloseButton: true,
                didOpen: () => new QRCode(document.getElementById('swal-qr'), {
                    text: 'mailto:' + qrBtn.dataset.address, width: 190, height: 190,
                }),
            });
        }
    });
</script>
@endsection
