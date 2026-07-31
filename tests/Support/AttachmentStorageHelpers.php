<?php

use Illuminate\Support\Facades\Storage;

/**
 * 为附件测试准备独立的本地文件系统。
 */
function fakeAttachmentStorage(): void
{
    Storage::fake('local');
}
