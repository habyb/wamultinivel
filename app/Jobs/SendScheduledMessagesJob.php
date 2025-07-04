<?php

namespace App\Jobs;

use App\Models\SentMessage;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Carbon\Carbon;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public function backoff(): int
    {
        return 60;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $messages = SentMessage::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('sent_at')
                    ->orWhere('sent_at', '<=', Carbon::now());
            })
            ->get();

        foreach ($messages as $message) {
            try {
                $users = is_string($message->contacts_result)
                    ? collect(json_decode($message->contacts_result, true))
                    : collect($message->contacts_result ?? []);

                foreach ($users as $user) {
                    $number = fix_whatsapp_number($user['remoteJid']);

                    app(WhatsAppServiceBusinessApi::class)->sendText(
                        phone: $number,
                        template: $message->template_name,
                        language: $message->template_language,
                        params: [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', "parameter_name" => "name", 'text' => $user['name']]
                                ],
                            ]
                        ]
                    );
                }

                $message->update([
                    'status' => 'sent',
                    'contacts_count' => $users->count(),
                ]);
            } catch (\Throwable $e) {
                Log::error("Erro ao enviar mensagem #{$message->id}: " . $e->getMessage());
                $message->update(['status' => 'failed']);
            }
        }
    }
}
