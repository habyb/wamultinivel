<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\SendScheduledMessagesJob;
use App\Models\SentMessage;
use App\Models\SentMessagesLog;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

class SendScheduledMessagesJobTest extends TestCase
{
    use DatabaseTransactions;

    public function test_job_respects_idempotency_and_logs_immediately(): void
    {
        // 1. Create a dummy campaign message with 2 contacts
        $message = SentMessage::create([
            'template_name' => 'imagem_sem_botao',
            'type' => 'image',
            'path' => 'messages/dummy.jpg',
            'description' => 'Test message content',
            'status' => 'pending',
            'sent_at' => now()->startOfMinute(),
            'contacts_result' => [
                ['name' => 'User One', 'remoteJid' => '5547992010169'],
                ['name' => 'User Two', 'remoteJid' => '5547992010170']
            ]
        ]);

        // 2. Pre-create a log for User One so they are treated as already sent (idempotency)
        SentMessagesLog::create([
            'sent_message_id' => $message->id,
            'remote_jid' => '5547992010169',
            'contact_name' => 'User One',
            'message_status' => 'accepted',
            'sent_at' => now()->subMinute(),
        ]);

        // Mock WhatsApp Business API request (should only be called once for User Two)
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '5547992010170', 'wa_id' => '5547992010170']],
                'messages' => [['id' => 'wamid.HBgLNTU0Nzk5MjAxMDE3MFZD']]
            ], 200)
        ]);

        // 3. Dispatch the job (batch size 1 to fetch our campaign)
        $job = new SendScheduledMessagesJob(1);
        $job->handle();

        // 4. Verify that User Two was processed and logged, but User One was skipped (no duplicate logs)
        $this->assertEquals(2, SentMessagesLog::where('sent_message_id', $message->id)->count());

        $userTwoLog = SentMessagesLog::where('sent_message_id', $message->id)
            ->where('remote_jid', '5547992010170')
            ->first();

        $this->assertNotNull($userTwoLog);
        $this->assertEquals('wamid.HBgLNTU0Nzk5MjAxMDE3MFZD', $userTwoLog->message_status ?? null);

        // 5. Verify the campaign message status was updated to 'sent'
        $message->refresh();
        $this->assertEquals('sent', $message->status);
    }
}
