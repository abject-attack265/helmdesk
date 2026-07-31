<?php

namespace Database\Factories;

use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContactIdentity>
 */
class ContactIdentityFactory extends Factory
{
    protected $model = ContactIdentity::class;

    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'contact_id' => Contact::factory(),
            'type' => IdentityType::Email,
            'namespace' => '',
            'value' => $email,
            'display_value' => $email,
        ];
    }

    public function email(?string $value = null): static
    {
        return $this->state(function () use ($value) {
            $email = $value ?? fake()->unique()->safeEmail();

            return [
                'type' => IdentityType::Email,
                'namespace' => '',
                'value' => strtolower($email),
                'display_value' => strtolower($email),
            ];
        });
    }

    public function phone(?string $value = null): static
    {
        return $this->state(function () use ($value) {
            $phone = $value ?? '+86'.fake()->numerify('1##########');

            return [
                'type' => IdentityType::Phone,
                'namespace' => '',
                'value' => $phone,
                'display_value' => $phone,
            ];
        });
    }

    public function session(?string $value = null): static
    {
        return $this->state(function () use ($value) {
            $sessionId = $value ?? Str::random(32);

            return [
                'type' => IdentityType::Session,
                'namespace' => '',
                'value' => $sessionId,
                'display_value' => 'sess:'.substr($sessionId, 0, 8),
            ];
        });
    }

    public function externalId(?string $value = null, string $namespace = 'api:default'): static
    {
        return $this->state(function () use ($value, $namespace) {
            $externalId = $value ?? (string) fake()->unique()->randomNumber(8);

            return [
                'type' => IdentityType::ExternalId,
                'namespace' => $namespace,
                'value' => $externalId,
                'display_value' => $externalId,
            ];
        });
    }

    /**
     * 渠道账号身份（如 Telegram 用户 ID），namespace 形如 "telegram:{code}"。
     */
    public function channelAccount(?string $value = null, string $namespace = 'telegram:default'): static
    {
        return $this->state(function () use ($value, $namespace) {
            $account = $value ?? (string) fake()->unique()->randomNumber(8);

            return [
                'type' => IdentityType::ChannelAccount,
                'namespace' => $namespace,
                'value' => $account,
                'display_value' => $account,
            ];
        });
    }
}
