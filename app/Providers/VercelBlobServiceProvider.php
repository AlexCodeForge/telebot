<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemManager;
use App\Services\VercelBlobFilesystemAdapter;
use League\Flysystem\Filesystem;
use VercelBlobPhp\Client;

class VercelBlobServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->resolving('filesystem', function (FilesystemManager $filesystem) {
            $filesystem->extend('vercel-blob', function ($app, $config) {
                $client = new Client($config['token'] ?? null);
                $adapter = new VercelBlobFilesystemAdapter($client);

                return new Filesystem($adapter, $config);
            });
        });
    }
}
