<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reclassify: maks 1 posttest per modul (quiz id terbesar = final).
        // Sisanya jadi 'practice' agar unique index bisa ditegakkan.
        $dupes = DB::select("SELECT training_module_id FROM quizzes WHERE purpose = 'posttest' GROUP BY training_module_id HAVING COUNT(*) > 1");
        foreach ($dupes as $d) {
            DB::update(
                "UPDATE quizzes SET purpose = 'practice'
                 WHERE purpose = 'posttest' AND training_module_id = ?
                 AND id <> (SELECT MAX(id) FROM quizzes q2 WHERE q2.training_module_id = quizzes.training_module_id AND q2.purpose = 'posttest')",
                [$d->training_module_id]
            );
        }

        DB::statement("CREATE UNIQUE INDEX quizzes_one_pretest_per_module ON quizzes (training_module_id) WHERE purpose = 'pretest'");
        DB::statement("CREATE UNIQUE INDEX quizzes_one_posttest_per_module ON quizzes (training_module_id) WHERE purpose = 'posttest'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS quizzes_one_pretest_per_module');
        DB::statement('DROP INDEX IF EXISTS quizzes_one_posttest_per_module');
    }
};
