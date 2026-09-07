<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map integer quality values to string equivalents
        $mapping = [
            '0' => 'poor',
            '20' => 'poor',  // Low quality juga poor
            '60' => 'acceptable',
            '100' => 'best',
        ];

        $scenes = DB::table('case_scenes')->get();

        foreach ($scenes as $scene) {
            $options = json_decode($scene->options, true);
            $changed = false;

            foreach ($options as $idx => $option) {
                $quality = (string) $option['quality'];
                if (isset($mapping[$quality])) {
                    $options[$idx]['quality'] = $mapping[$quality];
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('case_scenes')
                    ->where('id', $scene->id)
                    ->update(['options' => json_encode($options)]);
            }
        }
    }

    public function down(): void
    {
        // No reverse migration needed (strings are correct format)
    }
};