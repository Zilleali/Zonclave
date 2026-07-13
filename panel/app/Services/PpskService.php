<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Psk;
use App\Domain\PskGenerator;
use App\Domain\RadiusDerivation;
use App\Domain\VlanPlan;
use App\Enums\AdminLogAction;
use App\Enums\PpskStatus;
use App\Models\PpskGroup;
use App\Repositories\AdminLogRepository;
use App\Repositories\PpskGroupRepository;
use App\Repositories\RadiusRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

// Business logic for PPSK lifecycle (CLAUDE.md Sections 18 and 23.1).
// Every mutation runs in a single transaction: registry write, RADIUS
// projection, and admin_log entry commit or roll back together. In Phase 2
// these same methods gain OPNsense API steps; callers do not change.
class PpskService
{
    public function __construct(
        private readonly PpskGroupRepository $groups,
        private readonly RadiusRepository $radius,
        private readonly AdminLogRepository $auditLog,
        private readonly PskGenerator $generator,
    ) {}

    /**
     * Create a PPSK group. The PSK is generated, never supplied (Section 14).
     * Returns the group and the cleartext PSK for one-time display.
     *
     * @return array{group: PpskGroup, psk: string}
     */
    public function create(string $label, int $vlanId, bool $enabled, ?string $adminUser): array
    {
        $psk = $this->generator->generate();
        $plan = VlanPlan::forVlan($vlanId);
        $status = $enabled ? PpskStatus::Active : PpskStatus::Disabled;

        $group = DB::transaction(function () use ($label, $plan, $status, $psk, $adminUser): PpskGroup {
            $group = $this->groups->create([
                'label' => $label,
                // The username embeds the row id (Section 6); a placeholder
                // satisfies the unique constraint until the id exists.
                'radius_username' => 'pending_'.bin2hex(random_bytes(8)),
                'password_hash' => Crypt::encryptString($psk->value),
                'vlan_id' => $plan['vlan_id'],
                'subnet' => $plan['subnet'],
                'wireguard_interface' => $plan['wireguard_interface'],
                'wireguard_gateway' => $plan['wireguard_gateway'],
                'status' => $status,
            ]);

            $group = $this->groups->update($group, [
                'radius_username' => sprintf('ppsk_group%03d', $group->id),
            ]);

            $this->projectToRadius($group);
            $this->auditLog->log(AdminLogAction::PpskCreated, $adminUser, $group->id, $group->label);

            return $group;
        });

        return ['group' => $group, 'psk' => $psk->value];
    }

    /**
     * Update label and/or VLAN. A VLAN change re-derives subnet, tunnel,
     * and gateway from the plan and reprojects the RADIUS rows.
     */
    public function update(PpskGroup $group, string $label, int $vlanId, ?string $adminUser): PpskGroup
    {
        $plan = VlanPlan::forVlan($vlanId);

        return DB::transaction(function () use ($group, $label, $plan, $adminUser): PpskGroup {
            $group = $this->groups->update($group, [
                'label' => $label,
                'vlan_id' => $plan['vlan_id'],
                'subnet' => $plan['subnet'],
                'wireguard_interface' => $plan['wireguard_interface'],
                'wireguard_gateway' => $plan['wireguard_gateway'],
            ]);

            $this->projectToRadius($group);
            $this->auditLog->log(AdminLogAction::PpskUpdated, $adminUser, $group->id, $group->label);

            return $group;
        });
    }

    public function enable(PpskGroup $group, ?string $adminUser): PpskGroup
    {
        return $this->setStatus($group, PpskStatus::Active, AdminLogAction::PpskEnabled, $adminUser);
    }

    // Disabling revokes authentication via the projection, not just a UI
    // flag (Section 23.1).
    public function disable(PpskGroup $group, ?string $adminUser): PpskGroup
    {
        return $this->setStatus($group, PpskStatus::Disabled, AdminLogAction::PpskDisabled, $adminUser);
    }

    public function delete(PpskGroup $group, ?string $adminUser): void
    {
        DB::transaction(function () use ($group, $adminUser): void {
            $this->radius->purgeFor($group->radius_username);
            $this->auditLog->log(AdminLogAction::PpskDeleted, $adminUser, $group->id, $group->label);
            $this->groups->delete($group);
        });
    }

    /** @return array{group: PpskGroup, psk: string} the new cleartext PSK, shown once */
    public function regeneratePassword(PpskGroup $group, ?string $adminUser): array
    {
        $psk = $this->generator->generate();

        $group = DB::transaction(function () use ($group, $psk, $adminUser): PpskGroup {
            $group = $this->groups->update($group, [
                'password_hash' => Crypt::encryptString($psk->value),
            ]);

            $this->projectToRadius($group);
            $this->auditLog->log(AdminLogAction::PpskPasswordRegenerated, $adminUser, $group->id, $group->label);

            return $group;
        });

        return ['group' => $group, 'psk' => $psk->value];
    }

    /**
     * THE registry-to-RADIUS path (CLAUDE.md Section 23.1). The only place
     * in the panel that materializes radcheck/radreply, always from the
     * stored ppsk_groups row, and the only caller of RadiusRepository.
     * Callers must invoke it inside their transaction.
     */
    public function projectToRadius(PpskGroup $group): void
    {
        // Decrypted only at the point the projection needs it (Section 14).
        $psk = Psk::fromString(Crypt::decryptString($group->password_hash));

        $this->radius->replaceFor(
            $group->radius_username,
            RadiusDerivation::radcheckRows($group->radius_username, $psk, $group->status),
            RadiusDerivation::radreplyRows($group->radius_username, $group->vlan_id, $group->status),
        );
    }

    private function setStatus(PpskGroup $group, PpskStatus $status, AdminLogAction $action, ?string $adminUser): PpskGroup
    {
        return DB::transaction(function () use ($group, $status, $action, $adminUser): PpskGroup {
            $group = $this->groups->update($group, ['status' => $status]);

            $this->projectToRadius($group);
            $this->auditLog->log($action, $adminUser, $group->id, $group->label);

            return $group;
        });
    }
}
