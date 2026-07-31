<?php

namespace App\Actions\AppSetting\AiModel;

use App\Models\AiModel;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除一个AI 模型，立即移出运行时按用途取用的模型池。
 */
class DeleteAiModelAction
{
    use AsAction;

    public function handle(string $modelId): void
    {
        AiModel::query()->whereKey($modelId)->delete();
    }

    public function asController(string $model): RedirectResponse
    {
        $this->handle($model);

        return back();
    }
}
