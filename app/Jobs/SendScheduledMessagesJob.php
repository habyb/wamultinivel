<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\SentMessage;
use App\Models\SentMessagesLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use App\Services\WhatsAppServiceBusinessApi;

class SendScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O tempo limite (em segundos) que o job pode rodar.
     */
    public int $timeout = 900;

    /**
     * Tamanho do lote de mensagens (sent_messages) por execução.
     * Cada mensagem pode ter N contatos em contacts_result.
     */
    public int $batchSize;

    /**
     * Opcional: permitir configurar o batch no dispatch()
     */
    public function __construct(int $batchSize = 1)
    {
        $this->batchSize = $batchSize;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now   = Carbon::now()->startOfMinute();
        $token = (string) Str::uuid();

        // 1) BUSCA IDs (portável: SELECT + LIMIT) para depois dar UPDATE WHERE IN
        $ids = SentMessage::query()
            ->where('status', 'pending')
            ->where('sent_at', '<=', $now)
            ->orderBy('id')
            ->limit($this->batchSize)
            ->pluck('id');

        if ($ids->isEmpty()) {
            Log::info('[SendScheduledMessagesJob] Nenhuma mensagem para claimar.');
            return;
        }

        // 2) CLAIM ATÔMICO: só atualiza se ainda estiver pending (evita corrida)
        $claimed = SentMessage::query()
            ->whereIn('id', $ids)
            ->where('status', 'pending')
            ->update([
                'status'     => 'processing',
                'lock_token' => $token,
                'locked_at'  => now(),
            ]);

        if ($claimed === 0) {
            Log::info('[SendScheduledMessagesJob] Nenhuma mensagem claimada (condição mudou).');
            return;
        }

        Log::info("[SendScheduledMessagesJob] {$claimed} mensagen(s) claimada(s). token={$token}");

        // 3) Carrega apenas as mensagens deste worker (token)
        $messages = SentMessage::query()
            ->where('lock_token', $token)
            ->orderBy('id')
            ->get();

        foreach ($messages as $message) {
            $info = wa_single_line($message->description);

            // 3.1) Decodifica contacts_result de forma robusta
            $users = collect();
            $cr = $message->contacts_result;

            if (is_string($cr)) {
                $decoded = json_decode($cr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $users = collect($decoded);
                } else {
                    Log::warning("[SendScheduledMessagesJob] JSON inválido em contacts_result. id={$message->id}");
                }
            } elseif (is_array($cr)) {
                $users = collect($cr);
            }

            // Sanitiza estrutura: pega apenas chaves relevantes e filtra sem remoteJid
            $users = $users->map(function ($u) {
                return [
                    'name'      => $u['name']      ?? '',
                    'remoteJid' => $u['remoteJid'] ?? '',
                    'id'        => $u['id']        ?? null,
                ];
            })->filter(fn($u) => !empty($u['remoteJid']))->values();

            if ($users->isEmpty()) {
                // Sem contatos válidos: marca como failed e libera lock
                $message->update([
                    'status'     => 'failed',
                    'lock_token' => null,
                ]);
                Log::warning("[SendScheduledMessagesJob] Sem contatos válidos. id={$message->id}");
                continue;
            }

            // 3.2) Monta parâmetros do template (header/body) por contato e envia
            foreach ($users as $user) {
                $number = fix_whatsapp_number($user['remoteJid']);

                // Evita reenvio se o log já existir no banco (idempotência)
                $alreadySent = SentMessagesLog::where('sent_message_id', $message->id)
                    ->where('remote_jid', $number)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                try {
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

                    $params = [];
                    if (!empty($param_type_header)) {
                        $params[] = $param_type_header;
                    }
                    $params[] = [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'parameter_name' => 'name',
                                'text' => $user['name']
                            ],
                            [
                                'type' => 'text',
                                'parameter_name' => 'info',
                                'text' => $info
                            ],
                        ],
                    ];

                    $response = app(WhatsAppServiceBusinessApi::class)->sendText(
                        phone: $number,
                        template: $message->template_name,
                        language: $message->template_language ?? 'pt_BR',
                        params: $params
                    );

                    $status = $response['messages'][0]['message_status'] ?? null;

                    // Salva log de sucesso imediatamente
                    SentMessagesLog::create([
                        'sent_message_id'   => $message->id,
                        'remote_jid'        => $number,
                        'contact_name'      => $user['name'],
                        'message_status'    => $status,
                        'sent_at'           => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::error("[SendScheduledMessagesJob] Falha ao enviar para {$number}. msg_id={$message->id}. erro={$e->getMessage()}");

                    // Salva log de falha imediatamente
                    SentMessagesLog::create([
                        'sent_message_id'   => $message->id,
                        'remote_jid'        => $number,
                        'contact_name'      => $user['name'],
                        'message_status'    => 'failed',
                        'sent_at'           => now(),
                    ]);
                }
            }

            // 3.3) Define status final da mensagem baseado em TODOS os logs salvos até o momento
            $totalLogs = SentMessagesLog::where('sent_message_id', $message->id)->get();
            $dbSuccessCount = $totalLogs->where('message_status', '!=', 'failed')->count();
            $dbFailCount = $totalLogs->where('message_status', 'failed')->count();

            if ($dbSuccessCount > 0) {
                $message->update([
                    'status'         => 'sent',
                    'contacts_count' => $users->count(),
                    'sent_ok_at'     => now(),
                    'lock_token'     => null, // liberar
                ]);
                Log::info("info: {$info}");
                Log::info("[SendScheduledMessagesJob] Mensagem enviada. id={$message->id} ok={$dbSuccessCount} fail={$dbFailCount}");
            } else {
                $message->update([
                    'status'     => 'failed',
                    'lock_token' => null, // liberar
                ]);
                Log::warning("[SendScheduledMessagesJob] Mensagem sem sucessos. id={$message->id} fail={$dbFailCount}");
            }
        }
    }
}
