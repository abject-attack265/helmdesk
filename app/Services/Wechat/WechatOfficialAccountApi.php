<?php

namespace App\Services\Wechat;

use App\Exceptions\WechatApiException;
use App\Models\Attachment;
use App\Models\Channel;
use EasyWeChat\Kernel\Form\Form;
use Symfony\Component\Mime\Part\DataPart;
use Throwable;

/** 调用微信公众号客服消息 API。 */
class WechatOfficialAccountApi
{
    /** 微信公众号客服文本消息的最大 UTF-8 字节数。 */
    public const int MAX_TEXT_BYTES = 2048;

    /** 微信公众号临时图片允许的最大字节数。 */
    public const int MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** 创建微信公众号客服消息客户端。 */
    public function __construct(
        private readonly WechatOfficialAccountApplicationFactory $applications,
    ) {}

    /** 发送单个不超过平台字节上限的客服文本消息。 */
    public function sendText(Channel $channel, string $openid, string $content): void
    {
        if (strlen($content) > self::MAX_TEXT_BYTES) {
            throw new WechatApiException('微信公众号客服文本消息超过 2048 字节限制。');
        }

        try {
            $response = $this->applications->make($channel)->getClient()->postJson(
                '/cgi-bin/message/custom/send',
                [
                    'touser' => $openid,
                    'msgtype' => 'text',
                    'text' => ['content' => $content],
                ],
            );
            $result = $response->toArray(false);
        } catch (WechatApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new WechatApiException(
                '微信公众号客服消息发送请求失败。',
                errorCode: 0,
                previous: $e,
                retryable: true,
            );
        }

        $errorCode = (int) ($result['errcode'] ?? 0);
        if ($errorCode !== 0) {
            throw WechatApiException::fromResult($result, '微信公众号客服消息发送失败。');
        }
    }

    /**
     * 下载访客发送的微信临时图片素材。
     *
     * @return array{contents: string, mime_type: string, file_name: string}
     */
    public function downloadImage(Channel $channel, string $mediaId): array
    {
        try {
            $response = $this->applications->make($channel)->getClient()->get(
                '/cgi-bin/media/get',
                ['query' => ['media_id' => $mediaId]],
            );
            $contents = $response->getContent(false);
            $headers = $response->getHeaders(false);
        } catch (Throwable $e) {
            throw new WechatApiException(
                '微信公众号临时图片下载请求失败。',
                errorCode: 0,
                previous: $e,
                retryable: true,
            );
        }

        $contentType = strtolower(trim(explode(';', $headers['content-type'][0] ?? '')[0]));
        if (str_contains($contentType, 'json') || str_starts_with(ltrim($contents), '{')) {
            $result = json_decode($contents, true);
            if (is_array($result)) {
                throw WechatApiException::fromResult($result, '微信公众号临时图片下载失败。');
            }
        }

        if (! in_array($contentType, Attachment::INLINE_IMAGE_MIME_TYPES, true)) {
            throw new WechatApiException('微信公众号返回了不支持的图片类型。');
        }

        if ($contents === '' || strlen($contents) > self::MAX_IMAGE_BYTES) {
            throw new WechatApiException('微信公众号图片为空或超过 10 MB 限制。');
        }

        $extension = match ($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        };

        return [
            'contents' => $contents,
            'mime_type' => $contentType,
            'file_name' => 'wechat-'.$mediaId.'.'.$extension,
        ];
    }

    /** 上传并发送一张微信公众号客服图片。 */
    public function sendImage(Channel $channel, string $openid, string $contents, string $fileName): string
    {
        try {
            $multipart = Form::create([
                'media' => new DataPart($contents, $fileName),
            ])->toOptions();
            $response = $this->applications->make($channel)->getClient()->post(
                '/cgi-bin/media/upload',
                [
                    'query' => ['type' => 'image'],
                    ...$multipart,
                ],
            );
            $upload = $response->toArray(false);
        } catch (Throwable $e) {
            throw new WechatApiException(
                '微信公众号客服图片上传请求失败。',
                errorCode: 0,
                previous: $e,
                retryable: true,
            );
        }

        if ((int) ($upload['errcode'] ?? 0) !== 0) {
            throw WechatApiException::fromResult($upload, '微信公众号客服图片上传失败。');
        }
        $mediaId = is_string($upload['media_id'] ?? null) ? $upload['media_id'] : '';
        if ($mediaId === '') {
            throw new WechatApiException('微信公众号客服图片上传未返回 MediaId。');
        }

        try {
            $response = $this->applications->make($channel)->getClient()->postJson(
                '/cgi-bin/message/custom/send',
                [
                    'touser' => $openid,
                    'msgtype' => 'image',
                    'image' => ['media_id' => $mediaId],
                ],
            );
            $sent = $response->toArray(false);
        } catch (Throwable $e) {
            throw new WechatApiException(
                '微信公众号客服图片发送请求失败。',
                errorCode: 0,
                previous: $e,
                retryable: true,
            );
        }

        if ((int) ($sent['errcode'] ?? 0) !== 0) {
            throw WechatApiException::fromResult($sent, '微信公众号客服图片发送失败。');
        }

        return $mediaId;
    }

    /**
     * 按微信公众号字节上限拆分 UTF-8 文本。
     *
     * @return list<string>
     */
    public static function splitText(string $content): array
    {
        $chunks = [];
        while ($content !== '') {
            $chunk = mb_strcut($content, 0, self::MAX_TEXT_BYTES, 'UTF-8');
            $chunks[] = $chunk;
            $content = substr($content, strlen($chunk));
        }

        return $chunks;
    }
}
