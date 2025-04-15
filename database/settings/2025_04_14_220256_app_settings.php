<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('app.whatsapp_number', '5511999999999');
        $this->migrator->add('app.support_email', 'suporte@exemplo.com');
    }
};
