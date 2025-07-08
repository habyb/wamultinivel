<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SentMessageResource;
use Filament\Actions\Action;

class EditSentMessage extends EditRecord
{
    protected static string $resource = SentMessageResource::class;

    /**
     * Customize the head title.
     *
     * @return string
     */
    public function getHeading(): string
    {
        return __('Messages');
    }

    /**
     * Custom breadcrumb trail for this page.
     *
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.send-messages.index') => __('Messages'),
            null => __('View'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled(fn() => $this->record->status === 'sent');
    }
}
