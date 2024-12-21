<?php

namespace App\Services\Turbostars;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class SignatureValidator
{
    protected $partnerSecret;

    public function __construct()
    {
        $this->partnerSecret = config('services.turbostars.partner_secret');
    }

    public function validate($signature, $body)
    {
        try {
            $signParts = explode('.', $signature);

            if (count($signParts) !== 3) {
                return false;
            }

            JWT::decode(
                implode('.', [
                    $signParts[0],
                    JWT::urlsafeB64Encode($body),
                    $signParts[2],
                ]),
                new Key($this->partnerSecret, 'HS256')
            );

            return true;
        } catch (\Throwable $error) {
            return false;
        }
    }
}
