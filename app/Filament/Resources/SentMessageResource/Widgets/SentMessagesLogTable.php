<?php

namespace App\Filament\Resources\SentMessageResource\Widgets;

use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SentMessagesLog;
use App\Models\SentMessage;

class SentMessagesLogTable extends BaseWidget
{
    public ?SentMessage $record = null;

    protected static ?string $heading = 'Status de Entrega';
    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return SentMessagesLog::query()
            ->where('sent_message_id', $this->record->id)
            ->orderByDesc('sent_at');
    }

    /**
     * Header actions -> aparecem à direita do título
     */
    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('counter')
                ->label(function () {
                    // Total de registros exibidos por este widget (para a mensagem atual)
                    $total = (clone $this->getTableQuery())->count();

                    return 'Total: ' . number_format($total, 0, ',', '.');
                })
                ->color('gray')
                ->disabled()
                ->button()
                ->extraAttributes([
                    'class' => 'pointer-events-none',
                ]),
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('sent_at')->label(__('Date/Hour'))->dateTime('d/m/Y H:i:s'),
            TextColumn::make('contact_name')->label(__('Name')),
            TextColumn::make('remote_jid')
                ->label('WhatsApp')
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                }),
            TextColumn::make('message_status')
                ->label('Status')
                ->badge()
                ->color(fn(string $state) => match ($state) {
                    'accepted'  => 'success',
                    'sent'      => 'success',
                    'delivered' => 'success',
                    'queued'    => 'warning',
                    'read'      => 'warning',
                    'failed'    => 'danger',
                    default     => 'gray',
                })
                ->formatStateUsing(fn(string $state): string => match ($state) {
                    'accepted'    => __('Accepted'),
                    'queued'    => __('Queued'),
                    'sent'      => __('Sent'),
                    'delivered' => __('Delivered'),
                    'read'      => __('Read'),
                    'failed'    => __('Failed'),
                    default     => ucfirst($state),
                }),

        ];
    }
}
