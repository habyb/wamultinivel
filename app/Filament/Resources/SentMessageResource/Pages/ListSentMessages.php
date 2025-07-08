<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use App\Filament\Resources\SentMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSentMessages extends ListRecords
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
            null => __('List'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('Create message')),
        ];
    }
}
