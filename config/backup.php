<?php

return [

    'backup' => [

        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'pixelkraft'),

        'source' => [

            'files' => [

                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    base_path(),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by version control systems such as Git and subversion are excluded
                 * by default.
                 */
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('app/backups'),
                    storage_path('logs'),
                    storage_path('framework/cache'),
                    storage_path('framework/sessions'),
                    storage_path('framework/testing'),
                    storage_path('framework/views'),
                    storage_path('repos'), // Site git repos — large, not needed in DB backup
                ],

                /*
                 * Determines if symlinks should be followed.
                 */
                'followLinks' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignoreUnreadableDirs' => true,

                /*
                 * This path is used to make directories in resulting zip-file relative.
                 */
                'relativePathForExcludedDirs' => base_path(),
            ],

            /*
             * The names of the connections to the databases that should be backed up.
             * Laravel supports multiple database connections; name them here.
             */
            'databases' => [
                config('database.default', 'mariadb'),
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         * Out of the box options are: Gzip, Bzip2, and Zstd compressors.
         */
        'database_dump_compressor' => \Spatie\DbDumper\Compressors\GzipCompressor::class,

        /*
         * If specified, the database dumped file name will contain a timestamp prefixed
         * by the provided string, e.g. "dump-2023-01-01T00:00:00.sql.gz".
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The file extension used for the database dump files.
         * Set to null to use the default for the configured database dump compressor.
         */
        'database_dump_file_extension' => '',

        'destination' => [

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => 'backup-',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                config('pixelkraft.backup.disk', 'r2'),
            ],

        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-tmp'),

        /*
         * The password to be used for archive encryption.
         * Leave empty if you don't want to encrypt the archive.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can choose from the 256-bit AES encryption provided by
         * ZipArchive (requires PHP >= 7.2) or the OpenSSL encryption.
         */
        'encryption' => 'default',

        /*
         * The number of attempts, in case the backup command encounters
         * an exception, before giving up.
         */
        'tries' => 1,

        /*
         * The number of seconds the backup command should wait between
         * each failed attempt before trying again.
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use
     * 'mail' and 'slack'. See the Spatie\Backup\Notifications\Notifiable class
     * for details about how to configure those channels.
     *
     * For other notification channels, use the `custom_senders` option.
     */
    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFound::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailed::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessful::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFound::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessful::class => [],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'admin@example.com')),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Pixelkraft Backups'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'pixelkraft'),
            'disks' => [config('pixelkraft.backup.disk', 'r2')],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 2,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [

        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will be
         * kept and so on.
         *
         * No matter how you configure it, the default strategy will never
         * delete the newest backup.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [

            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * After the "keep_all_backups_for_days" period is over, these
             * daily backups will be kept.
             */
            'keep_daily_backups_for_days' => 16,

            /*
             * After the "keep_daily_backups_for_days" period is over, these
             * weekly backups will be kept.
             */
            'keep_weekly_backups_for_weeks' => 8,

            /*
             * After the "keep_weekly_backups_for_weeks" period is over, these
             * monthly backups will be kept.
             */
            'keep_monthly_backups_for_months' => 4,

            /*
             * After the "keep_monthly_backups_for_months" period is over, these
             * yearly backups will be kept.
             */
            'keep_yearly_backups_for_years' => 2,

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 20000,
        ],
    ],

];
