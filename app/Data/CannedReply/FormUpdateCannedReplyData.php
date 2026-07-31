<?php

namespace App\Data\CannedReply;

/**
 * 更新快捷回复模版的表单数据。
 * 由 resources/js/pages/cannedReplies/Index.vue 的编辑模态框提交，UpdateCannedReplyAction 在 handle() 中应用。
 * 允许在编辑期间切换归属（个人 <-> 应用共享）。
 */
class FormUpdateCannedReplyData extends FormCreateCannedReplyData {}
