<?php

namespace App\Console\Commands;

use App\Models\TrainingModule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanMedia extends Command
{
    protected $signature = 'media:prune-orphans {--days=7 : Hapus file yatim lebih tua dari N hari}';

    protected $description = 'Hapus file di module-media/ yang tidak direferensi content_html mana pun';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $files = Storage::disk('private')->files('module-media');
        $orphanCount = 0;

        foreach ($files as $file) {
            $lastModified = Storage::disk('private')->lastModified($file);

            if ($lastModified > $cutoff->timestamp) {
                continue;
            }

            $filename = basename($file);
            $referenced = TrainingModule::where('content_html', 'LIKE', "%{$filename}%")->exists();

            if (! $referenced) {
                Storage::disk('private')->delete($file);
                $this->info("Deleted orphan: {$filename}");
                $orphanCount++;
            }
        }

        $this->info("Pruned {$orphanCount} orphan files older than {$days} days");

        return Command::SUCCESS;
    }
}
