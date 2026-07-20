<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

// The public marketing/docs pages (Section 16 doesn't cover these - they
// sit outside /admin, unauthenticated by design). No RADIUS boundary
// concerns here; this just confirms every public route renders.
class PublicPagesTest extends TestCase
{
    public function test_landing_page_is_reachable(): void
    {
        $this->get('/')->assertOk()->assertSee('Zonclave');
    }

    public function test_docs_index_is_reachable(): void
    {
        $this->get('/docs')->assertOk()->assertSee('Documentation');
    }

    public function test_installation_guide_is_reachable(): void
    {
        $this->get('/docs/installation-guide')->assertOk()->assertSee('Installation Guide');
    }

    public function test_commands_reference_is_reachable(): void
    {
        $this->get('/docs/commands-reference')->assertOk()->assertSee('Command Reference');
    }

    public function test_opnsense_configuration_guide_is_reachable(): void
    {
        $this->get('/docs/opnsense-configuration')->assertOk()->assertSee('OPNsense Configuration Guide');
    }

    public function test_site_configuration_guide_is_reachable(): void
    {
        $this->get('/docs/site-configuration')->assertOk()->assertSee('Site Configuration');
    }

    public function test_troubleshooting_guide_is_reachable(): void
    {
        $this->get('/docs/troubleshooting')->assertOk()->assertSee('Troubleshooting');
    }

    public function test_public_pages_do_not_link_to_github_or_the_raw_runbook_file(): void
    {
        // Section 20/25.3-style guardrail: this deployment's real site
        // detail is deliberately published on /docs/site-configuration and
        // /docs/troubleshooting (client decision, recorded in CLAUDE.md
        // Section 20). What must still never happen is a clickable link out
        // to the source repository or to the raw runbook file itself - the
        // public pages transcribe the content, they don't expose the repo.
        // A plain-text mention (e.g. installation-guide.md's own prose
        // naming the runbook, same as it already names CLAUDE.md elsewhere)
        // is fine - App\Support\DocsMarkdownRenderer strips the href but
        // intentionally leaves the surrounding sentence readable.
        foreach ([
            '/', '/docs', '/docs/installation-guide', '/docs/commands-reference',
            '/docs/opnsense-configuration', '/docs/site-configuration', '/docs/troubleshooting',
        ] as $url) {
            $html = $this->get($url)->getContent();
            $this->assertIsString($html);

            preg_match_all('/href="([^"]*)"/', $html, $matches);
            $hrefs = $matches[1];

            foreach ($hrefs as $href) {
                $this->assertStringNotContainsString('github.com', $href, "{$url} links to github.com ({$href})");
                $this->assertStringNotContainsString('runbook/', $href, "{$url} links to the raw runbook file ({$href})");
            }
        }
    }
}
