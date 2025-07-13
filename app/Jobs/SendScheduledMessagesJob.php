<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\SentMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\WhatsAppServiceBusinessApi;

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
                    $param_type_header = [];

                    if ($message->type == 'image' || $message->type == 'video') {
                        $url = asset('storage/' . $message->path);

                        $param_type_header = [
                            'type' => 'header',
                            'parameters' => [
                                ['type' => $message->type, $message->type => [
                                    'link' => $url
                                ]]
                            ],
                        ];
                    }

                    $response = app(WhatsAppServiceBusinessApi::class)->sendText(
                        phone: $number,
                        template: $message->template_name,
                        language: $message->template_language,
                        params: [
                            $param_type_header,
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', "parameter_name" => "name", 'text' => $user['name']]
                                ],
                            ]
                        ]
                    );

                    // Sent messages logs
                    $status = $response['messages'][0]['message_status'] ?? null;

                    DB::table('sent_messages_logs')->insert([
                        'sent_message_id' => $message->id,
                        'contact_name'    => $user['name'],
                        'remote_jid'      => $number,
                        'message_status'  => $status,
                        'sent_at'         => now(),
                    ]);
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
