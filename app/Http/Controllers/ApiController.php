<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAlias;
use Illuminate\Http\Request;

/**
 * API baca untuk skrip/bot (autentikasi: Bearer token dari halaman Pengaturan).
 * Read-only + pembuatan alias cepat — tidak ada endpoint hapus/ubah.
 *
 * Contoh:
 *   curl -H "Authorization: Bearer TOKEN" "https://host/api/emails?alias=belanja"
 *   curl -H "Authorization: Bearer TOKEN" "https://host/api/otp?alias=belanja"
 *   curl -H "Authorization: Bearer TOKEN" "https://host/api/alias/quick?site=netflix.com"
 */
class ApiController extends Controller
{
    public function emails(Request $request)
    {
        $data = $request->validate([
            'alias' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'unread' => ['nullable', 'boolean'],
            'spam' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Email::query()->orderByDesc('received_at');
        $query->where('is_spam', $request->boolean('spam'));
        if (filled($data['alias'] ?? null)) $query->where('alias', $data['alias']);
        if (filled($data['domain'] ?? null)) $query->where('domain', $data['domain']);
        if ($request->boolean('unread')) $query->where('is_read', false);
        if (filled($data['q'] ?? null)) {
            $q = $data['q'];
            $query->where(fn ($s) => $s->where('subject', 'like', "%{$q}%")->orWhere('from_address', 'like', "%{$q}%"));
        }

        return response()->json([
            'emails' => $query->limit($data['limit'] ?? 20)->get()->map(fn (Email $e) => [
                'id' => $e->id,
                'to' => $e->to_address,
                'from' => $e->from_address,
                'from_name' => $e->from_name,
                'subject' => $e->subject,
                'otp' => $e->otp_code,
                'is_read' => $e->is_read,
                'received_at' => $e->received_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(Email $email)
    {
        return response()->json([
            'id' => $email->id,
            'to' => $email->to_address,
            'from' => $email->from_address,
            'from_name' => $email->from_name,
            'subject' => $email->subject,
            'otp' => $email->otp_code,
            'text' => $email->text_body,
            'spf' => $email->spf_result,
            'dkim' => $email->dkim_result,
            'dmarc' => $email->dmarc_result,
            'is_spam' => $email->is_spam,
            'received_at' => $email->received_at?->toIso8601String(),
            'attachments' => $email->attachments->map(fn ($a) => [
                'id' => $a->id, 'filename' => $a->filename, 'mime' => $a->mime_type,
                'size_bytes' => $a->size_bytes, 'is_flagged' => (bool) $a->is_flagged,
            ]),
        ]);
    }

    // Kode OTP terbaru untuk sebuah alias — dipakai skrip yang menunggu verifikasi
    public function otp(Request $request)
    {
        $data = $request->validate([
            'alias' => ['required', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:253'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $email = Email::where('alias', $data['alias'])
            ->when(filled($data['domain'] ?? null), fn ($q) => $q->where('domain', $data['domain']))
            ->whereNotNull('otp_code')
            ->where('received_at', '>', now()->subMinutes($data['minutes'] ?? 15))
            ->orderByDesc('received_at')
            ->first();

        if (!$email) {
            return response()->json(['otp' => null, 'message' => 'Belum ada kode masuk — coba lagi.'], 404);
        }

        return response()->json([
            'otp' => $email->otp_code,
            'from' => $email->from_address,
            'subject' => $email->subject,
            'email_id' => $email->id,
            'received_at' => $email->received_at?->toIso8601String(),
        ]);
    }

    // Buat alias bernama situs (netflix-x4k9@...) — untuk bookmarklet/skrip signup
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
            return response()->json(['message' => 'Gagal membuat alias — coba lagi.'], 500);
        }

        return response()->json([
            'address' => $alias->full_address,
            'label' => $alias->label,
            'expires_at' => $alias->expires_at?->toIso8601String(),
        ], 201);
    }
}
