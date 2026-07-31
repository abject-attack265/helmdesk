<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Models\TranslationProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 更新翻译供应商的启用状态。 */
class ToggleTranslationProviderActiveAction
{
    use AsAction;

    /**
     * 保存供应商启用状态。
     */
    public function handle(string $id, bool $isActive): TranslationProvider
    {
        $provider = TranslationProvider::query()->findOrFail($id);

        $provider->is_active = $isActive;
        $provider->save();

        return $provider;
    }

    /**
     * 校验状态更新请求并返回列表页。
     */
    public function asController(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $this->handle($id, (bool) $validated['is_active']);

        return back();
    }
}
