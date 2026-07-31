<?php

namespace App\Data\Experience;

use Spatie\LaravelData\Data;

/**
 * 触发经验提炼表单 Data。
 * 提交来源：resources/js/pages/experiences/Create.vue 的联系人选择区（管理员在时间窗口内筛选后勾选的联系人，可跨页累积）。
 *
 * 提炼单位是「联系人在窗口内的全部已关闭会话」，所以窗口本身也随表单提交：
 * 服务端据此展开出会话集合，保证页面看到的与实际送去提炼的是同一批。
 */
class FormStartExperienceExtractionData extends Data
{
    /** 单次运行允许送入的会话数上限（控制 LLM 输入规模）；勾选的联系人展开后按会话总数计。 */
    public const int MAX_CONVERSATIONS = 200;

    /**
     * @param  list<string>  $contact_ids
     * @param  string  $from  窗口起始日期（Y-m-d）
     * @param  string  $to  窗口截止日期（Y-m-d）
     */
    public function __construct(
        public array $contact_ids,
        public string $from,
        public string $to,
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            // 一个联系人至少带一条会话，故这里用会话数上限给数组兜底；真实上限在展开成会话后校验。
            'contact_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_CONVERSATIONS],
            'contact_ids.*' => ['required', 'string', 'ulid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
