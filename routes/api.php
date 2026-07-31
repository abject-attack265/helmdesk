<?php

use App\Actions\Attachment\FinalizeAttachmentUploadAction;
use App\Actions\Attachment\PresignAttachmentUploadAction;
use App\Actions\Reception\Visitor\NoteReceptionTypingAction;
use App\Actions\Reception\Visitor\RecallReceptionMessageAction;
use App\Actions\Reception\Visitor\SendReceptionMessageAction;
use App\Actions\Reception\Visitor\ShowReceptionStateAction;
use App\Actions\Reception\Visitor\SubmitReceptionRatingAction;
use Illuminate\Support\Facades\Route;

// 直传对象存储：presign 换签名 → 浏览器直传 S3（字节不过应用）→ finalize 校验并落库。
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('attachments/presign', PresignAttachmentUploadAction::class)
        ->name('attachments.presign');
    Route::post('attachments/{attachment}/finalize', FinalizeAttachmentUploadAction::class)
        ->whereUlid('attachment')
        ->name('attachments.finalize');
});

// 访客附件直传对象存储：申请与完成分别限流，避免上传完成请求被申请流量挤占。
Route::post('visitor/attachments/presign', PresignAttachmentUploadAction::class)
    ->middleware('throttle:visitor-attachment-presign')
    ->name('visitor.attachments.presign');
Route::post('visitor/attachments/{attachment}/finalize', FinalizeAttachmentUploadAction::class)
    ->middleware('throttle:visitor-attachment-finalize')
    ->whereUlid('attachment')
    ->name('visitor.attachments.finalize');

// 访客聊天入口（公开，无需认证）
Route::prefix('chat/{code}')->group(function () {
    Route::get('state', ShowReceptionStateAction::class)
        ->middleware('throttle:120,1')
        ->name('visitor.chat.state');
    Route::post('messages', SendReceptionMessageAction::class)
        ->middleware('throttle:60,1')
        ->name('visitor.chat.messages.send');
    Route::post('messages/{messageId}/recall', RecallReceptionMessageAction::class)
        ->middleware('throttle:60,1')
        ->name('visitor.chat.messages.recall');
    Route::post('typing', NoteReceptionTypingAction::class)
        ->middleware('throttle:120,1')
        ->name('visitor.chat.typing');
    Route::post('rating', SubmitReceptionRatingAction::class)
        ->middleware('throttle:30,1')
        ->name('visitor.chat.rating');
});
