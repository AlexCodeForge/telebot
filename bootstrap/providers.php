<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ViewServiceProvider::class, // Dynamic bot username from database
    App\Providers\VercelBlobServiceProvider::class,
];
