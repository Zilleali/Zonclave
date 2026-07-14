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

    public function test_public_pages_do_not_link_to_github_or_internal_docs(): void
    {
        // Section 20/25.3-style guardrail for this feature: the runbook and
        // CLAUDE.md contain real client infrastructure details and must
        // never be linked from a public page.
        foreach (['/', '/docs', '/docs/installation-guide', '/docs/commands-reference', '/docs/opnsense-configuration'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertIsString($html);
            $this->assertStringNotContainsString('github.com', $html);
            $this->assertStringNotContainsString('runbook', $html);
        }
    }
}
