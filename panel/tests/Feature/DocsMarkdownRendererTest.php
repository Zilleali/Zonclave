<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\DocsMarkdownRenderer;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

// Section 20/25.3-style guardrail (client decision 2026-07-18: render
// docs/*.md live rather than hand-copy them onto the public pages). The
// safety property that matters here is narrower than "renders markdown
// correctly" - it's "only the three publishable files are ever
// reachable, and a link to anything else never survives as a link."
class DocsMarkdownRendererTest extends TestCase
{
    private string $docsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docsDir = sys_get_temp_dir().'/zonclave-docs-test-'.bin2hex(random_bytes(4));
        mkdir($this->docsDir);
        mkdir($this->docsDir.'/runbook');

        config(['zonclave.docs_path' => $this->docsDir]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_filter(glob($this->docsDir.'/runbook/*') ?: [], 'is_file'));
        array_map('unlink', array_filter(glob($this->docsDir.'/*') ?: [], 'is_file'));
        @rmdir($this->docsDir.'/runbook');
        @rmdir($this->docsDir);

        parent::tearDown();
    }

    public function test_rejects_a_slug_outside_the_fixed_allowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DocsMarkdownRenderer)->render('runbook/phase1-opnsense-unifi');
    }

    public function test_rejects_a_path_traversal_attempt_as_an_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DocsMarkdownRenderer)->render('../CLAUDE');
    }

    public function test_throws_clearly_when_the_source_file_is_missing(): void
    {
        $this->expectException(RuntimeException::class);

        (new DocsMarkdownRenderer)->render('commands-reference');
    }

    public function test_strips_the_leading_h1_since_the_page_template_provides_its_own(): void
    {
        file_put_contents($this->docsDir.'/commands-reference.md', "# Command Reference\n\nBody text.\n");

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringNotContainsString('<h1>', $html);
        $this->assertStringContainsString('Body text.', $html);
    }

    public function test_rewrites_a_same_directory_link_between_public_docs_to_its_route(): void
    {
        file_put_contents($this->docsDir.'/commands-reference.md', "# Command Reference\n\nSee [the guide](installation-guide.md).\n");
        file_put_contents($this->docsDir.'/installation-guide.md', "# Installation Guide\n\nPlaceholder.\n");

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringContainsString('href="/docs/installation-guide"', $html);
        $this->assertStringNotContainsString('href="installation-guide.md"', $html);
    }

    public function test_preserves_an_anchor_when_rewriting_a_same_directory_link(): void
    {
        file_put_contents($this->docsDir.'/commands-reference.md', "# Command Reference\n\nSee [a section](installation-guide.md#setup).\n");
        file_put_contents($this->docsDir.'/installation-guide.md', "# Installation Guide\n\nPlaceholder.\n");

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringContainsString('href="/docs/installation-guide#setup"', $html);
    }

    public function test_strips_a_link_to_claude_md_but_keeps_the_visible_text(): void
    {
        file_put_contents($this->docsDir.'/commands-reference.md', "# Command Reference\n\nSee [CLAUDE.md](../CLAUDE.md) for why.\n");

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringNotContainsString('href', $html);
        $this->assertStringContainsString('CLAUDE.md', $html);
    }

    public function test_strips_a_link_to_the_raw_runbook_file_but_keeps_the_visible_text(): void
    {
        file_put_contents(
            $this->docsDir.'/commands-reference.md',
            "# Command Reference\n\nSee [the runbook](runbook/phase1-opnsense-unifi.md) for site steps.\n",
        );

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringNotContainsString('href', $html);
        $this->assertStringContainsString('the runbook', $html);
    }

    public function test_strips_a_github_link_but_keeps_the_visible_text(): void
    {
        file_put_contents(
            $this->docsDir.'/commands-reference.md',
            "# Command Reference\n\nSource: [the repo](https://github.com/zilleali/Zonclave).\n",
        );

        $html = (new DocsMarkdownRenderer)->render('commands-reference');

        $this->assertStringNotContainsString('href', $html);
        $this->assertStringContainsString('the repo', $html);
    }

    public function test_renders_tables_and_nested_ordered_lists(): void
    {
        $markdown = <<<'MD'
            # OPNsense Configuration Guide

            | Item | Convention |
            | --- | --- |
            | VLAN ID | `VLAN<id>` |

            1. First step
               1. Sub-step
            2. Second step
            MD;
        file_put_contents($this->docsDir.'/opnsense-configuration.md', $markdown);

        $html = (new DocsMarkdownRenderer)->render('opnsense-configuration');

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('Sub-step', $html);
    }
}
