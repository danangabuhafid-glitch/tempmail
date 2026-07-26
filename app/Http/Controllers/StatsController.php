<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Statistik pemakaian inbox: tren harian, pengirim & alamat teraktif,
 * komposisi spam/OTP. Khusus pemilik.
 */
class StatsController extends Controller
{
    public function index(Request $request)
    {
        $hari = (int) $request->integer('hari', 30);
        $hari = max(7, min($hari, 365));
        $sejak = now()->subDays($hari)->startOfDay();

        // Tren harian (diisi nol untuk hari tanpa email agar grafiknya utuh)
        $perHariDb = Email::withTrashed()
            ->where('received_at', '>=', $sejak)
            ->selectRaw('DATE(received_at) as tgl, COUNT(*) as total')
            ->groupBy('tgl')
            ->pluck('total', 'tgl');

        $perHari = [];
        for ($d = $sejak->copy(); $d->lte(now()); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $perHari[$key] = (int) ($perHariDb[$key] ?? 0);
        }

        $dasar = fn () => Email::withTrashed()->where('received_at', '>=', $sejak);

        return view('stats.index', [
            'hari' => $hari,
            'perHari' => $perHari,
            'total' => $dasar()->count(),
            'totalSpam' => $dasar()->where('is_spam', true)->count(),
            'totalOtp' => $dasar()->whereNotNull('otp_code')->count(),
            'totalUkuranMb' => round((int) $dasar()->sum('size_bytes') / 1048576, 1),
            'rataPerHari' => round($dasar()->count() / max(1, $hari), 1),

            'pengirimTeratas' => $dasar()->whereNotNull('from_address')
                ->select('from_address', DB::raw('COUNT(*) as total'))
                ->groupBy('from_address')->orderByDesc('total')->limit(10)->get(),

            'alamatTeraktif' => $dasar()
                ->select('to_address', DB::raw('COUNT(*) as total'))
                ->groupBy('to_address')->orderByDesc('total')->limit(10)->get(),

            'perDomain' => $dasar()
                ->select('domain', DB::raw('COUNT(*) as total'))
                ->groupBy('domain')->orderByDesc('total')->get(),

            // Jam tersibuk (0-23) — kapan biasanya email masuk
            'perJam' => $this->perJam($sejak),
        ]);
    }

    private function perJam(\Carbon\Carbon $sejak): array
    {
        $kolom = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', received_at) AS INTEGER)"
            : 'HOUR(received_at)';

        $data = Email::withTrashed()->where('received_at', '>=', $sejak)
            ->selectRaw("{$kolom} as jam, COUNT(*) as total")
            ->groupBy('jam')
            ->pluck('total', 'jam');

        $hasil = [];
        for ($j = 0; $j < 24; $j++) {
            $hasil[$j] = (int) ($data[$j] ?? 0);
        }

        return $hasil;
    }
}
