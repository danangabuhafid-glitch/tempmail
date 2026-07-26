<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAlias;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AliasController extends Controller
{
    public function index(Request $request)
    {
        $aliases = EmailAlias::orderByDesc('is_pinned')->orderByDesc('created_at')->get();

        // Statistik email per alamat (jumlah + terakhir menerima)
        $stats = Email::selectRaw('alias, domain, COUNT(*) as total, MAX(received_at) as last_received')
            ->groupBy('alias', 'domain')
            ->get()
            ->keyBy(function ($row) {
                return $row->alias . '@' . $row->domain;
            });

        $domains = config('tempmail.domains');

        // Request AJAX cukup dijawab partial daftar
        if ($request->ajax()) {
            return view('alias._list', compact('aliases', 'stats'))->render();
        }

        return view('alias.index', compact('aliases', 'stats', 'domains'));
    }

    public function store(Request $request)
    {
        // Mode default manual — kompatibel dengan pemanggil lama tanpa field mode
        $request->merge(['mode' => $request->input('mode', 'manual')]);

        $data = $request->validate([
            'mode' => ['required', 'in:manual,prefix,otomatis'],
            'alias' => ['nullable', 'required_if:mode,manual', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'prefix' => ['nullable', 'required_if:mode,prefix', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'domain' => ['required', 'in:' . implode(',', config('tempmail.domains'))],
            'label' => ['nullable', 'string', 'max:100'],
            'ttl' => ['nullable', 'in:1h,24h,7d'],
        ], [
            'alias.regex' => 'Alias hanya boleh huruf, angka, titik, strip, dan underscore.',
            'prefix.regex' => 'Prefix hanya boleh huruf, angka, titik, strip, dan underscore.',
            'alias.required_if' => 'Alias wajib diisi pada mode manual.',
            'prefix.required_if' => 'Prefix wajib diisi pada mode prefix.',
        ]);

        $mode = $data['mode'];
        $domain = $data['domain'];
        // Mode manual selalu 1 alamat; prefix/otomatis sesuai quantity
        $jumlah = $mode === 'manual' ? 1 : max(1, (int) ($data['quantity'] ?? 1));

        // Password acak UNIK per akun — kalau satu password dipakai sepaket,
        // penerima satu alamat bisa login ke kotak masuk alamat lain sebatch.
        // Ditampilkan sekali di hasil; wajib diganti saat login pertama.
        $kredensial = [];

        // Alias sekali pakai: setelah lewat masa aktif, email baru DITOLAK
        $expiresAt = match ($data['ttl'] ?? null) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            default => null,
        };

        $created = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $alias = null;

            // Coba beberapa kali kalau alias hasil generate kebetulan tabrakan
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $kandidat = match ($mode) {
                    'manual' => Str::lower($data['alias']),
                    'prefix' => Str::lower($data['prefix']) . '-' . Str::lower(Str::random(4)),
                    'otomatis' => Str::lower(Str::random(8)),
                };

                $sudahAda = EmailAlias::where('alias', $kandidat)->where('domain', $domain)->exists();
                if (!$sudahAda) {
                    $alias = $kandidat;
                    break;
                }

                if ($mode === 'manual') {
                    if ($request->expectsJson()) {
                        return response()->json(['success' => false, 'message' => 'Alamat itu sudah tersimpan.'], 422);
                    }
                    return back()->withErrors(['alias' => 'Alamat itu sudah tersimpan.'])->withInput();
                }
            }

            if ($alias === null) continue; // 5x tabrakan beruntun — lewati slot ini

            EmailAlias::create([
                'alias' => $alias,
                'domain' => $domain,
                'label' => $data['label'] ?? null,
                'expires_at' => $expiresAt,
            ]);

            // Tiap alamat sekaligus jadi akun login terbatas (hanya lihat inbox-nya
            // sendiri). Akun yang sudah ada TIDAK ditimpa — jangan pernah me-reset
            // password / menurunkan role akun lain (termasuk owner) diam-diam.
            if (!User::where('email', $alias . '@' . $domain)->exists()) {
                $plain = Str::password(12, symbols: false);
                User::create([
                    'email' => $alias . '@' . $domain,
                    'name' => $alias,
                    'password' => Hash::make($plain),
                    'role' => 'alias',
                    'must_change_password' => true,
                ]);
                $kredensial[$alias . '@' . $domain] = $plain;
            }

            $created[] = $alias . '@' . $domain;
        }

        $pesan = count($created) === 1
            ? "Alamat {$created[0]} siap dipakai. Login panel: alamat itu + password " . ($kredensial[$created[0]] ?? '(akun sudah ada sebelumnya)') . ' (wajib diganti saat login pertama).'
            : count($created) . ' alamat baru siap dipakai. Tiap akun punya password sendiri — daftarnya tampil sekali ini saja.';

        if ($request->expectsJson()) {
            $respon = ['success' => true, 'message' => $pesan];

            // Pembuatan massal: kirim daftar alamat + password masing-masing
            // (tampil sekali ini saja) untuk popup + tombol salin semua
            if (count($created) > 1) {
                $daftar = collect($created)
                    ->map(fn ($a) => '<div style="margin:.25rem 0"><code>' . e($a) . '</code>'
                        . (isset($kredensial[$a]) ? ' <span style="opacity:.7">· ' . e($kredensial[$a]) . '</span>' : '')
                        . '</div>')
                    ->implode('');
                $salin = collect($created)
                    ->map(fn ($a) => $a . (isset($kredensial[$a]) ? ' ' . $kredensial[$a] : ''))
                    ->implode('&#10;');

                $respon['popup_html'] = "<div style=\"text-align:left;max-height:300px;overflow:auto\">{$daftar}</div>"
                    . '<div class="small text-muted mt-2">Tiap akun punya password sendiri — salin sekarang, tidak ditampilkan lagi.</div>'
                    . '<button type="button" class="btn btn-sm btn-light border mt-3" data-copy="' . $salin . '">'
                    . '<i class="bi bi-clipboard me-1"></i>Salin semua</button>';
            }

            return response()->json($respon);
        }

        return redirect()->route('alias.index')->with('success', $pesan);
    }

    // Halaman mini untuk bookmarklet: buat alias bernama situs, tampilkan + salin.
    // Dibuka via window.open dari bookmarklet di browser (sesi login owner).
    public function cepat(Request $request)
    {
        $data = $request->validate([
            'site' => ['nullable', 'string', 'max:100'],
        ]);

        $alias = EmailAlias::buatCepat($data['site'] ?? 'situs');
        abort_unless($alias, 500, 'Gagal membuat alias — coba lagi.');

        return view('alias.cepat', ['alias' => $alias]);
    }

    // Ubah label alamat kapan saja (sebelumnya label terkunci setelah dibuat)
    public function updateLabel(Request $request, EmailAlias $alias)
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $alias->update(['label' => $data['label'] ?: null]);

        return response()->json(['success' => true, 'message' => 'Label diperbarui.']);
    }

    public function togglePin(Request $request, EmailAlias $alias)
    {
        $alias->update(['is_pinned' => !$alias->is_pinned]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $alias->is_pinned ? 'Alamat disematkan.' : 'Pin dilepas.']);
        }

        return redirect()->route('alias.index');
    }

    public function destroy(Request $request, EmailAlias $alias)
    {
        // Hanya menghapus dari daftar kelola — email yang sudah masuk tetap ada
        // dan alamatnya tetap berfungsi (sifat catch-all). Akun login-nya ikut dicabut.
        User::where('email', $alias->full_address)->where('role', 'alias')->delete();
        $alias->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Alamat dihapus dari daftar.']);
        }

        return redirect()->route('alias.index')->with('success', 'Alamat dihapus dari daftar.');
    }
}
