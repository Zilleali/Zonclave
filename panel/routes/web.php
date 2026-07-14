<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing'));
Route::get('/docs', fn () => view('docs.index'));
Route::get('/docs/installation-guide', fn () => view('docs.installation-guide'));
Route::get('/docs/commands-reference', fn () => view('docs.commands-reference'));
