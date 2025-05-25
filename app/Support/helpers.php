<?php

if (!function_exists('generate_custom_alphanumeric_password')) {
    /**
     * Generate a custom secure alphanumeric password.
     *
     * Options:
     * - Include/exclude lowercase letters
     * - Include/exclude uppercase letters
     * - Include/exclude digits
     * - Exclude similar characters for better readability
     *
     * @param int $length
     * @param bool $includeLowercase
     * @param bool $includeUppercase
     * @param bool $includeDigits
     * @param bool $excludeSimilar
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    function generate_custom_alphanumeric_password(
        int $length = 12,
        bool $includeLowercase = true,
        bool $includeUppercase = true,
        bool $includeDigits = true,
        bool $excludeSimilar = false
    ): string {
        if ($length < 1) {
            throw new \InvalidArgumentException('Password length must be at least 1 character.');
        }

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';

        $similarCharacters = ['0', 'O', 'I', 'l', '1'];

        $pool = '';

        if ($includeLowercase) {
            $pool .= $lowercase;
        }
        if ($includeUppercase) {
            $pool .= $uppercase;
        }
        if ($includeDigits) {
            $pool .= $digits;
        }

        if ($pool === '') {
            throw new \InvalidArgumentException('At least one character set must be included.');
        }

        // Remove similar characters if needed
        if ($excludeSimilar) {
            $pool = str_replace($similarCharacters, '', $pool);
        }

        $password = '';

        $maxIndex = strlen($pool) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $pool[random_int(0, $maxIndex)];
        }

        return $password;
    }
}

if (!function_exists('fix_whatsapp_number')) {
    /**
     * Fix WhatsApp Number
     *
     * @param string $remoteJid
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    function fix_whatsapp_number(string $remoteJid): string
    {
        // Remove the sufix "@s.whatsapp.net"
        $number = explode('@', $remoteJid)[0];

        // If is not Brazil number, it retorns without to change
        if (!str_starts_with($number, '55')) {
            return $number;
        }

        $ddd = substr($number, 2, 2);
        $remaining_part = substr($number, 4);

        if (strlen($remaining_part) === 8) {
            $firstDigit = $remaining_part[0];
            if (in_array($firstDigit, ['6', '7', '8', '9'])) {
                $remaining_part = '9' . $remaining_part;
            }
        }

        return '55' . $ddd . $remaining_part;
    }
}

if (!function_exists('remove_ddi_whatsapp_number')) {
    /**
     * Remove DDI WhatsApp Number
     *
     * @param string $number
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    function remove_ddi_whatsapp_number(string $number): string
    {
        // Remove the sufix "@s.whatsapp.net"
        $number = explode('@', $number)[0];

        // If is Brazil number, it retorns with to change
        if (str_starts_with($number, '55')) { // 5521999888777
            return substr($number, 2);
        } else {
            return $number;
        }
    }
}

if (!function_exists('remove_third_digit')) {
    /**
     * Remove the 3rd digit from a numeric string.
     *
     * @param int|string $number The number to process.
     * @return string The number with the 3rd digit removed.
     */
    function remove_third_digit(int|string $number): string
    {
        // Ensure the number is treated as a string
        $number = (string) $number;

        // If the number has less than 3 digits, return it unchanged
        if (strlen($number) < 3) {
            return $number;
        }

        // Remove the character at index 2 (3rd digit, since index starts at 0)
        return substr($number, 0, 2) . substr($number, 3);
    }
}

if (! function_exists('format_phone_number')) {
    /**
     * Format a phone number with DDI 55 (Brazil) into standard format.
     *
     * Example:
     * Input: 5521988777555
     * Output: 55 (21) 98877-7555
     *
     * @param string $number
     * @return string
     */
    function format_phone_number(string $number): string
    {
        // Clean number: keep only digits
        $cleanNumber = preg_replace('/\D/', '', $number);

        // Check if it starts with '55' and length is valid
        if (str_starts_with($cleanNumber, '55') && strlen($cleanNumber) >= 12) {
            // Extract DDI, DDD, and phone number
            $ddi = substr($cleanNumber, 0, 2);         // 55
            $ddd = substr($cleanNumber, 2, 2);         // e.g., 21
            $phone = substr($cleanNumber, 4);          // e.g., 988777555

            // Split phone number into parts (5 + 4 digits)
            $part1 = substr($phone, 0, strlen($phone) - 4);  // 98877
            $part2 = substr($phone, -4);                     // 7555

            return sprintf('%s (%s) %s-%s', $ddi, $ddd, $part1, $part2);
        }

        // If not a Brazilian number, return the cleaned number as-is
        return $cleanNumber;
    }
}
