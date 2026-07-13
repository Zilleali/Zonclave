<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

// Overrides Filament's default profile page (CLAUDE.md Section 16.1: single
// admin, no self-registration). The email identifies the one admin account;
// once set it is display-only here so it can't be changed away from itself
// by mistake. Change it via panel:create-admin or Settings if ever needed.
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->disabled()
            ->dehydrated(false);
    }
}
