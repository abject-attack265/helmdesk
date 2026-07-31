<?php

namespace App\Data\KnowledgeBase;

/**
 * 知识库编辑分组弹窗提交的表单 Data，承接重命名和移动到其它顶级分组的合并操作。
 * 字段与 FormCreateKnowledgeGroupData 完全一致，更新时的差异（如同名校验）由 UpdateKnowledgeGroupAction 处理。
 */
class FormUpdateKnowledgeGroupData extends FormCreateKnowledgeGroupData {}
