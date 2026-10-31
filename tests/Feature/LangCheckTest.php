<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    if (isset($this->backup)) {
        File::put(lang_path('en.json'), $this->backup);
    }
});

it('passes while the translations are in sync', function () {
    $this->artisan('lang:check')
        ->expectsOutputToContain('All translations are in sync.')
        ->assertExitCode(0);
});

it('fails when a key is missing from one language', function () {
    $path = lang_path('en.json');
    $this->backup = File::get($path);

    $keys = json_decode($this->backup, true);
    $keys['test.only.in.english'] = 'Only in English';
    File::put($path, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->artisan('lang:check')->assertExitCode(1);
});

it('fails when a translation is left empty', function () {
    $path = lang_path('en.json');
    $this->backup = File::get($path);

    $keys = json_decode($this->backup, true);
    $keys['common.save'] = '';
    File::put($path, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $this->artisan('lang:check')->assertExitCode(1);
});
