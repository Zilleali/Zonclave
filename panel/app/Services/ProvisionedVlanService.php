<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdminLogAction;
use App\Exceptions\VlanInUseException;
use App\Models\PpskGroup;
use App\Models\ProvisionedVlan;
use App\Models\TunnelEgressIp;
use App\Repositories\AdminLogRepository;
use Illuminate\Support\Facades\DB;

// Writes to provisioned_vlans (CLAUDE.md Section 16.5). The only mutation
// path for that table, same convention as PpskService/TunnelEgressIpService:
// every change is logged, and a delete that would orphan a live PPSK
// credential is blocked rather than silently allowed.
class ProvisionedVlanService
{
    public function __construct(
        private readonly AdminLogRepository $auditLog,
        private readonly BackupService $backups,
    ) {}

    public function provision(int $vlanId, ?string $adminUser): ProvisionedVlan
    {
        $vlan = DB::transaction(function () use ($vlanId, $adminUser): ProvisionedVlan {
            $vlan = ProvisionedVlan::query()->create(['vlan_id' => $vlanId]);

            $this->auditLog->log(AdminLogAction::VlanProvisioned, $adminUser, null, sprintf('VLAN %d', $vlanId));

            return $vlan;
        });

        // Outside the transaction, same reasoning as PpskService::create()
        // (Section 16.8, client request: back up after a significant
        // registry change, not just on the daily schedule).
        $this->backups->maybeAutoBackup($adminUser);

        return $vlan;
    }

    /**
     * @throws VlanInUseException if a PPSK (active or disabled) still references this VLAN
     */
    public function deprovision(ProvisionedVlan $vlan, ?string $adminUser): void
    {
        $labels = PpskGroup::query()->where('vlan_id', $vlan->vlan_id)->pluck('label');

        if ($labels->isNotEmpty()) {
            throw VlanInUseException::forVlan($vlan->vlan_id, $labels);
        }

        DB::transaction(function () use ($vlan, $adminUser): void {
            TunnelEgressIp::query()->where('vlan_id', $vlan->vlan_id)->delete();

            $vlanId = $vlan->vlan_id;
            $vlan->delete();

            $this->auditLog->log(AdminLogAction::VlanDeprovisioned, $adminUser, null, sprintf('VLAN %d', $vlanId));
        });

        $this->backups->maybeAutoBackup($adminUser);
    }
}
