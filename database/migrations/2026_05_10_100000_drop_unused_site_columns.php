<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop columns that are stored in the sites table but never read by any
     * service, job, or report.  Keeping them creates false expectations in the
     * UI and misleads operators into thinking integrations are active.
     *
     * google_ads_id  — Google Ads API integration not built; stored but unused.
     * gsc_property   — Google Search Console integration not built; stored but unused.
     * ftp_ssh_user   — SSH/FTP remote deployment not built; stored but unused.
     * ftp_ssh_password — Same; also removed from SECRET_FILLABLE cast.
     */
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['google_ads_id', 'gsc_property', 'ftp_ssh_user', 'ftp_ssh_password']);
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('google_ads_id')->nullable()->after('gtm_id');
            $table->string('gsc_property')->nullable()->after('google_ads_id');
            $table->string('ftp_ssh_user')->nullable()->after('ssh_host');
            $table->text('ftp_ssh_password')->nullable()->after('ftp_ssh_user');
        });
    }
};
