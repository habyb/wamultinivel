<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Models\Setting;

class InviteRedirectController extends Controller
{
    /**
     * Handle the convite redirection.
     *
     * @param string $codigo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handle(string $codigo): RedirectResponse
    {
        // phone number
        $invite = Setting::where('name', 'whatsapp_number')->firstOrFail();
        $phone = $invite->payload['value'];
        $text = "Quero me cadastrar no Time do Dep. André Corrêa. ID: {$codigo}";

        $whatsAppUrl = "https://wa.me/+{$phone}?text=" .
            rawurlencode($text);

        return redirect()->away($whatsAppUrl);
    }
}
