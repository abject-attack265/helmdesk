<?php

namespace App\Actions\Attachment;

use App\Models\Attachment;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowAttachmentContentAction
{
    use AsAction;

    public function handle(Attachment $attachment): StreamedResponse
    {
        return $attachment->filesystem()->response(
            $attachment->object_key,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => Attachment::dispositionFor(
                    $attachment->mime_type,
                    $attachment->original_name,
                ),
                'Cache-Control' => Attachment::CACHE_CONTROL,
            ],
        );
    }

    public function asController(Attachment $attachment): StreamedResponse
    {
        return $this->handle($attachment);
    }
}
