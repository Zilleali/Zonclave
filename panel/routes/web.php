<?php

use App\Models\Backup;
use App\Support\DocsMarkdownRenderer;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => view('landing'));
Route::get('/about', fn () => view('about'));
Route::get('/docs', fn () => view('docs.index'));

// These three render docs/*.md live (App\Support\DocsMarkdownRenderer) -
// the markdown file is the single source, this page is never hand-copied
// from it (client decision 2026-07-18, so the two can't drift apart).
// site-configuration and troubleshooting have no markdown source (real
// Sancover-specific content, hand-written directly as blade views) and
// are intentionally not part of this mechanism.
Route::get('/docs/installation-guide', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'Installation Guide',
    'description' => 'The complete start-to-finish manual for Zonclave: panel setup, production installation, and how to use it day to day.',
    'html' => $renderer->render('installation-guide'),
    'ctaHeading' => 'Questions about deploying Zonclave?',
    'ctaLabel' => 'Get in touch',
    'ctaUrl' => null,
]));

Route::get('/docs/commands-reference', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'Command Reference',
    'description' => 'Every command used to develop, test, and install Zonclave, grouped by environment.',
    'html' => $renderer->render('commands-reference'),
    'ctaHeading' => 'Need the full walkthrough?',
    'ctaLabel' => 'Read the installation guide',
    'ctaUrl' => '/docs/installation-guide',
]));

Route::get('/docs/opnsense-configuration', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'OPNsense Configuration Guide',
    'description' => 'How the OPNsense network side of a Zonclave deployment is configured: VLANs, WireGuard tunnels, fail-closed firewall policy, DNS, and the UniFi integration.',
    'html' => $renderer->render('opnsense-configuration'),
    'ctaHeading' => 'Deploying Zonclave on your own network?',
    'ctaLabel' => 'Get in touch',
    'ctaUrl' => null,
]));

Route::get('/docs/user-guide', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'User Guide',
    'description' => 'A screen-by-screen walkthrough of the Zonclave admin panel, for whoever runs it day to day.',
    'html' => $renderer->render('user-guide'),
    'ctaHeading' => 'Setting this up for the first time?',
    'ctaLabel' => 'Read the installation guide',
    'ctaUrl' => '/docs/installation-guide',
]));

Route::get('/docs/developer-guide', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'Developer Guide',
    'description' => 'Architecture, coding standards, and conventions for anyone picking up this codebase.',
    'html' => $renderer->render('developer-guide'),
    'ctaHeading' => 'Want the full command list?',
    'ctaLabel' => 'Read the command reference',
    'ctaUrl' => '/docs/commands-reference',
]));

Route::get('/docs/changelog', fn (DocsMarkdownRenderer $renderer) => view('docs.markdown-page', [
    'title' => 'Changelog',
    'description' => 'What changed, and when - newest first.',
    'html' => $renderer->render('changelog'),
    'ctaHeading' => 'Questions about a specific release?',
    'ctaLabel' => 'Get in touch',
    'ctaUrl' => null,
]));

Route::get('/docs/site-configuration', fn () => view('docs.site-configuration'));
Route::get('/docs/troubleshooting', fn () => view('docs.troubleshooting'));

// Everything above is public and unauthenticated by design (Section 25 of
// the developer guide). This one route is the exception - a real browser
// download, which a Livewire action inside the admin panel can't trigger
// directly (BackupsTable's Download action links here). Uses Filament's own
// Authenticate middleware (not the generic 'auth' alias) so an
// unauthenticated request redirects to the panel's actual login page rather
// than erroring on Laravel's default `route('login')` lookup, which this
// app never defines - Filament's login route has its own name
// (CLAUDE.md Section 16.8).
Route::middleware(Authenticate::class)->get('/admin/backups/{backup}/download', function (Backup $backup) {
    return Storage::disk('local')->download($backup->disk_path, $backup->filename);
})->name('backups.download');
