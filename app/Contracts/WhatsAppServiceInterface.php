<?php

namespace App\Contracts;

interface WhatsAppServiceInterface
{
    /**
     * Sends a free text message (not a template).
     */
    public function sendFreeText(string $phone, string $text);

    /**
     * Sends a message with interactive buttons.
     */
    public function sendInteractiveButtons(string $phone, string $bodyText, array $buttons);

    /**
     * Sends a list message (interactive dropdown).
     */
    public function sendListMessage(
        string $phone,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null
    );
}
