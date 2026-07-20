<?php

declare(strict_types=1);

// Zonclave deployment plan, per CLAUDE.md Section 5.
// VLANs are pre-provisioned on OPNsense in Phase 1; the panel only offers
// this fixed list. Phase 2 continues the block from vlan_max + 1.
return [

    // Inclusive Phase 1 PPSK VLAN block. VLAN 300 to 304, replicated
    // identically on all three routers.
    'vlan_min' => (int) env('ZONCLAVE_VLAN_MIN', 300),
    'vlan_max' => (int) env('ZONCLAVE_VLAN_MAX', 304),

    // Subnet formula: 10.30.X.0/24 where X = VLAN - 300.
    'vlan_base' => 300,
    'subnet_template' => '10.30.%d.0/24',

    // Where the repo's docs/ directory lives relative to this app, for the
    // public /docs pages to render live (see App\Support\DocsMarkdownRenderer).
    // In a git checkout, panel/ and docs/ are siblings. In production,
    // deploy_panel() and zonclave-update.sh copy docs/ to a sibling of
    // /opt/zonclave (/opt/docs) so this same relative path resolves in
    // both places without an environment-specific branch.
    'docs_path' => env('ZONCLAVE_DOCS_PATH', base_path('../docs')),
];
