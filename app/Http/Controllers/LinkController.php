<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Halaman perantara anti-phishing: semua link di email diarahkan ke sini dulu.
 * Menampilkan URL tujuan sebenarnya + peringatan, dan membersihkan parameter pelacak.
 */
class LinkController extends Controller
{
    // Parameter pelacak yang dibuang dari URL tujuan
    private const TRACKERS = [
        'fbclid', 'gclid', 'dclid', 'msclkid', 'yclid', 'igshid', 'twclid',
        'mc_cid', 'mc_eid', '_hsenc', '_hsmi', 'vero_id', 'oly_anon_id', 'oly_enc_id',
    ];

    public function keluar(Request $request)
    {
        $url = base64_decode(strtr((string) $request->query('u'), '-_', '+/'), true);

        $parts = $url === false ? false : parse_url($url);
        abort_unless($parts !== false && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && filled($parts['host'] ?? null), 404);

        $host = $parts['host'];
        $bersih = $this->stripTrackers($url, $parts);

        return view('link.keluar', [
            'url' => $url,
            'urlBersih' => $bersih,
            'host' => $host,
            // Peringatan homograph: domain punycode (xn--) bisa meniru domain asli
            'punycode' => str_contains($host, 'xn--'),
            'ipHost' => (bool) filter_var($host, FILTER_VALIDATE_IP),
            'insecure' => ($parts['scheme'] ?? '') === 'http',
        ]);
    }

    private function stripTrackers(string $url, array $parts): string
    {
        if (blank($parts['query'] ?? null)) {
            return $url;
        }

        parse_str($parts['query'], $params);
        $params = array_filter($params, function ($v, $k) {
            return !in_array(strtolower($k), self::TRACKERS, true) && !str_starts_with(strtolower($k), 'utm_');
        }, ARRAY_FILTER_USE_BOTH);

        $query = http_build_query($params);

        return ($parts['scheme'] . '://' . $parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '')
            . ($query !== '' ? '?' . $query : '')
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
}
