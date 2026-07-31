<?php

namespace App\Actions\Integration;

use App\Contracts\Integration\ProvidesContactPanel;
use App\Data\CurrentUserContextData;
use App\Data\Integration\Panel\ContactPanelData;
use App\Models\Contact;
use App\Models\Integration;
use App\Services\Integration\IntegrationProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 组装联系人在所有可用集成中的业务数据面板。
 *
 * 支持联系人面板的集成会异步提供数据，返回空面板的集成会被跳过。
 */
class BuildContactIntegrationPanelsAction
{
    use AsAction;

    /**
     * 创建联系人集成面板组装动作。
     */
    public function __construct(
        private readonly IntegrationProviderRegistry $registry,
    ) {}

    /**
     * 收集所有集成提供的联系人面板。
     *
     * @return list<ContactPanelData>
     */
    public function handle(Contact $contact): array
    {
        $panels = [];

        foreach (Integration::query()->orderBy('sort_order')->get() as $integration) {
            $provider = $this->registry->for($integration->provider);
            if (! $provider instanceof ProvidesContactPanel) {
                continue;
            }

            $panel = $provider->buildContactPanel($integration, $contact);
            if ($panel !== null) {
                $panels[] = $panel;
            }
        }

        return $panels;
    }

    /**
     * 加载联系人并返回集成面板。
     */
    public function asController(Request $request, string $contactId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $contact = Contact::query()
            ->with('identities')
            ->findOrFail($contactId);

        return response()->json(['panels' => $this->handle($contact)]);
    }
}
