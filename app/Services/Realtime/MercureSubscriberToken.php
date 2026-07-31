<?php

namespace App\Services\Realtime;

use Illuminate\Support\Carbon;

class MercureSubscriberToken
{
    public function issue(): string
    {
        $header = $this->encodeJson([
            'alg' => 'HS256',
            'typ' => 'JWT',
        ]);
        $payload = $this->encodeJson([
            'mercure' => [
                'subscribe' => [
                    MercureTopics::receptionInbox(),
                    MercureTopics::receptionConversationSelector(),
                    MercureTopics::aiChatSelector(),
                ],
            ],
            'exp' => Carbon::now()->addHours(12)->timestamp,
        ]);
        $signingInput = $header.'.'.$payload;
        $signature = hash_hmac(
            'sha256',
            $signingInput,
            (string) config('octane.mercure.subscriber_jwt'),
            true,
        );

        return $signingInput.'.'.$this->encode($signature);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return $this->encode(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
