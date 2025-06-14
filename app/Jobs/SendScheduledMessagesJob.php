<?php

namespace App\Jobs;

use App\Models\SentMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
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
    public function handle(WhatsAppService $whatsAppService): void
    {
        $messages = SentMessage::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('sent_at')
                    ->orWhere('sent_at', '<=', Carbon::now());
            })
            ->get();

        foreach ($messages as $message) {
            $mimetype = '';
            $url = Storage::url($message->path);

            if (Storage::disk('public')->exists($message->path)) {
                $mimetype = Storage::disk('public')->mimeType($message->path);
            }

            try {
                $users = is_string($message->contacts_result)
                    ? collect(json_decode($message->contacts_result, true))
                    : collect($message->contacts_result ?? []);

                foreach ($users as $user) {
                    $number = fix_whatsapp_number($user['remoteJid']);
                    $mediatype = $message->type;
                    $mimetype = $mimetype;
                    $caption = $message->description;
                    $media = env('APP_URL') . $url;
                    $fileName = basename($media);

                    logger()->info("Mensagem enviada para usuário {$user['id']} ({$user['name']})");

                    switch ($message->type) {
                        case 'text':
                            logger()->info("number: $number, text: $message->description");
                            // $whatsAppService->sendText($user->phone, $message->description);
                            break;
                        case 'image':
                            logger()->info("number: $number, mediatype: $mediatype, mimetype: $mimetype, caption: $caption, media: $media, fileName: $fileName");
                            // $whatsAppService->sendMediaUrl(
                            //     $number,
                            //     $mediatype,
                            //     $mimetype,
                            //     $caption,
                            //     $media,
                            //     $fileName
                            // );
                            break;
                        case 'document':
                            logger()->info("number: $number, mediatype: $mediatype, mimetype: $mimetype, caption: $caption, media: $media, fileName: $fileName");
                            # code...
                            break;
                        case 'video':
                            logger()->info("number: $number, mediatype: $mediatype, mimetype: $mimetype, caption: $caption, media: $media, fileName: $fileName");
                            # code...
                            break;
                        case 'audio':
                            logger()->info("number: $number, mediatype: $mediatype, mimetype: $mimetype, caption: $caption, media: $media, fileName: $fileName");
                            # code...
                            break;
                        default:
                            # code...
                            break;
                    }
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
