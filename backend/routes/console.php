<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:sub2rebate', function (): void {
    $this->info('Sub2Rebate backend is ready.');
})->purpose('Show Sub2Rebate backend status');
