<?php

namespace App\Console\Commands;

use App\Models\Email;
use Illuminate\Console\Command;

/**
 * Pagar terakhir anti mail-bomb: bila total penyimpanan email melewati kuota
 * (settings max_storage_mb), email tertua dihapus lebih dulu sampai muat lagi.
 * Email yang disimpan permanen (expires_at NULL) tidak pernah ikut terpangkas.
 */
class TrimStorageCommand extends Command
{
    protected $signature = 'tempmail:trim-storage';

    protected $description = 'Hapus email tertua bila kuota penyimpanan terlampaui';

    public function handle(): int
    {
        $capMb = (int) config('tempmail.max_storage_mb', 0);
        if ($capMb <= 0) {
            $this->info('Kuota penyimpanan tidak diset (0 = tanpa batas) — tidak ada yang dipangkas.');
            return self::SUCCESS;
        }

        $capBytes = $capMb * 1048576;
        // Email di keranjang sampah masih memakan disk — ikut dihitung & dipangkas duluan
        $total = (int) Email::withTrashed()->sum('size_bytes');

        if ($total <= $capBytes) {
            $this->info('Pemakaian ' . number_format($total / 1048576, 1) . " MB masih di bawah kuota {$capMb} MB.");
            return self::SUCCESS;
        }

        $dihapus = 0;
        while ($total > $capBytes) {
            // Yang di Sampah dibuang lebih dulu, lalu email tertua yang tidak
            // ditandai "simpan permanen"
            $batch = Email::withTrashed()
                ->whereNotNull('expires_at')
                ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('received_at')
                ->limit(100)
                ->get();

            if ($batch->isEmpty()) {
                $this->warn('Kuota masih terlampaui, tapi sisa email semuanya disimpan permanen — tidak dipangkas.');
                break;
            }

            foreach ($batch as $email) {
                $total -= (int) $email->size_bytes;
                $email->hapusFileTerkait();
                $email->forceDelete(); // benar-benar dibuang, bukan sekadar ke Sampah
                $dihapus++;

                if ($total <= $capBytes) {
                    break;
                }
            }
        }

        $this->info("{$dihapus} email tertua dihapus; pemakaian sekarang " . number_format(max($total, 0) / 1048576, 1) . ' MB.');

        return self::SUCCESS;
    }
}
