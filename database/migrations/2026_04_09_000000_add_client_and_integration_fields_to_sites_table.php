<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Client information
            $table->string('client_first_name')->nullable()->after('name');
            $table->string('client_last_name')->nullable()->after('client_first_name');
            $table->string('client_email')->nullable()->after('client_last_name');
            $table->string('client_phone')->nullable()->after('client_email');
            $table->string('client_company')->nullable()->after('client_phone');
            $table->string('client_address')->nullable()->after('client_company');
            $table->text('client_notes')->nullable()->after('client_address');

            // Billing
            $table->string('billing_cycle')->nullable()->after('project_type');
            $table->decimal('monthly_retainer', 10, 2)->nullable()->after('billing_cycle');

            // Domain & SSL
            $table->string('ssl_provider')->nullable()->after('domain');
            $table->string('dns_provider')->nullable()->after('ssl_provider');

            // Extended integrations
            $table->string('gtm_id')->nullable()->after('ga_property_id');
            $table->string('google_ads_id')->nullable()->after('gtm_id');
            $table->text('cf_api_token')->nullable()->after('cf_zone_id');

            // SMTP
            $table->string('smtp_host')->nullable()->after('cf_api_token');
            $table->integer('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_username')->nullable()->after('smtp_port');
            $table->text('smtp_password')->nullable()->after('smtp_username');

            // Hosting / server access
            $table->string('hosting_provider')->nullable()->after('smtp_password');
            $table->string('ssh_host')->nullable()->after('hosting_provider');
            $table->string('ftp_ssh_user')->nullable()->after('ssh_host');
            $table->text('ftp_ssh_password')->nullable()->after('ftp_ssh_user');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'client_first_name',
                'client_last_name',
                'client_email',
                'client_phone',
                'client_company',
                'client_address',
                'client_notes',
                'billing_cycle',
                'monthly_retainer',
                'ssl_provider',
                'dns_provider',
                'gtm_id',
                'google_ads_id',
                'cf_api_token',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'hosting_provider',
                'ssh_host',
                'ftp_ssh_user',
                'ftp_ssh_password',
            ]);
        });
    }
};
