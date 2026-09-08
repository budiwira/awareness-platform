<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetUserData extends Command
{
    protected $signature = 'app:reset-user-data
        {--dry-run : Tampilkan apa yang akan dihapus tanpa eksekusi}
        {--force : Lewati konfirmasi interaktif}
        {--with-tenants : Hapus juga tenants dan subscriptions}';

    protected $description = 'Hapus semua data user & aktivitas tenant untuk demo bersih. Konten platform (packages, modules, quizzes, cases, CTF, TTX, badges) dan super admin DIPERTAHANKAN.';

    private const ACTIVITY_TABLES = [
        'quiz_attempts',
        'module_assignments',
        'user_badges',
        'phishing_targets',
        'phishing_campaigns',
        'case_participations',
        'ctf_solves',
        'ttx_scores',
        'ttx_team_members',
        'ttx_teams',
        'notifications',
        'package_requests',
    ];

    public function handle(): int
    {
        $conn = $this->connection();
        $dry = (bool) $this->option('dry-run');
        $withTenants = (bool) $this->option('with-tenants');

        $this->warn('Perhatian: perintah ini menghapus data user & aktivitas tenant.');
        $this->line('Konten platform + super admin DIPERTAHANKAN.');

        if (! $dry) {
            if (! $this->option('force') && ! $this->confirm('Lanjutkan reset?', false)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        } else {
            $this->info('[DRY-RUN] Tidak ada data yang akan diubah.');
        }

        $tables = self::ACTIVITY_TABLES;

        foreach ($this->tablesReferencing($conn, 'users') as $extra) {
            if ($extra !== 'users' && ! in_array($extra, $tables, true)) {
                $tables[] = $extra;
            }
        }

        $failed = [];

        foreach ($tables as $table) {
            $exists = DB::connection($conn)->select('SELECT to_regclass(?) AS t', ['public.'.$table])[0]->t;
            if ($exists === null) {
                continue;
            }
            $count = (int) DB::connection($conn)->table($table)->count();

            $this->line(sprintf('  - %-24s %s', $table, ($dry ? 'akan dihapus: ' : 'dihapus: ').$count));

            if (! $dry && $count > 0) {
                try {
                    DB::connection($conn)->table($table)->delete();
                } catch (\Throwable) {
                    $failed[] = $table;
                }
            }
        }

        foreach ($failed as $table) {
            DB::connection($conn)->table($table)->delete();
        }

        $userCount = (int) DB::connection($conn)->table('users')->where('role', '!=', 'super_admin')->count();
        if (! $dry) {
            DB::connection($conn)->table('users')->where('role', '!=', 'super_admin')->delete();
        }
        $this->line(sprintf('  - %-24s %s', 'users (non super_admin)', ($dry ? 'akan dihapus: ' : 'dihapus: ').$userCount));

        if ($withTenants) {
            foreach (['subscriptions', 'tenants'] as $table) {
                $count = (int) DB::connection($conn)->table($table)->count();
                if (! $dry) {
                    DB::connection($conn)->table($table)->delete();
                }
                $this->line(sprintf('  - %-24s %s', $table, ($dry ? 'akan dihapus: ' : 'dihapus: ').$count));
            }
        }

        $this->info($dry ? '[DRY-RUN] Selesai. Tidak ada perubahan.' : 'Reset selesai. Bangun data demo Anda dari nol.');

        return self::SUCCESS;
    }

    private function connection(): string
    {
        if (app()->environment('testing')) {
            return config('database.default');
        }

        return array_key_exists('pgsql_owner', config('database.connections'))
            ? 'pgsql_owner'
            : config('database.default');
    }

    private function tablesReferencing(string $conn, string $table): array
    {
        try {
            $rows = DB::connection($conn)->select(
                'SELECT DISTINCT cl.relname AS table_name
                 FROM pg_constraint con
                 JOIN pg_class cl ON cl.oid = con.conrelid
                 JOIN pg_class fcl ON fcl.oid = con.confrelid
                 WHERE con.contype = ? AND fcl.relname = ?',
                ['f', $table]
            );

            return array_map(fn ($r) => $r->table_name, $rows);
        } catch (\Throwable) {
            return [];
        }
    }
}
