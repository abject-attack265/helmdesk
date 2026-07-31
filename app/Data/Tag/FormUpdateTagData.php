<?php

namespace App\Data\Tag;

/**
 * 更新标签表单数据。
 * 字段与 FormCreateTagData 完全一致，名称唯一性在 UpdateTagAction 内基于 normalized_name 自行校验。
 */
class FormUpdateTagData extends FormCreateTagData {}
