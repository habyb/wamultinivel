<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InviteRedirectController;

Route::redirect('/', '/admin');

Route::get('/{codigo}', [InviteRedirectController::class, 'handle']);

Route::get('/teste-whatsapp/api', function () {
    $number = '50760215163'; // Substitua por um número de teste
    $user = ['name' => 'Habyb Fernandes'];

    $param_type_header = [
        'type' => 'header',
        'parameters' => [
            ['type' => 'video', 'video' => [
                'link' => 'https://convite.andrecorrea.com.br/storage/messages/sample-mp4-file-small.mp4'
            ]]
        ],
    ];

    try {
        $response = app(\App\Services\WhatsAppServiceBusinessApi::class)->sendText(
            phone: $number,
            template: 'teste_gabinete',
            language: 'pt_BR',
            params: [
                $param_type_header,
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'parameter_name' => 'name', 'text' => $user['name']]
                    ],
                ]
            ]
        );

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Mensagem enviada com sucesso!',
            'resposta_api' => $response,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'erro',
            'mensagem' => 'Falha ao enviar mensagem',
            'erro' => $e->getMessage(),
        ], 500);
    }
});
