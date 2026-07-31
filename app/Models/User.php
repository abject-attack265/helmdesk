<?php

namespace App\Models;

use App\Data\User\UserNotificationPreferencesData;
use App\Enums\UserPermission;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Services\LocalePreference;
use App\Settings\GeneralSettings;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property string $locale
 * @property string|null $timezone
 * @property \App\Data\User\UserNotificationPreferencesData:default|null $notification_preferences
 * @property array|null $permissions
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $deleted_at
 * @property mixed $use_factory
 * @property int|null $assigned_conversations_count
 * @property int|null $avatar_attachments_count
 * @property-read Membership|null $membership
 * @property-read Collection|Conversation[] $assignedConversations
 * @property-read Attachment|null $avatarAttachment
 *
 * @method static \Database\Factories\UserFactory<self> factory($count = null, $state = [])
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /**
     * 用户模型，保存后台账号和成员关系。
     */

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    use SoftDeletes;

    protected $table = 'users';

    /** @var list<string> 可批量写入的用户字段。 */
    protected $fillable = [
        'id',
        'name',
        'email',
        'password',
        'avatar',
        'locale',
        'timezone',
        'notification_preferences',
        'permissions',
    ];

    /** @var list<string> 序列化用户时隐藏的敏感字段。 */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_preferences' => UserNotificationPreferencesData::class.':default',
            'permissions' => 'array',
            'two_factor_confirmed_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * 查看者时区，用于把 UTC 存储的时间按其本地日历日聚合与筛选；缺失或非法回退到应用时区。
     */
    public function resolvedTimezone(): string
    {
        $timezone = (string) $this->timezone;

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return (string) (config('app.timezone') ?: 'UTC');
        }

        return $timezone;
    }

    /**
     * 单一系统中的成员资料。
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'user_id', 'id');
    }

    /**
     * 当前分配给用户处理的会话。
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /**
     * 用户头像附件。
     */
    public function avatarAttachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('purpose', 'avatar')
            ->latestOfMany();
    }

    /**
     * 返回用户通知偏好。
     */
    public function notificationPreferences(): UserNotificationPreferencesData
    {
        return $this->notification_preferences;
    }

    public function hasPermission(UserPermission|string $permission): bool
    {
        $settings = app(GeneralSettings::class);

        if (filled($settings->owner_id) && (string) $settings->owner_id === (string) $this->id) {
            return true;
        }

        $permissionValue = $permission instanceof UserPermission ? $permission->value : $permission;
        $permissions = array_values(array_filter(array_map('strval', $this->permissions ?? [])));

        if (in_array($permissionValue, $permissions, true)) {
            return true;
        }

        $permissionEnum = $permission instanceof UserPermission
            ? $permission
            : UserPermission::tryFrom($permissionValue);

        return $permissionEnum instanceof UserPermission
            && in_array($permissionEnum->managePermission()->value, $permissions, true);
    }

    /**
     * @param  list<UserPermission|string>  $permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 返回通知和邮件使用的 Laravel 语言标识。
     */
    public function preferredLocale(): string
    {
        return LocalePreference::normalizeLaravel($this->locale);
    }

    /**
     * 发送邮箱验证通知。
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new QueuedVerifyEmail);
    }

    /**
     * 发送密码重置通知。
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
