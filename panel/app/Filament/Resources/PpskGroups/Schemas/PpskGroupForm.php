<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Schemas;

use App\Domain\Psk;
use App\Domain\RadiusUsername;
use App\Domain\VlanPlan;
use App\Models\PpskGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

// Create/Edit PPSK form, per CLAUDE.md Section 16.3. Password is
// auto-generate by default, with manual entry as an explicit opt-in
// (Section 14, decision reversed 2026-07-17, client request). On create
// this is passwordFields(); on edit, regeneration is opt-in via a toggle
// (passwordRegenerateFields() - client request 2026-07-25, previously a
// separate row action). Either path still goes through Psk::fromString
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
                ->placeholder('VLAN300_GUESTA')
                // No format constraint (decision reversed 2026-07-25, client
                // request - the VLAN<id>_<GroupName> convention was
                // previously enforced by regex; the client wants complete
                // freedom to name a group however he likes, e.g. a person's
                // name or a plain description with no VLAN reference at
                // all). Suggestions from existing labels via the browser's
                // native <datalist> remain purely a convenience, never a
                // constraint - this field never had any other validation.
                ->datalist(fn (): array => PpskGroup::query()->pluck('label')->unique()->values()->all())
                ->helperText('Any name you like. Pick a suggestion or type your own.'),

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
     * The RADIUS username choice, shown on create. Auto-generate
     * (ppsk_group###) by default, with manual entry as an explicit opt-in
     * (Section 6, decision reversed 2026-07-18, client request) for cases
     * like a client-supplied naming scheme. Either way the value goes
     * through RadiusUsername::fromString() (format) and this field's own
     * ->unique() rule (Section 7's UNIQUE NOT NULL constraint, surfaced as
     * a form error instead of a raw query exception) before PpskService
     * ever persists it.
     *
     * Editing the username after creation is a separate, deliberately
     * blunter field - usernameEditField() below, not this generate/manual
     * choice (regenerating a *new* random username during an edit isn't a
     * meaningful action the way it is at create time).
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

    /**
     * RADIUS username, directly editable from the Edit action (client
     * request, 2026-07-25 - reverses the earlier create-only decision
     * recorded above: "there is no change username action after creation,
     * since an existing device is already paired against whatever
     * username it authenticated with." That risk is now accepted -
     * renaming a group here will silently stop any device still using the
     * old username from reconnecting, with no signal to the admin that
     * happened. PpskService::update() purges the old username's RADIUS
     * rows so it stops authenticating rather than lingering as an
     * orphaned credential. Same validation boundary as create
     * (RadiusUsername::fromString(), format regex, uniqueness), except the
     * uniqueness check ignores the record's own current value.
     *
     * @return array<int, TextInput>
     */
    public static function usernameEditField(): array
    {
        return [
            TextInput::make('radius_username')
                ->label('RADIUS username')
                ->required()
                ->minLength(RadiusUsername::MIN_LENGTH)
                ->maxLength(RadiusUsername::MAX_LENGTH)
                ->regex('/^[A-Za-z0-9_-]+$/')
                ->unique(table: 'ppsk_groups', column: 'radius_username', ignoreRecord: true)
                ->helperText(sprintf('%d to %d characters: letters, numbers, underscores, and hyphens only. A device already connected with the old username stops authenticating once this changes.', RadiusUsername::MIN_LENGTH, RadiusUsername::MAX_LENGTH)),
        ];
    }

    /**
     * Optional password regeneration, embedded in the Edit action (client
     * request, 2026-07-25 - previously a separate row action; see
     * PpskGroupsTable's history). Off by default so opening Edit never
     * silently regenerates anything; the same generate/manual choice as
     * create appears only once the toggle is on.
     *
     * @return array<int, Toggle|Radio|TextInput>
     */
    public static function passwordRegenerateFields(): array
    {
        return [
            Toggle::make('regenerate_password')
                ->label('Regenerate password')
                ->live()
                ->default(false)
                ->helperText('The current Wi-Fi password stops working immediately and the new one is shown once.'),

            Radio::make('password_source')
                ->label('New password')
                ->options([
                    'generate' => 'Auto-generate (recommended)',
                    'manual' => 'Enter manually',
                ])
                ->default('generate')
                ->inline()
                ->live()
                ->visible(fn (Get $get): bool => (bool) $get('regenerate_password')),

            TextInput::make('manual_password')
                ->label('New password')
                ->password()
                ->revealable()
                ->minLength(Psk::MIN_LENGTH)
                ->maxLength(Psk::MAX_LENGTH)
                ->helperText(sprintf('%d to %d characters (WPA2 personal PSK constraint, Section 14).', Psk::MIN_LENGTH, Psk::MAX_LENGTH))
                ->requiredIf('password_source', 'manual')
                ->visible(fn (Get $get): bool => (bool) $get('regenerate_password') && $get('password_source') === 'manual'),
        ];
    }
}
