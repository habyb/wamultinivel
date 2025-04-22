<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class InviteLinkWidget extends Widget
{
    protected static string $view = 'filament.pages.widgets.invite-link-widget';

    protected int | string | array $columnSpan = 1;

    public function getInviteUrl(): string
    {
        $user = auth()->user();

        $code = $user->code;

        return url("/{$code}");
    }
}
