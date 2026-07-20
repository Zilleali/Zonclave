<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use RuntimeException;

// Renders the repo's docs/*.md files live on the public /docs pages, so
// the source markdown and the published page can never drift apart -
// there is exactly one place these three documents are written (client
// decision, 2026-07-18: automate docs/landing-page sync rather than rely
// on remembering to hand-copy every change).
//
// Deliberately NOT a general "render any markdown file" tool. Only the
// three files in ALLOWED are reachable, ever - docs/runbook/*.md and
// CLAUDE.md contain real Sancover site details and must never be
// published (same boundary PublicPagesTest enforces). A slug is looked
// up in a fixed map, never used to build a file path directly, so there
// is no path-traversal surface here regardless of what a route parameter
// might contain.
final class DocsMarkdownRenderer
{
    /** @var array<string, string> public slug => filename in config('zonclave.docs_path') */
    private const ALLOWED = [
        'commands-reference' => 'commands-reference.md',
        'opnsense-configuration' => 'opnsense-configuration.md',
        'installation-guide' => 'installation-guide.md',
    ];

    public function render(string $slug): string
    {
        if (! isset(self::ALLOWED[$slug])) {
            throw new InvalidArgumentException("\"{$slug}\" is not a publishable doc.");
        }

        $path = rtrim((string) config('zonclave.docs_path'), '/\\').DIRECTORY_SEPARATOR.self::ALLOWED[$slug];

        if (! is_file($path)) {
            throw new RuntimeException("Doc source not found at {$path}. Is config('zonclave.docs_path') correct for this environment?");
        }

        $markdown = file_get_contents($path);

        if ($markdown === false) {
            throw new RuntimeException("Could not read {$path}.");
        }

        $html = (new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]))->convert($markdown)->getContent();

        return $this->sanitizeLinks($this->stripLeadingH1($html));
    }

    // The markdown source's own `# Title` duplicates the page template's
    // <h1> (docs/markdown-page.blade.php), which every other public doc
    // page also uses for a consistent header. Only the first <h1> is
    // removed - the rest of the document structure is untouched.
    private function stripLeadingH1(string $html): string
    {
        return preg_replace('/^<h1>.*?<\/h1>\s*/s', '', $html, limit: 1) ?? $html;
    }

    // The source docs cross-reference each other and, in plain prose (not
    // links), mention CLAUDE.md and the runbook by name - that's fine,
    // developers reading the repo need those links. What must never
    // survive onto the public page is a clickable path to a file that
    // isn't published: CLAUDE.md, docs/runbook/**, README.md, db/README.md,
    // or an external repo host. Same-directory links between the three
    // published docs get rewritten to their route instead of left as a
    // dead ".md" href.
    private function sanitizeLinks(string $html): string
    {
        foreach (self::ALLOWED as $slug => $filename) {
            $html = preg_replace(
                '/href="'.preg_quote($filename, '/').'(#[^"]*)?"/',
                'href="/docs/'.$slug.'$1"',
                $html,
            ) ?? $html;
        }

        // Any remaining relative/parent-path or known-internal link (not
        // one of the three rewritten above) is stripped down to plain
        // text - the mention stays, the navigation doesn't.
        return preg_replace_callback(
            '/<a\s+[^>]*href="(?:\.\.\/|runbook\/|https?:\/\/github\.com)[^"]*"[^>]*>(.*?)<\/a>/is',
            static fn (array $m): string => $m[1],
            $html,
        ) ?? $html;
    }
}
