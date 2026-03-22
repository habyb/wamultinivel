<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Support\Facades\Log;

class TestWhatsApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-whatsapp {user_id} {template_name=parabens}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends a test WhatsApp template message to a user.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppServiceBusinessApi $whatsAppService)
    {
        $userId = $this->argument('user_id');
        $template = $this->argument('template_name');

        $this->info("Looking for User with ID: {$userId}");
        $user = User::find($userId);

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }
        $this->info("User found: {$user->name}");

        if (!$user->remoteJid) {
            $this->error("User does not have a WhatsApp number (remoteJid).");
            return 1;
        }

        // A função fix_whatsapp_number é definida em app/Support/helpers.php
        if (function_exists('fix_whatsapp_number')) {
            $number = fix_whatsapp_number($user->remoteJid);
            $this->info("Original number: {$user->remoteJid}, Fixed number: {$number}");
        } else {
            $this->warn("Helper function 'fix_whatsapp_number' not found. Using remoteJid as is.");
            $number = $user->remoteJid;
        }

        $params = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', "parameter_name" => "name", 'text' => $user->name]
                ],
            ]
        ];

        $this->info("Sending template '{$template}' to {$number}...");
        $this->info("With params: " . json_encode($params, JSON_PRETTY_PRINT));

        try {
            $response = $whatsAppService->sendText(
                phone: $number,
                template: $template,
                language: 'pt_BR',
                params: $params
            );

            $this->info('Message sent!');
            $this->info('API Response:');
            $this->line(json_encode($response, JSON_PRETTY_PRINT));

        } catch (\Exception $e) {
            $this->error('An exception occurred:');
            $this->error($e->getMessage());
            Log::error('TestWhatsApp command failed', [
                'user_id' => $userId,
                'exception' => $e
            ]);
            return 1;
        }

        return 0;
    }
}
