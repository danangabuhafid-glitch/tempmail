<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Attachment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_flagged' => 'boolean',
    ];

    public function email()
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * URL bertanda tangan (2 jam) untuk menampilkan gambar inline.
     * Signed URL dipakai karena iframe srcdoc tidak selalu mengirim cookie sesi;
     * tanda tangan hanya dibuat di dalam request yang sudah lolos otorisasi.
     */
    public function inlineUrl(): string
    {
        return URL::temporarySignedRoute('inbox.inline', now()->addHours(2), $this);
    }
}
