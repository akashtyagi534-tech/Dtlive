<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        if (config('app.env') === 'production' || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Set dynamic AWS S3 / Wasabi config from DB/cache (guarded for build-time artisan commands)
        $storage = null;
        try {
            $storage = Cache::rememberForever('s3_storage_settings', function () {
                return Storage_Setting();
            });
        } catch (\Throwable $e) {
            $storage = null;
        }

        if (is_array($storage) && !empty($storage)) {
            if (!empty($storage['s3_access_key'])) {
                Config::set('filesystems.disks.s3', [
                    'driver' => 's3',
                    'key'    => $storage['s3_access_key'],
                    'secret' => $storage['s3_secret_key'] ?? '',
                    'region' => $storage['s3_region'] ?? 'us-east-1',
                    'bucket' => $storage['s3_bucket_name'] ?? '',
                    'use_path_style_endpoint' => false,
                ]);
            }

            if (!empty($storage['wasabi_access_key'])) {
                Config::set('filesystems.disks.wasabi', [
                    'driver' => 's3',
                    'key' => $storage['wasabi_access_key'],
                    'secret' => $storage['wasabi_secret_key'] ?? '',
                    'region' => $storage['wasabi_region'] ?? 'us-east-1',
                    'bucket' => $storage['wasabi_bucket_name'] ?? '',
                    'endpoint' => $storage['wasabi_endpoint'] ?? '',
                ]);
            }
        }
    }
}
