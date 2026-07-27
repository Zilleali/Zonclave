<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdminLogAction;
use App\Models\TunnelEgressIp;
use App\Repositories\AdminLogRepository;
use Illuminate\Support\Facades\DB;

// Writes to tunnel_egress_ips (CLAUDE.md Section 16.6). The only mutation
// path for that table, same convention as PpskService's relationship to
// ppsk_groups/radcheck/radreply (Section 18): the Filament page never writes
// the model directly, it always goes through here so every change is logged.
class TunnelEgressIpService
{
    public function __construct(
        private readonly AdminLogRepository $auditLog,
    ) {}

    public function update(int $vlanId, ?string $egressIp, ?string $adminUser): TunnelEgressIp
    {
        return DB::transaction(function () use ($vlanId, $egressIp, $adminUser): TunnelEgressIp {
            $record = TunnelEgressIp::query()->updateOrCreate(
                ['vlan_id' => $vlanId],
                [
                    'egress_ip' => $egressIp,
                    'checked_at' => now(),
                    'updated_by' => $adminUser,
                ],
            );

            $this->auditLog->log(
                AdminLogAction::TunnelEgressIpUpdated,
                $adminUser,
                null,
                sprintf('VLAN %d: %s', $vlanId, $egressIp ?? '(cleared)'),
            );

            return $record;
        });
    }
}
