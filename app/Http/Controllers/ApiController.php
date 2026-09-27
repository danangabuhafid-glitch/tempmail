<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAlias;
use App\Services\OtpExtractor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TempMail Developer API Controller
 *
 * Menyediakan endpoint RESTful bagi developer/bot untuk:
 * 1. Auto-buat / generate temporary email address
 * 2. Menerima email masuk (list, detail, HTML, text, attachments)
 * 3. Mengambil kode OTP / verifikasi otomatis
 * 4. Wait-email & Wait-OTP (long-polling) untuk script testing / bot
 * 5. Manajemen & pembersihan alias / email
 */
class ApiController extends Controller
{
    public function __construct(
        private OtpExtractor $otpExtractor
    ) {
    }

    /**
     * Dokumentasi lengkap Developer API (dapat diakses public / tanpa token)
     */
    public function docs()
    {
        $baseUrl = url('/');
        $domains = config('tempmail.domains', []);

        return response()->json([
            'title' => 'TempMail Developer API',
            'version' => '2.0',
            'base_url' => $baseUrl,
            'available_domains' => $domains,
            'default_domain' => $domains[0] ?? 'danang.biz.id',
            'authentication' => [
                'type' => 'Bearer Token or Query Parameter',
                'methods' => [
                    'Header: Authorization' => 'Bearer <TOKEN>',
                    'Header: X-Api-Token' => '<TOKEN>',
                    'Query string' => '?token=<TOKEN> atau ?api_key=<TOKEN>',
                ],
                'note' => 'Token API dapat diperoleh dari Dashboard Web TempMail (Menu Pengaturan -> API).'
            ],
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/domains',
                    'desc' => 'Daftar domain aktif yang bisa digunakan untuk membuat email.',
                ],
                [
                    'method' => 'POST / GET',
                    'path' => '/api/create',
                    'desc' => 'Auto-generate / buat alamat temp mail baru.',
                    'params' => [
                        'name' => 'optional (string) - custom username (mis: user123). Jika kosong, digenerate acak.',
                        'prefix' => 'optional (string) - awalan email (mis: bot_, test_). Default: dev_',
                        'domain' => 'optional (string) - pilih domain dari /api/domains. Default: domain utama.',
                        'ttl' => 'optional (string) - masa aktif: 10m, 30m, 1h, 24h, 7d, 30d. Default: permanen.',
                        'label' => 'optional (string) - catatan/label tujuan email.',
                    ],
                    'example_response' => [
                        'success' => true,
                        'email' => 'dev_7xk29p@danang.biz.id',
                        'alias' => 'dev_7xk29p',
                        'domain' => 'danang.biz.id',
                        'expires_at' => null,
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/emails',
                    'desc' => 'Ambil daftar email masuk.',
                    'params' => [
                        'email' => 'optional (string) - filter by email lengkap (mis: dev_7xk29p@danang.biz.id)',
                        'alias' => 'optional (string) - filter by alias (mis: dev_7xk29p)',
                        'domain' => 'optional (string) - filter by domain',
                        'unread' => 'optional (boolean) - filter hanya email belum dibaca',
                        'q' => 'optional (string) - cari subjek atau pengirim',
                        'limit' => 'optional (integer) - jumlah email (default: 20, max: 100)',
                        'after_id' => 'optional (integer) - hanya ambil email dengan ID > after_id',
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/emails/{id}',
                    'desc' => 'Ambil isi email lengkap (text, html, attachment, headers). Menandai email sebagai dibaca.',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/otp',
                    'desc' => 'Ambil kode OTP terbaru secara instan.',
                    'params' => [
                        'email' => 'required/optional - email lengkap atau alias',
                        'alias' => 'required/optional - nama alias',
                        'domain' => 'optional - nama domain',
                        'minutes' => 'optional (integer) - rentang waktu pencarian (default: 15 menit)',
                    ],
                    'example_response' => [
                        'success' => true,
                        'otp' => '126848',
                        'from' => 'noreply@service.com',
                        'subject' => 'Kode OTP Verifikasi',
                        'email_id' => 280,
                        'received_at' => '2026-09-27T13:52:00+07:00'
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/wait-email',
                    'desc' => 'Long-polling: Menunggu email masuk secara real-time (otomatis loop di server).',
                    'params' => [
                        'email' => 'required - email tujuan',
                        'timeout' => 'optional (integer) - waktu tunggu maksimal dlm detik (default: 30, max: 60)',
                        'after_id' => 'optional (integer) - tunggu email baru dengan ID lebih besar dari ini',
                    ]
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/wait-otp',
                    'desc' => 'Long-polling: Menunggu kode OTP masuk secara real-time langsung mengembalikan OTP.',
                    'params' => [
                        'email' => 'required - email tujuan',
                        'timeout' => 'optional (integer) - waktu tunggu maksimal dlm detik (default: 30, max: 60)',
                        'after_id' => 'optional (integer) - tunggu email dengan ID lebih besar dari ini',
                    ]
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/api/emails/{id}',
                    'desc' => 'Hapus email setelah selesai digunakan.',
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/api/alias/{alias}',
                    'desc' => 'Hapus/tutup alias temp mail.',
                ],
            ],
            'curl_examples' => [
                'create_email' => "curl -X POST -H \"Authorization: Bearer <TOKEN>\" \"{$baseUrl}/api/create?prefix=bot_\"",
                'get_otp' => "curl -H \"Authorization: Bearer <TOKEN>\" \"{$baseUrl}/api/otp?email=bot_12345@danang.biz.id\"",
                'wait_otp' => "curl -H \"Authorization: Bearer <TOKEN>\" \"{$baseUrl}/api/wait-otp?email=bot_12345@danang.biz.id&timeout=30\"",
            ]
        ]);
    }

    /**
     * Daftar domain yang didukung sistem
     */
    public function domains()
    {
        $domains = config('tempmail.domains', []);

        return response()->json([
            'success' => true,
            'domains' => array_values($domains),
            'default_domain' => $domains[0] ?? null,
            'total' => count($domains),
        ]);
    }

    /**
     * Buat/Generate alamat email baru (Auto Buat)
     * Mendukung GET dan POST
     */
    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:64'],
            'alias' => ['nullable', 'string', 'max:64'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'domain' => ['nullable', 'string', 'max:253'],
            'ttl' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $customName = $data['name'] ?? $data['alias'] ?? null;
        $prefix = $data['prefix'] ?? null;
        $domain = $data['domain'] ?? null;
        $label = $data['label'] ?? 'Dev API';

        $expiresAt = null;
        if (filled($data['ttl'] ?? null)) {
            $ttl = strtolower(trim($data['ttl']));
            $expiresAt = match ($ttl) {
                '10m' => now()->addMinutes(10),
                '30m' => now()->addMinutes(30),
                '1h' => now()->addHour(),
                '24h', '1d' => now()->addDay(),
                '7d' => now()->addDays(7),
                '30d' => now()->addDays(30),
                default => is_numeric($ttl) ? now()->addMinutes((int) $ttl) : null,
            };
        }

        $alias = EmailAlias::buatBaru($customName, $prefix, $domain, $expiresAt, $label);
        if (!$alias) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat email alias. Periksa parameter domain atau coba lagi.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'email' => $alias->full_address,
            'alias' => $alias->alias,
            'domain' => $alias->domain,
            'label' => $alias->label,
            'expires_at' => $alias->expires_at?->toIso8601String(),
            'created_at' => $alias->created_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Ambil daftar email masuk
     */
    public function emails(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'unread' => ['nullable', 'boolean'],
            'spam' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'after_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Email::query()->orderByDesc('id');

        $query->where('is_spam', $request->boolean('spam'));

        // Filter by full email / to address
        $targetEmail = $data['email'] ?? $data['to'] ?? null;
        if (filled($targetEmail)) {
            $targetEmail = Str::lower(trim($targetEmail));
            if (str_contains($targetEmail, '@')) {
                [$aliasPart, $domainPart] = explode('@', $targetEmail, 2);
                $query->where('alias', $aliasPart)->where('domain', $domainPart);
            } else {
                $query->where('alias', $targetEmail);
            }
        } else {
            if (filled($data['alias'] ?? null)) {
                $query->where('alias', Str::lower(trim($data['alias'])));
            }
            if (filled($data['domain'] ?? null)) {
                $query->where('domain', Str::lower(trim($data['domain'])));
            }
        }

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        if (filled($data['after_id'] ?? null)) {
            $query->where('id', '>', (int) $data['after_id']);
        }

        if (filled($data['q'] ?? null)) {
            $q = $data['q'];
            $query->where(fn ($s) => $s->where('subject', 'like', "%{$q}%")->orWhere('from_address', 'like', "%{$q}%"));
        }

        $emails = $query->limit($data['limit'] ?? 20)->get()->map(function (Email $e) {
            $otp = $e->otp_code ?: $this->extractOtpFromEmail($e);

            return [
                'id' => $e->id,
                'to' => $e->to_address,
                'from' => $e->from_address,
                'from_name' => $e->from_name,
                'subject' => $e->subject,
                'otp' => $otp,
                'is_read' => (bool) $e->is_read,
                'received_at' => $e->received_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'total' => $emails->count(),
            'emails' => $emails,
        ]);
    }

    /**
     * Ambil isi email lengkap berdasarkan ID
     */
    public function show(Email $email)
    {
        // Tandai sudah dibaca
        if (!$email->is_read) {
            $email->update(['is_read' => true]);
        }

        $otp = $email->otp_code ?: $this->extractOtpFromEmail($email);

        return response()->json([
            'success' => true,
            'email' => [
                'id' => $email->id,
                'to' => $email->to_address,
                'alias' => $email->alias,
                'domain' => $email->domain,
                'from' => $email->from_address,
                'from_name' => $email->from_name,
                'subject' => $email->subject,
                'otp' => $otp,
                'text' => $email->text_body,
                'html' => $email->html_body,
                'spf' => $email->spf_result,
                'dkim' => $email->dkim_result,
                'dmarc' => $email->dmarc_result,
                'is_spam' => (bool) $email->is_spam,
                'is_read' => true,
                'received_at' => $email->received_at?->toIso8601String(),
                'attachments' => $email->attachments->map(fn ($a) => [
                    'id' => $a->id,
                    'filename' => $a->filename,
                    'mime' => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                    'is_flagged' => (bool) $a->is_flagged,
                ]),
            ]
        ]);
    }

    /**
     * Ambil kode OTP terbaru untuk suatu email/alias
     */
    public function otp(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $query = Email::query()->orderByDesc('id');

        $targetEmail = $data['email'] ?? $data['to'] ?? null;
        if (filled($targetEmail)) {
            $targetEmail = Str::lower(trim($targetEmail));
            if (str_contains($targetEmail, '@')) {
                [$aliasPart, $domainPart] = explode('@', $targetEmail, 2);
                $query->where('alias', $aliasPart)->where('domain', $domainPart);
            } else {
                $query->where('alias', $targetEmail);
            }
        } elseif (filled($data['alias'] ?? null)) {
            $query->where('alias', Str::lower(trim($data['alias'])));
            if (filled($data['domain'] ?? null)) {
                $query->where('domain', Str::lower(trim($data['domain'])));
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Parameter email atau alias wajib diisi (mis: /api/otp?email=user@danang.biz.id).',
            ], 422);
        }

        $minutes = $data['minutes'] ?? 15;
        $query->where('received_at', '>', now()->subMinutes($minutes));

        // Ambil beberapa email terbaru untuk mengecek OTP
        $candidates = $query->limit(10)->get();

        foreach ($candidates as $email) {
            $otp = $email->otp_code ?: $this->extractOtpFromEmail($email);
            if ($otp) {
                return response()->json([
                    'success' => true,
                    'otp' => $otp,
                    'to' => $email->to_address,
                    'from' => $email->from_address,
                    'from_name' => $email->from_name,
                    'subject' => $email->subject,
                    'email_id' => $email->id,
                    'received_at' => $email->received_at?->toIso8601String(),
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'otp' => null,
            'message' => 'Belum ada email atau kode OTP yang masuk dalam ' . $minutes . ' menit terakhir.',
        ], 404);
    }

    /**
     * Long-polling: Menunggu email masuk secara real-time
     */
    public function waitEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:60'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $targetEmail = $data['email'] ?? $data['to'] ?? null;
        $alias = $data['alias'] ?? null;
        $domain = $data['domain'] ?? null;

        if (filled($targetEmail)) {
            $targetEmail = Str::lower(trim($targetEmail));
            if (str_contains($targetEmail, '@')) {
                [$alias, $domain] = explode('@', $targetEmail, 2);
            } else {
                $alias = $targetEmail;
            }
        }

        if (blank($alias)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter email atau alias wajib disertakan.',
            ], 422);
        }

        $timeout = $data['timeout'] ?? 30;
        $afterId = $data['after_id'] ?? 0;
        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $query = Email::query()->orderByDesc('id');
            $query->where('alias', $alias);
            if (filled($domain)) {
                $query->where('domain', $domain);
            }
            if ($afterId > 0) {
                $query->where('id', '>', $afterId);
            }

            $email = $query->first();
            if ($email) {
                $otp = $email->otp_code ?: $this->extractOtpFromEmail($email);
                return response()->json([
                    'success' => true,
                    'email' => [
                        'id' => $email->id,
                        'to' => $email->to_address,
                        'from' => $email->from_address,
                        'from_name' => $email->from_name,
                        'subject' => $email->subject,
                        'otp' => $otp,
                        'text' => $email->text_body,
                        'html' => $email->html_body,
                        'received_at' => $email->received_at?->toIso8601String(),
                    ]
                ]);
            }

            sleep(1);
        }

        return response()->json([
            'success' => false,
            'message' => "Timeout ({$timeout} detik): Tidak ada email baru masuk.",
        ], 408);
    }

    /**
     * Long-polling: Menunggu kode OTP masuk secara real-time
     */
    public function waitOtp(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'alias' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'timeout' => ['nullable', 'integer', 'min:5', 'max:60'],
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $targetEmail = $data['email'] ?? $data['to'] ?? null;
        $alias = $data['alias'] ?? null;
        $domain = $data['domain'] ?? null;

        if (filled($targetEmail)) {
            $targetEmail = Str::lower(trim($targetEmail));
            if (str_contains($targetEmail, '@')) {
                [$alias, $domain] = explode('@', $targetEmail, 2);
            } else {
                $alias = $targetEmail;
            }
        }

        if (blank($alias)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter email atau alias wajib disertakan.',
            ], 422);
        }

        $timeout = $data['timeout'] ?? 30;
        $afterId = $data['after_id'] ?? 0;
        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $query = Email::query()->orderByDesc('id');
            $query->where('alias', $alias);
            if (filled($domain)) {
                $query->where('domain', $domain);
            }
            if ($afterId > 0) {
                $query->where('id', '>', $afterId);
            }

            $email = $query->first();
            if ($email) {
                $otp = $email->otp_code ?: $this->extractOtpFromEmail($email);
                if ($otp) {
                    return response()->json([
                        'success' => true,
                        'otp' => $otp,
                        'email_id' => $email->id,
                        'to' => $email->to_address,
                        'from' => $email->from_address,
                        'from_name' => $email->from_name,
                        'subject' => $email->subject,
                        'received_at' => $email->received_at?->toIso8601String(),
                    ]);
                }
            }

            sleep(1);
        }

        return response()->json([
            'success' => false,
            'otp' => null,
            'message' => "Timeout ({$timeout} detik): Belum ada kode OTP masuk.",
        ], 408);
    }

    /**
     * Buat alias bernama situs (bookmarklet compatibility)
     */
    public function quickAlias(Request $request)
    {
        $data = $request->validate([
            'site' => ['required', 'string', 'max:100'],
            'domain' => ['nullable', 'in:' . implode(',', config('tempmail.domains'))],
            'ttl' => ['nullable', 'in:1h,24h,7d'],
        ]);

        $expiresAt = match ($data['ttl'] ?? null) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            default => null,
        };

        $alias = EmailAlias::buatCepat($data['site'], $data['domain'] ?? null, $expiresAt);
        if (!$alias) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat alias — coba lagi.'], 500);
        }

        return response()->json([
            'success' => true,
            'address' => $alias->full_address,
            'email' => $alias->full_address,
            'alias' => $alias->alias,
            'label' => $alias->label,
            'expires_at' => $alias->expires_at?->toIso8601String(),
        ], 201);
    }

    /**
     * Hapus email
     */
    public function destroy(Email $email)
    {
        $email->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email berhasil dihapus.',
        ]);
    }

    /**
     * Hapus alias
     */
    public function destroyAlias(string $alias, Request $request)
    {
        $domain = $request->query('domain');
        $query = EmailAlias::where('alias', $alias);
        if (filled($domain)) {
            $query->where('domain', $domain);
        }
        $row = $query->first();

        if ($row) {
            $row->delete();
            return response()->json([
                'success' => true,
                'message' => "Alias {$alias} berhasil dihapus.",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Alias {$alias} tidak ditemukan.",
        ], 404);
    }

    /**
     * Ekstraksi OTP cadangan dari HTML jika belum ada di database
     */
    private function extractOtpFromEmail(Email $email): ?string
    {
        $extracted = $this->otpExtractor->extract($email->subject, $email->text_body, $email->html_body);
        if ($extracted && blank($email->otp_code)) {
            // Update ke DB agar query selanjutnya cepat
            $email->update(['otp_code' => $extracted]);
        }
        return $extracted;
    }
}
