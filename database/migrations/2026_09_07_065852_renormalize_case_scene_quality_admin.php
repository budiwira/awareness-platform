<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function ddl()
    {
        $hasAdmin = (bool) config('database.connections.pgsql_admin.password');

        return DB::connection($hasAdmin ? 'pgsql_admin' : config('database.default'));
    }

    public function up(): void
    {
        $map = ['100' => 'best', '60' => 'acceptable', '20' => 'poor', '0' => 'poor'];

        $rows = $this->ddl()->table('case_scenes')->get();

        foreach ($rows as $row) {
            $options = json_decode($row->options, true);
            $changed = false;

            foreach ($options as $i => $opt) {
                $q = (string) ($opt['quality'] ?? '');
                if (isset($map[$q])) {
                    $options[$i]['quality'] = $map[$q];
                    $changed = true;
                }
            }

            if ($changed) {
                $this->ddl()->table('case_scenes')
                    ->where('id', $row->id)
                    ->update(['options' => json_encode($options)]);
            }
        }
    }

    public function down(): void
    {
        // Data normalization is not reversible by design.
    }
};
