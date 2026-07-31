<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

enum UserPermission: string implements LabeledEnum
{
    case UsersManage = 'users.manage';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersEdit = 'users.edit';
    case UsersDelete = 'users.delete';

    case ContactsManage = 'contacts.manage';
    case ContactsView = 'contacts.view';
    case ContactsEdit = 'contacts.edit';
    case ContactsDelete = 'contacts.delete';

    case ConversationsView = 'conversations.view';

    case TagsManage = 'tags.manage';
    case TagsView = 'tags.view';
    case TagsEdit = 'tags.edit';
    case TagsDelete = 'tags.delete';

    case AttributesManage = 'attributes.manage';
    case AttributesView = 'attributes.view';
    case AttributesEdit = 'attributes.edit';
    case AttributesDelete = 'attributes.delete';

    case CannedRepliesManage = 'canned_replies.manage';
    case CannedRepliesView = 'canned_replies.view';
    case CannedRepliesEdit = 'canned_replies.edit';
    case CannedRepliesDelete = 'canned_replies.delete';

    case KnowledgeBasesManage = 'knowledge_bases.manage';
    case KnowledgeBasesView = 'knowledge_bases.view';
    case KnowledgeBasesCreate = 'knowledge_bases.create';
    case KnowledgeBasesEdit = 'knowledge_bases.edit';
    case KnowledgeBasesDelete = 'knowledge_bases.delete';

    case ReceptionPlansManage = 'reception_plans.manage';
    case ReceptionPlansView = 'reception_plans.view';
    case ReceptionPlansCreate = 'reception_plans.create';
    case ReceptionPlansEdit = 'reception_plans.edit';
    case ReceptionPlansDelete = 'reception_plans.delete';

    case ChannelsManage = 'channels.manage';
    case ChannelsView = 'channels.view';
    case ChannelsCreate = 'channels.create';
    case ChannelsEdit = 'channels.edit';
    case ChannelsDelete = 'channels.delete';

    case SystemSettingsManage = 'system_settings.manage';
    case SystemSettingsView = 'system_settings.view';
    case SystemSettingsEdit = 'system_settings.edit';

    public function label(): string
    {
        return __("permissions.items.{$this->value}");
    }

    public function group(): string
    {
        return str($this->value)->before('.')->toString();
    }

    public function groupLabel(): string
    {
        return __("permissions.groups.{$this->group()}");
    }

    public function managePermission(): self
    {
        return self::tryFrom($this->group().'.manage') ?? $this;
    }

    /**
     * @return array<string, list<self>>
     */
    public static function groupedCases(): array
    {
        $groups = [];

        foreach (self::cases() as $permission) {
            $groups[$permission->group()][] = $permission;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }
}
