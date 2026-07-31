<?php

namespace App\Actions\Contact;

use App\Data\Contact\ShowContactTrashPagePropsData;
use App\Data\Contact\TrashContactItemData;
use App\Data\CurrentUserContextData;
use App\Data\EnumOptionData;
use App\Data\SimplePaginationData;
use App\Enums\ContactListType;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询已删除联系人列表。
 */
class GetContactTrashListAction
{
    use AsAction;

    /**
     * 查询应用联系人回收站列表页 props。
     */
    public function handle(
        int $page = 1,
        int $perPage = SimplePaginationData::DEFAULT_PER_PAGE,
    ): ShowContactTrashPagePropsData {
        $perPage = max(1, min($perPage, 50));
        $page = max(1, $page);

        $paginator = Contact::onlyTrashed()

            ->orderByDesc('deleted_at')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $contacts = $paginator->getCollection()
            ->map(fn (Contact $contact) => TrashContactItemData::fromModel($contact))
            ->all();

        return new ShowContactTrashPagePropsData(
            contact_trash_list: $contacts,
            contact_trash_list_pagination: SimplePaginationData::fromPaginator($paginator),
            contact_list_type_options: EnumOptionData::fromCases(ContactListType::cases()),
        );
    }

    /**
     * 返回应用联系人回收站页面。
     */
    public function asController(Request $request): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', SimplePaginationData::DEFAULT_PER_PAGE);

        return Inertia::render(
            'contacts/Trash',
            $this->handle($page, $perPage)->toArray(),
        );
    }
}
