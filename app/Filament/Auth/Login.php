<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Lindr Admin Portal';
    }

    public function getHeading(): string
    {
        return 'Admin Sign In';
    }

    public function getSubheading(): ?string
    {
        return 'System Administrators Only — App users access Lindr via the mobile app.';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->default('admin@lindr.app')
            ->helperText('Administrator Email: admin@lindr.app');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->default('password')
            ->helperText('Administrator Password: password');
    }
}
