<?php

namespace App\Models;

use App\Enums\TranslationProviderType;
use App\Models\Concerns\HasCredentialFields;
use Database\Factories\TranslationProviderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $slug 供应商唯一标识，按名称生成
 * @property string $name 展示名称，用于区分同协议的多个供应商
 * @property TranslationProviderType $protocol 翻译协议标识，决定凭据字段与驱动实现
 * @property string|null $icon 图标标识或 URL
 * @property array<string, mixed>|null $credentials 加密存储的供应商凭据
 * @property list<array<string, mixed>> $credential_fields 凭据表单字段定义
 * @property array<string, mixed>|null $options 供应商非敏感运行参数
 * @property bool $is_active 是否启用：仅启用且凭据完整的供应商进入运行时轮询池
 * @property mixed $use_factory
 *
 * @method static \Database\Factories\TranslationProviderFactory<self> factory($count = null, $state = [])
 */
class TranslationProvider extends Model
{
    /**
     * 保存翻译供应商协议、凭据和运行参数。
     */

    /** @use HasFactory<TranslationProviderFactory> */
    use HasCredentialFields, HasFactory, HasUlids;

    protected $table = 'translation_providers';

    protected $guarded = [];

    /**
     * 返回翻译供应商字段的类型转换配置；credentials 用 encrypted:array 保证落库为密文。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'protocol' => TranslationProviderType::class,
            'credentials' => 'encrypted:array',
            'credential_fields' => 'array',
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
