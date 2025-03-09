<?php

declare(strict_types=1);

namespace AliesDev\LaravelSupabaseFlysystem;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

/** @api */
final class SupabaseStorageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('supabase', static function (Application $app, array $config): FilesystemAdapter {
            $adapter = new SupabaseAdapter($config);

            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config
            );
        });
    }
}
