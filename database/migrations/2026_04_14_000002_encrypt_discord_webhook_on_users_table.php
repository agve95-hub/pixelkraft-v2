<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the column so it can hold Laravel's encrypted payload (>255 chars).
        Schema::table('users', function (Blueprint $table) {
            $table->text('discord_webhook')->nullable()->change();
        });

        // Re-encrypt any existing plaintext values.  We work at the DB level
        // (not through the Eloquent model) so the new 'encrypted' cast doesn't
        // interfere with the migration itself.
        DB::table('users')
            ->whereNotNull('discord_webhook')
            ->orderBy('id')
            ->each(function (object $row) {
                $raw = $row->discord_webhook;

                // Skip rows that were already encrypted (e.g. re-running up()).
                try {
                    Crypt::decryptString($raw);

                    return; // already encrypted
                } catch (\Exception) {
                    // Plaintext — encrypt it below.
                }

                DB::table('users')
                    ->where('id', $row->id)
                    ->update(['discord_webhook' => Crypt::encryptString($raw)]);
            });
    }

    public function down(): void
    {
        // Decrypt back to plaintext and narrow column to string.
        DB::table('users')
            ->whereNotNull('discord_webhook')
            ->orderBy('id')
            ->each(function (object $row) {
                try {
                    $plain = Crypt::decryptString($row->discord_webhook);
                    DB::table('users')
                        ->where('id', $row->id)
                        ->update(['discord_webhook' => $plain]);
                } catch (\Exception) {
                    // If decryption fails the value was already plain; leave it.
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('discord_webhook')->nullable()->change();
        });
    }
};
