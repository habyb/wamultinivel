<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class WhatsAppServiceBusinessApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup mock configurations
        Config::set('services.business.url', 'https://graph.facebook.com');
        Config::set('services.business.version', 'v24.0');
        Config::set('services.business.access_token', 'test_token');
        Config::set('services.business.phone_number_id', '123456');
    }

    public function test_send_text_success(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => '5547992010169', 'wa_id' => '5547992010169']
                ],
                'messages' => [
                    ['id' => 'wamid.HBgLNTU0Nzk5MjAxMDE2OQZDZD']
                ]
            ], 200)
        ]);

        $service = new WhatsAppServiceBusinessApi();
        $response = $service->sendText('5547992010169', 'template_name', 'pt_BR', []);

        $this->assertEquals('wamid.HBgLNTU0Nzk5MjAxMDE2OQZDZD', $response['messages'][0]['id']);
    }

    public function test_send_text_error_throws_exception(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => '(#132001) Template name does not exist',
                    'type' => 'OAuthException',
                    'code' => 132001,
                    'fbtrace_id' => 'A12345'
                ]
            ], 400)
        ]);

        $service = new WhatsAppServiceBusinessApi();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Meta API Error: (#132001) Template name does not exist (Code: 132001).');

        $service->sendText('5547992010169', 'template_name', 'pt_BR', []);
    }
}
