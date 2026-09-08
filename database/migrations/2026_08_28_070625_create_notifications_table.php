<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });

        DB::statement('ALTER TABLE notifications ENABLE ROW LEVEL SECURITY');

        DB::statement('DROP POLICY IF EXISTS notifications_select_policy ON notifications');
        DB::statement("CREATE POLICY notifications_select_policy ON notifications FOR SELECT USING (notifiable_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        DB::statement('DROP POLICY IF EXISTS notifications_update_policy ON notifications');
        DB::statement("CREATE POLICY notifications_update_policy ON notifications FOR UPDATE USING (notifiable_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin') WITH CHECK (notifiable_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        DB::statement('DROP POLICY IF EXISTS notifications_delete_policy ON notifications');
        DB::statement("CREATE POLICY notifications_delete_policy ON notifications FOR DELETE USING (notifiable_id = NULLIF(current_setting('app.user_id', true), '')::bigint OR NULLIF(current_setting('app.role', true), '') = 'super_admin')");

        DB::statement('DROP POLICY IF EXISTS notifications_insert_policy ON notifications');
        DB::statement('CREATE POLICY notifications_insert_policy ON notifications FOR INSERT WITH CHECK (true)');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
