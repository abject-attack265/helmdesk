<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Data\SimplePaginationData;
use App\Data\Translation\ShowTranslationProviderListPagePropsData;
use App\Data\Translation\TranslationProviderData;
use App\Models\TranslationProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示翻译供应商列表。 */
class ShowTranslationProviderListAction
{
    use AsAction;

    /**
     * 分页加载翻译供应商展示数据。
     */
    public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowTranslationProviderListPagePropsData
    {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        $paginator = TranslationProvider::query()
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        $providers = $paginator->getCollection()
            ->map(fn (TranslationProvider $provider) => TranslationProviderData::fromModel($provider))
            ->all();

        return new ShowTranslationProviderListPagePropsData(
            provider_list: $providers,
            provider_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /**
     * 返回翻译供应商列表页。
     */
    public function asController(Request $request): Response
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', SimplePaginationData::DEFAULT_PER_PAGE);

        return Inertia::render('appSettings/translationProvider/List', $this->handle($page, $perPage)->toArray());
    }
}
