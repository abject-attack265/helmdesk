<?php

namespace App\Data\Tag;

use App\Enums\TagScope;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 创建标签组表单数据。
 * 提交来源：标签管理页 resources/js/pages/tags/Index.vue 的「新建标签组」对话框。
 * scope 决定该组及组内标签作用于会话还是联系人，创建后不可更改。
 */
class FormCreateTagGroupData extends Data
{
    public function __construct(
        public string $name,
        public TagScope $scope,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $scopes = array_map(static fn (TagScope $s) => $s->value, TagScope::cases());

        return [
            'name' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'scope' => ['required', Rule::in($scopes)],
        ];
    }
}
