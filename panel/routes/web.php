<?php

use App\Support\DocsMarkdownRenderer;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing'));
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

Route::get('/docs/site-configuration', fn () => view('docs.site-configuration'));
Route::get('/docs/troubleshooting', fn () => view('docs.troubleshooting'));
