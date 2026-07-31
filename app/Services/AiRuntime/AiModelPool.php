<?php

namespace App\Services\AiRuntime;

use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use Illuminate\Support\Collection;

/**
 * 当前应用的 AI 模型用途池。
 *
 * 各业务场景按「用途」从当前应用「启用且所属供应商凭据完整」的模型里取用。一行模型对应一个用途，
 * 同用途内按 weight 加权随机排序：首位为本次选中的主模型，其余按序作为 fallback 候选。
 * weight（1-100）越大被选中为主的概率越高；大量调用下分布趋近权重比例，单次仍是随机。无状态，Octane 多 worker 安全。
 */
class AiModelPool
{
    /**
     * 返回某用途下可用的模型集合，按 weight 加权随机排序（含 provider 关联）。
     *
     * @return Collection<int, AiModel>
     */
    public function modelsForPurpose(AiModelPurpose $purpose): Collection
    {
        $models = AiModel::query()
            ->with('provider')
            ->where('purpose', $purpose->value)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(static fn (AiModel $model): bool => $model->provider !== null
                && $model->provider->hasCompleteCredentials())
            ->values();

        return $this->weightedOrder($models);
    }

    /**
     * 该用途当前是否有可用模型。
     */
    public function hasUsable(AiModelPurpose $purpose): bool
    {
        return $this->modelsForPurpose($purpose)->isNotEmpty();
    }

    /**
     * 取该用途下本次加权随机选中的模型（主模型）。
     */
    public function firstForPurpose(AiModelPurpose $purpose): ?AiModel
    {
        return $this->modelsForPurpose($purpose)->first();
    }

    /**
     * 按 weight 做加权随机排序（Efraimidis-Spirakis 无放回加权抽样）：
     * 为每个模型生成 key = u^(1/weight)（u 为 (0,1) 随机数），按 key 降序排列。
     * weight 越大 key 越接近 1、越靠前，被选为主模型的概率正比于 weight。
     *
     * @param  Collection<int, AiModel>  $models
     * @return Collection<int, AiModel>
     */
    private function weightedOrder(Collection $models): Collection
    {
        return $models
            ->sortByDesc(static function (AiModel $model): float {
                $weight = max((int) $model->weight, 1);
                $u = (mt_rand() + 1) / (mt_getrandmax() + 2);

                return $u ** (1 / $weight);
            })
            ->values();
    }
}
