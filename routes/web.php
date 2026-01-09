<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/{any}', function () {
    $devServer = env('VITE_DEV_SERVER_URL');

    if ($devServer) {
        $devServer = rtrim($devServer, '/');

        $html = <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Church Calendar</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="{$devServer}/@vite/client"></script>
    <script type="module" src="{$devServer}/src/main.js"></script>
  </body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    $path = public_path('spa/index.html');

    if (!File::exists($path)) {
        abort(404, 'SPA build not found. Run `npm run build` in the frontend folder.');
    }

    return File::get($path);
})->where('any', '.*');
