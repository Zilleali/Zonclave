<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Schemas;

use App\Domain\Psk;
use App\Domain\RadiusUsername;
use App\Domain\VlanPlan;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

// Create/Edit PPSK form, per CLAUDE.md Section 16.3. Password is
// auto-generate by default, with manual entry as an explicit opt-in
// (Section 14, decision reversed 2026-07-17, client request) - shown only
// on create, since editing an existing group never touches its password
// (that's what "regenerate" is for, a separate action with its own choice
// of the same two options). Either path still goes through Psk::fromString
// (Section 14's validation boundary) before PpskService ever persists it.
// Subnet, tunnel, and gateway are fixed 1:1 derivations of the VLAN and are
// shown in the option label, never chosen.
class PpskGroupForm
{
    // Generic fallback shape (label, VLAN, enabled-on-create). The actual
    // Create and Edit modal actions (ListPpskGroups, PpskGroupsTable) build
    // their own schemas directly from labelAndVlanFields()/passwordFields()
    // rather than calling this, since Create needs the password fields and
    // Edit must not have them - a single combined schema can't cleanly gate
    // both an operation and a live field dependency at once.
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            ...self::labelAndVlanFields(),
            ...self::enabledField(),
        ]);
    }

    /** @return array<int, Toggle> */
    public static function enabledField(): array
    {
        return [
            Toggle::make('enabled')
                ->label('Enabled')
                ->default(true)
                ->helperText('Disabled groups cannot authenticate. Toggle later from the list.'),
        ];
    }

    /** @return array<int, TextInput|Select> */
    public static function labelAndVlanFields(): array
    {
        return [
            TextInput::make('label')
                ->label('Label')
                ->required()
                ->maxLength(128)
                ->regex('/^VLAN\d+_[A-Za-z0-9]+$/')
                ->placeholder('VLAN300_GUESTA')
                ->helperText('Naming convention: VLAN<id>_<GroupName> (Section 6).'),

            Select::make('vlan_id')
                ->label('VLAN / tunnel')
                ->required()
                ->options(VlanPlan::options())
                ->helperText('VLAN, subnet, WireGuard tunnel, and gateway are paired 1:1; picking the VLAN picks them all.'),
        ];
    }

    /**
     * The password-source choice, reused identically by the create form and
     * the regenerate-password action's own confirmation form.
     *
     * @return array<int, Radio|TextInput>
     */
    public static function passwordFields(): array
    {
        return [
            Radio::make('password_source')
                ->label('Password')
                ->options([
                    'generate' => 'Auto-generate (recommended)',
                    'manual' => 'Enter manually',
                ])
                ->default('generate')
                ->inline()
                ->live(),

            TextInput::make('manual_password')
                ->label('New password')
                ->password()
                ->revealable()
                ->minLength(Psk::MIN_LENGTH)
                ->maxLength(Psk::MAX_LENGTH)
                ->helperText(sprintf('%d to %d characters (WPA2 personal PSK constraint, Section 14).', Psk::MIN_LENGTH, Psk::MAX_LENGTH))
                ->requiredIf('password_source', 'manual')
                ->visible(fn (Get $get): bool => $get('password_source') === 'manual'),
        ];
    }

    /**
     * The RADIUS username choice, create-only - a group's username is fixed
     * once it exists (there is no "regenerate username" action, unlike the
     * password, since a live username change would break any device already
     * paired against it). Auto-generate (ppsk_group###) by default, with
     * manual entry as an explicit opt-in (Section 6, decision reversed
     * 2026-07-18, client request) for cases like a client-supplied naming
     * scheme. Either way the value goes through RadiusUsername::fromString()
     * (format) and this field's own ->unique() rule (Section 7's UNIQUE
     * NOT NULL constraint, surfaced as a form error instead of a raw query
     * exception) before PpskService ever persists it.
     *
     * @return array<int, Radio|TextInput>
     */
    public static function usernameFields(): array
    {
        return [
            Radio::make('username_source')
                ->label('RADIUS username')
                ->options([
                    'generate' => 'Auto-generate (recommended)',
                    'manual' => 'Enter manually',
                ])
                ->default('generate')
                ->inline()
                ->live(),

            TextInput::make('manual_username')
                ->label('Username')
                ->minLength(RadiusUsername::MIN_LENGTH)
                ->maxLength(RadiusUsername::MAX_LENGTH)
                ->regex('/^[A-Za-z0-9_-]+$/')
                ->unique(table: 'ppsk_groups', column: 'radius_username')
                ->helperText(sprintf('%d to %d characters: letters, numbers, underscores, and hyphens only.', RadiusUsername::MIN_LENGTH, RadiusUsername::MAX_LENGTH))
                ->requiredIf('username_source', 'manual')
                ->visible(fn (Get $get): bool => $get('username_source') === 'manual'),
        ];
    }
}
