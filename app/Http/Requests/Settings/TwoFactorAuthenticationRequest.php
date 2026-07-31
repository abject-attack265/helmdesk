<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Features;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * 仅当系统启用了两步验证功能时才允许该请求。
     */
    public function authorize(): bool
    {
        return Features::enabled(Features::twoFactorAuthentication());
    }

    /**
     * 该请求无需校验入参，返回空规则集。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
