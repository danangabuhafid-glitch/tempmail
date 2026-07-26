<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Email extends Model
{
    use Prunable, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'is_spam' => 'boolean',
        'received_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Dihapus permanen oleh scheduler (model:prune) bila:
     * - sudah melewati masa retensi, ATAU
     * - sudah lebih dari 7 hari berada di keranjang sampah.
     */
    public function prunable(): Builder
    {
        return static::withTrashed()
            ->where(function ($q) {
                $q->where(fn ($s) => $s->whereNotNull('expires_at')->where('expires_at', '<', now()))
                    ->orWhere(fn ($s) => $s->whereNotNull('deleted_at')->where('deleted_at', '<', now()->subDays(7)));
            });
    }

    // File lampiran & raw .eml di disk ikut dibersihkan sebelum record dihapus
    protected function pruning(): void
    {
        $this->hapusFileTerkait();
    }

    public function hapusFileTerkait(): void
    {
        Storage::disk('local')->deleteDirectory('attachments/' . $this->id);
        if ($this->raw_path) {
            Storage::disk('local')->delete($this->raw_path);
        }
    }
}
