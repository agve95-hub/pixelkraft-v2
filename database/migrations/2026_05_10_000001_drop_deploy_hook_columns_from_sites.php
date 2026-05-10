<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * pre_deploy_hook and post_deploy_hook were intentionally excluded from
     * $fillable and never executed.  Keeping unused shell-command columns in the
     * schema is a latent risk — they could be set directly via DB tooling and
     * executed if the feature is ever wired up without satisfying the full security
     * contract.  Remove them now; they can be re-added when the feature is
     * properly implemented.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['pre_deploy_hook', 'post_deploy_hook']);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('pre_deploy_hook')->nullable()->after('deployment_mode');
            $table->text('post_deploy_hook')->nullable()->after('pre_deploy_hook');
        });
    }
};
