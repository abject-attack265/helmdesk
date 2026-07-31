<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除应用 AI 供应商及其下所有模型。
 *
 * 运行时按用途从模型池取用模型，删除即从池中移除；无引用检查。
 */
class DeleteAiProviderAction
{
    use AsAction;

    /**
     * 连同模型一起删除一条 ai_providers 记录。
     */
    public function handle(string $providerId): void
    {
        DB::transaction(function () use ($providerId): void {
            $provider = AiProvider::query()->findOrFail($providerId);
            $provider->models()->delete();
            $provider->delete();
        });
    }

    /**
     * 删除后回到列表页。
     */
    public function asController(Request $request, string $provider): RedirectResponse
    {
        $this->handle($provider);

        return redirect()->route('app.manage.ai-providers.index');
    }
}
