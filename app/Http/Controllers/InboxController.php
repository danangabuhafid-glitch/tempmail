<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Email;
use App\Models\EmailAlias;
use App\Services\EmailHtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        // Akun alias dipaksa hanya melihat kotak masuknya sendiri
        $isAliasUser = !$request->user()->isOwner();
        if ($isAliasUser) {
            [$aliasSaya, $domainSaya] = $request->user()->kotakSaya();
            $request->merge(['alias' => $aliasSaya, 'domain' => $domainSaya]);
        }

        $query = Email::query()->withCount('attachments')->orderByDesc('received_at');

        // Folder: Inbox (default) / Spam / Sampah
        $lihatSampah = $request->boolean('trash');
        $lihatSpam = !$lihatSampah && $request->boolean('spam');

        if ($lihatSampah) {
            $query->onlyTrashed()->reorder()->orderByDesc('deleted_at');
        } else {
            $query->where('is_spam', $lihatSpam);
        }

        if ($request->filled('domain')) {
            $query->where('domain', $request->domain);
        }
        if ($request->filled('alias')) {
            $query->where('alias', $request->alias);
        }
        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                // MySQL: FULLTEXT atas subjek+isi (pakai index); lainnya: LIKE
                if (DB::connection()->getDriverName() === 'mysql') {
                    $sub->whereFullText(['subject', 'text_body'], $q);
                } else {
                    $sub->where('subject', 'like', "%{$q}%")
                        ->orWhere('text_body', 'like', "%{$q}%");
                }
                $sub->orWhere('from_address', 'like', "%{$q}%")
                    ->orWhere('from_name', 'like', "%{$q}%")
                    ->orWhere('alias', 'like', "%{$q}%");
            });
        }

        $emails = $query->paginate(20)->withQueryString();

        $unreadQuery = Email::where('is_read', false)->where('is_spam', false);
        $spamQuery = Email::where('is_spam', true);
        $trashQuery = Email::onlyTrashed();
        if ($isAliasUser) {
            $unreadQuery->where('alias', $aliasSaya)->where('domain', $domainSaya);
            $spamQuery->where('alias', $aliasSaya)->where('domain', $domainSaya);
            $trashQuery->where('alias', $aliasSaya)->where('domain', $domainSaya);
        }
        $unreadCount = $unreadQuery->count();
        $spamCount = $spamQuery->count();
        $trashCount = $trashQuery->count();

        $domains = config('tempmail.domains');

        // Akun alias tidak butuh chip/dropdown alamat — hanya satu kotak masuk
        if ($isAliasUser) {
            $latestId = (int) Email::where('alias', $aliasSaya)->where('domain', $domainSaya)->max('id');
            $aliases = collect();
            $mailboxes = collect();
            $hiddenBoxes = 0;

            if ($request->ajax()) {
                return view('inbox._list', compact('emails', 'latestId', 'mailboxes', 'unreadCount', 'spamCount', 'trashCount', 'hiddenBoxes', 'isAliasUser'))->render();
            }

            return view('inbox.index', compact('emails', 'unreadCount', 'spamCount', 'trashCount', 'domains', 'aliases', 'latestId', 'mailboxes', 'hiddenBoxes', 'isAliasUser'));
        }

        // Alias yang paling baru menerima email (untuk dropdown filter)
        $aliases = Email::select('alias', 'domain')
            ->selectRaw('MAX(received_at) as last_received')
            ->groupBy('alias', 'domain')
            ->orderByDesc('last_received')
            ->limit(30)
            ->get();

        // Chip pemilih kotak masuk: alamat tersimpan (pinned dulu) + alamat lain
        // yang pernah menerima email, lengkap dengan jumlah belum-dibaca per alamat
        $unreadPerBox = Email::where('is_read', false)->where('is_spam', false)
            ->selectRaw('alias, domain, COUNT(*) as total')
            ->groupBy('alias', 'domain')
            ->get()
            ->keyBy(function ($r) { return $r->alias . '@' . $r->domain; });

        // Batas chip agar tetap rapi saat data banyak: alamat tersimpan selalu
        // tampil (dikurasi user), penerima lain hanya 8 terbaru + hitungan sisanya
        $maxRecentChips = 8;

        $mailboxes = collect();
        foreach (EmailAlias::orderByDesc('is_pinned')->orderBy('alias')->get() as $saved) {
            $mailboxes->put($saved->full_address, [
                'alias' => $saved->alias, 'domain' => $saved->domain,
                'label' => $saved->label, 'pinned' => $saved->is_pinned,
            ]);
        }
        $recentShown = 0;
        foreach ($aliases as $r) {
            $key = $r->alias . '@' . $r->domain;
            if ($mailboxes->has($key) || $recentShown >= $maxRecentChips) continue;
            $mailboxes->put($key, ['alias' => $r->alias, 'domain' => $r->domain, 'label' => null, 'pinned' => false]);
            $recentShown++;
        }
        $mailboxes = $mailboxes->map(function ($m, $key) use ($unreadPerBox) {
            $m['unread'] = (int) optional($unreadPerBox->get($key))->total;
            return $m;
        });

        // Batas keras total chip (alamat tersimpan bisa dibuat massal s/d 50 sekaligus)
        $mailboxes = $mailboxes->take(14);

        // Jumlah alamat penerima yang tidak kebagian chip (dicari lewat dropdown filter)
        $totalBoxes = Email::distinct()->count('to_address');
        $hiddenBoxes = max(0, $totalBoxes - $mailboxes->count());

        // Penanda email terbaru — dipakai polling auto-refresh di browser
        $latestId = (int) Email::max('id');

        $isAliasUser = false;

        // Request AJAX cukup dijawab partial daftar (untuk refresh tanpa reload)
        if ($request->ajax()) {
            return view('inbox._list', compact('emails', 'latestId', 'mailboxes', 'unreadCount', 'spamCount', 'trashCount', 'hiddenBoxes', 'isAliasUser'))->render();
        }

        return view('inbox.index', compact('emails', 'unreadCount', 'spamCount', 'trashCount', 'domains', 'aliases', 'latestId', 'mailboxes', 'hiddenBoxes', 'isAliasUser'));
    }

    // Endpoint ringan untuk polling: browser cek apakah ada email lebih baru.
    // Ringkasan email terakhir ikut dikirim untuk notifikasi browser.
    public function poll(Request $request)
    {
        // Spam tidak memicu notifikasi/reload — masuk diam-diam ke folder Spam
        $query = Email::where('is_spam', false);
        if (!$request->user()->isOwner()) {
            [$alias, $domain] = $request->user()->kotakSaya();
            $query->where('alias', $alias)->where('domain', $domain);
        }

        $latest = (clone $query)->orderByDesc('id')->first(['id', 'from_name', 'from_address', 'subject', 'otp_code']);

        return response()->json([
            'latest_id' => (int) optional($latest)->id,
            'unread' => (clone $query)->where('is_read', false)->count(),
            'latest' => $latest ? [
                'id' => $latest->id,
                'from' => $latest->from_name ?: $latest->from_address,
                'subject' => $latest->subject,
                'otp' => $latest->otp_code,
            ] : null,
        ]);
    }

    public function show(Request $request, Email $email, EmailHtmlSanitizer $sanitizer)
    {
        $this->pastikanBolehLihat($request, $email);

        if (!$email->is_read) {
            $email->update(['is_read' => true]);
        }

        $email->load('attachments');

        $showImages = $request->boolean('images');
        $htmlAman = $sanitizer->sanitize($email->html_body, $showImages, $email);

        return view('inbox.show', [
            'email' => $email,
            'htmlAman' => $htmlAman,
            'showImages' => $showImages,
        ]);
    }

    // Hapus satu email → masuk keranjang sampah (bisa dipulihkan 7 hari).
    // Akun alias juga boleh, tapi hanya untuk email kotaknya sendiri.
    public function destroy(Request $request, Email $email)
    {
        $this->pastikanBolehLihat($request, $email);

        $email->delete(); // soft delete — file baru dihapus saat dibuang permanen

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Email dipindahkan ke Sampah.', 'redirect' => route('inbox.index')]);
        }

        return redirect()->route('inbox.index')->with('success', 'Email dipindahkan ke Sampah.');
    }

    // Pulihkan email dari keranjang sampah
    public function restore(Request $request, int $id)
    {
        $email = Email::onlyTrashed()->findOrFail($id);
        $this->pastikanBolehLihat($request, $email);

        $email->restore();

        return response()->json(['success' => true, 'message' => 'Email dipulihkan.']);
    }

    // Kosongkan keranjang sampah (hapus permanen beserta file-nya)
    public function emptyTrash(Request $request)
    {
        $query = Email::onlyTrashed();
        if (!$request->user()->isOwner()) {
            [$alias, $domain] = $request->user()->kotakSaya();
            $query->where('alias', $alias)->where('domain', $domain);
        }

        $jumlah = 0;
        foreach ($query->get() as $email) {
            $email->hapusFileTerkait();
            $email->forceDelete();
            $jumlah++;
        }

        return response()->json(['success' => true, 'message' => "{$jumlah} email dihapus permanen."]);
    }

    // Catatan pribadi per email ("akun trial, expire 3 Agustus")
    public function saveNote(Request $request, Email $email)
    {
        $this->pastikanBolehLihat($request, $email);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $email->update(['note' => $data['note'] ?: null]);

        return response()->json(['success' => true, 'message' => 'Catatan disimpan.']);
    }

    // Aksi massal dari daftar inbox: hapus / tandai dibaca / tandai belum dibaca
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => ['required', 'in:delete,read,unread,restore,purge'],
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        $query = Email::withTrashed()->whereIn('id', $data['ids']);
        if (!$request->user()->isOwner()) {
            [$alias, $domain] = $request->user()->kotakSaya();
            $query->where('alias', $alias)->where('domain', $domain);
        }

        $emails = $query->get();

        if ($data['action'] === 'delete') {
            foreach ($emails as $email) {
                $email->delete(); // ke Sampah dulu, file belum dihapus
            }
            $pesan = count($emails) . ' email dipindahkan ke Sampah.';
        } elseif ($data['action'] === 'restore') {
            foreach ($emails as $email) {
                $email->restore();
            }
            $pesan = count($emails) . ' email dipulihkan.';
        } elseif ($data['action'] === 'purge') {
            foreach ($emails as $email) {
                $email->hapusFileTerkait();
                $email->forceDelete();
            }
            $pesan = count($emails) . ' email dihapus permanen.';
        } else {
            Email::whereIn('id', $emails->pluck('id'))->update(['is_read' => $data['action'] === 'read']);
            $pesan = count($emails) . ' email ditandai ' . ($data['action'] === 'read' ? 'sudah dibaca.' : 'belum dibaca.');
        }

        return response()->json(['success' => true, 'message' => $pesan]);
    }

    // Kembalikan status "belum dibaca" (untuk ditindaklanjuti nanti)
    public function markUnread(Request $request, Email $email)
    {
        $this->pastikanBolehLihat($request, $email);
        $email->update(['is_read' => false]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Ditandai belum dibaca.', 'redirect' => route('inbox.index')]);
        }

        return redirect()->route('inbox.index');
    }

    // Toggle simpan permanen: expires_at NULL = kebal auto-hapus retensi
    public function toggleKeep(Request $request, Email $email)
    {
        $this->pastikanBolehLihat($request, $email);

        if ($email->expires_at === null) {
            $email->update(['expires_at' => $email->received_at->copy()->addDays(config('tempmail.retention_days'))]);
            $pesan = 'Email kembali mengikuti masa simpan otomatis.';
        } else {
            $email->update(['expires_at' => null]);
            $pesan = 'Email disimpan permanen — tidak akan dihapus otomatis.';
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan, 'reload' => true]);
        }

        return back()->with('success', $pesan);
    }

    // Unduh sumber asli email sebagai file .eml (bisa dibuka di Thunderbird dll.)
    public function downloadEml(Request $request, Email $email)
    {
        $this->pastikanBolehLihat($request, $email);

        $disk = Storage::disk('local');
        abort_unless($email->raw_path && $disk->exists($email->raw_path), 404, 'Sumber asli email ini tidak tersimpan (diterima sebelum fitur .eml aktif).');

        $nama = ($email->subject ? preg_replace('/[^\w.\- ]+/u', '_', mb_substr($email->subject, 0, 60)) : 'email') . '.eml';

        return $disk->download($email->raw_path, $nama, ['Content-Type' => 'message/rfc822']);
    }

    public function attachment(Request $request, Attachment $attachment)
    {
        $this->pastikanBolehLihat($request, $attachment->email);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        // Selalu attachment (bukan inline) — jangan pernah render file kiriman orang di browser
        return $disk->download($attachment->path, $attachment->filename);
    }

    // Format raster yang aman dirender inline. SVG sengaja TIDAK ada di sini:
    // SVG bisa berisi <script> dan akan berjalan di origin aplikasi (stored XSS).
    private const MIME_INLINE_AMAN = [
        'image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif',
    ];

    // Khusus gambar inline (cid:) — dirender di dalam iframe sandbox tampilan email.
    // Otorisasi = signed URL (dibuat hanya saat pemiliknya membuka email itu),
    // bukan sesi: iframe srcdoc tidak selalu mengirim cookie.
    // Hanya raster aman yang boleh inline; selain itu tetap dipaksa download.
    public function inlineAttachment(Request $request, Attachment $attachment)
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->path), 404);

        $mime = strtolower(trim(explode(';', (string) $attachment->mime_type)[0]));
        if (!in_array($mime, self::MIME_INLINE_AMAN, true)) {
            // Tipe dinetralkan: SVG dkk. tidak boleh dirender browser dalam bentuk apa pun
            return $disk->download($attachment->path, $attachment->filename, [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response($disk->get($attachment->path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            // Lapisan terakhir: walau file salah tipe, tidak ada yang bisa dieksekusi
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    // Akun alias hanya boleh membuka email milik kotak masuknya sendiri
    private function pastikanBolehLihat(Request $request, Email $email): void
    {
        if ($request->user()->isOwner()) {
            return;
        }

        [$alias, $domain] = $request->user()->kotakSaya();
        abort_unless($email->alias === $alias && $email->domain === $domain, 403);
    }
}
