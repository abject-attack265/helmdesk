<?php

namespace App\Actions\Manage;

use App\Data\System\FormUpdateSystemSettingsData;
use App\Enums\AttachmentPurpose;
use App\Models\User;
use App\Services\Storage\AttachmentBindingService;
use App\Settings\GeneralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新后台设置里的系统基础资料。
 */
class UpdateSystemSettingsAction
{
    use AsAction;

    /**
     * 注入附件绑定服务。
     */
    public function __construct(
        private readonly AttachmentBindingService $attachments,
    ) {}

    /**
     * 更新当前系统基础资料。
     */
    public function handle(User $actor, FormUpdateSystemSettingsData $data): void
    {
        $settings = app(GeneralSettings::class);
        $originalLogoId = filled($settings->logo_id) ? (string) $settings->logo_id : null;

        $this->attachments->assertSettingsAttachmentAssignable(
            attachmentId: $data->logo_id,
            currentAttachmentId: $originalLogoId,
            actor: $actor,
            allowedPurposes: [AttachmentPurpose::Avatar],
            messageKey: 'channel.messages.invalid_attachment',
        );
        $settings->fill($data->toArray())->save();
        $this->attachments->syncSettingsAttachment($data->logo_id, $originalLogoId, $actor);
    }

    /**
     * 接收当前系统编辑表单并返回系统首页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $data = FormUpdateSystemSettingsData::from($request);
        $this->handle($request->user(), $data);

        return redirect()->route('app.manage.system.settings.show');
    }
}
