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
];
