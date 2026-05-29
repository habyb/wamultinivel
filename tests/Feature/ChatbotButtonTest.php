<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class ChatbotButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_chatbot_responds_to_button_click_sim(): void
    {
        // 1. Mock WhatsApp service so it doesn't make external HTTP calls
        $this->mock(WhatsAppServiceBusinessApi::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendFreeText')
                ->once()
                ->with('5524999999999', Mockery::on(function ($text) {
                    return str_contains($text, 'Muito obrigado por sua resposta') && str_contains($text, 'Habyb');
                }))
                ->andReturn([]);
        });

        // 2. Create the required 'Membro' role first
        \Spatie\Permission\Models\Role::create(['name' => 'Membro']);

        // 3. Create a user who completed registration
        $user = User::factory()->create([
            'name' => 'Habyb Fernandes',
            'remoteJid' => '5524999999999',
            'is_add_date_of_birth' => true,
        ]);

        // 3. Make request mimicking template button click (type: button)
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    [
                                        'wa_id' => '5524999999999',
                                        'profile' => ['name' => 'Habyb Fernandes']
                                    ]
                                ],
                                'messages' => [
                                    [
                                        'from' => '5524999999999',
                                        'id' => 'wamid.HBgLNTY1MDU1NTAxMjMVAgIGGBIyRjY2NzhBNEYyNDU5MUI4OTA1',
                                        'timestamp' => '1663085526',
                                        'type' => 'button',
                                        'button' => [
                                            'text' => 'SIM',
                                            'payload' => 'SIM'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/chat/process', $payload);

        $response->assertStatus(200);
    }

    public function test_chatbot_responds_to_button_click_nao(): void
    {
        // 1. Mock WhatsApp service so it doesn't make external HTTP calls
        $this->mock(WhatsAppServiceBusinessApi::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendFreeText')
                ->once()
                ->with('5524999999999', Mockery::on(function ($text) {
                    return str_contains($text, 'Muito obrigado por sua resposta') && str_contains($text, 'Habyb');
                }))
                ->andReturn([]);
        });

        // 2. Create the required 'Membro' role first
        \Spatie\Permission\Models\Role::create(['name' => 'Membro']);

        // 3. Create a user who completed registration
        $user = User::factory()->create([
            'name' => 'Habyb Fernandes',
            'remoteJid' => '5524999999999',
            'is_add_date_of_birth' => true,
        ]);

        // 3. Make request mimicking template button click (type: button)
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'contacts' => [
                                    [
                                        'wa_id' => '5524999999999',
                                        'profile' => ['name' => 'Habyb Fernandes']
                                    ]
                                ],
                                'messages' => [
                                    [
                                        'from' => '5524999999999',
                                        'id' => 'wamid.HBgLNTY1MDU1NTAxMjMVAgIGGBIyRjY2NzhBNEYyNDU5MUI4OTA1',
                                        'timestamp' => '1663085526',
                                        'type' => 'button',
                                        'button' => [
                                            'text' => 'NÃO',
                                            'payload' => 'NÃO'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/chat/process', $payload);

        $response->assertStatus(200);
    }
}
