<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Models\TranslationProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除翻译供应商并使其退出运行时轮询池。
 */
class DeleteTranslationProviderAction
{
    use AsAction;

    /**
     * 删除指定翻译供应商。
     */
    public function handle(string $id): void
    {
        TranslationProvider::query()->findOrFail($id)->delete();
    }

    /**
     * 处理删除请求并返回供应商列表页。
     */
    public function asController(Request $request, string $id): RedirectResponse
    {
        $this->handle($id);

        return redirect()->route('app.manage.translation-providers.index');
    }
}
