/**
 * 文件说明：附件上传组合式函数。
 * 三步直传：presign 换签名 → 浏览器把文件直接 PUT 到存储（字节不过应用）→ finalize 校验落库。
 */
import attachments from '@/routes/attachments';
import visitorAttachments from '@/routes/visitor/attachments';
import type {
  AttachmentPurpose,
  AttachmentUploadTicketData,
  UploadedAttachmentData,
} from '@/types/generated';
import axios from 'axios';

export type { AttachmentPurpose, UploadedAttachmentData };

// 直传对象存储用的独立 axios 实例：不带应用 Cookie / XSRF，避免与 S3 的 CORS（Allow-Origin *）冲突。
const objectStorageClient = axios.create({ withCredentials: false });

export interface AttachmentUploadOptions {
  purpose: AttachmentPurpose;
  scope?: 'authenticated' | 'visitor';
  context?: Record<string, unknown>;
  // 访客会话 token：visitor 作用域下作为 X-Helmdesk-Visitor-Token 发往 HelmDesk presign/finalize 端点。
  visitorToken?: string;
  // 签名访客身份：visitor 作用域下用于校验附件申请归属的会话。
  visitorUserToken?: string | null;
  onProgress?: (progress: number) => void;
  signal?: AbortSignal;
}

type Translate = (
  key: string,
  params?: Record<string, string | number>,
) => string;

export function resolveAttachmentUploadError(
  error: unknown,
  t: Translate,
  fallbackKey = '上传失败，请重试',
): string {
  if (axios.isAxiosError(error)) {
    const message = error.response?.data?.message;
    if (typeof message === 'string' && message !== '') {
      return message;
    }
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return t(fallbackKey);
}

export function useAttachmentUploader() {
  async function upload(
    file: File,
    options: AttachmentUploadOptions,
  ): Promise<UploadedAttachmentData> {
    options.onProgress?.(0);

    const routes =
      options.scope === 'visitor' ? visitorAttachments : attachments;
    const contentType = file.type || 'application/octet-stream';

    // 1. 申请直传签名（声明文件信息 + 业务上下文）。
    const presign = await axios.post<AttachmentUploadTicketData>(
      routes.presign.url(),
      {
        purpose: options.purpose,
        file_name: file.name,
        mime_type: contentType,
        byte_size: file.size,
        context: options.context ?? {},
      },
      {
        headers: visitorTokenHeaders(options),
        withCredentials: options.scope !== 'visitor',
        signal: options.signal,
      },
    );
    const ticket = presign.data;

    // 2. 浏览器直传到对象存储，带进度（占 0~95%）。
    await objectStorageClient.put(ticket.upload_url, file, {
      headers: {
        ...sanitizeUploadHeaders(
          ticket.upload_headers as Record<string, string>,
        ),
        'Content-Type': contentType,
      },
      signal: options.signal,
      onUploadProgress: (event) => {
        if (!event.total) return;
        options.onProgress?.(Math.round((event.loaded / event.total) * 95));
      },
    });

    // 3. finalize：应用校验直传对象并落库，返回可绑定附件。
    const finalized = await axios.post<{ attachment: UploadedAttachmentData }>(
      routes.finalize.url(ticket.attachment_id),
      {},
      {
        headers: visitorTokenHeaders(options),
        withCredentials: options.scope !== 'visitor',
        signal: options.signal,
      },
    );

    options.onProgress?.(100);

    return finalized.data.attachment;
  }

  return { upload };
}

const browserManagedHeaders = new Set([
  'accept-charset',
  'accept-encoding',
  'access-control-request-headers',
  'access-control-request-method',
  'connection',
  'content-length',
  'cookie',
  'cookie2',
  'date',
  'dnt',
  'expect',
  'host',
  'keep-alive',
  'origin',
  'referer',
  'te',
  'trailer',
  'transfer-encoding',
  'upgrade',
  'via',
]);

export function sanitizeUploadHeaders(
  headers: Record<string, string>,
): Record<string, string> {
  return Object.fromEntries(
    Object.entries(headers).filter(([name]) => {
      const normalizedName = name.toLowerCase();

      return (
        !browserManagedHeaders.has(normalizedName) &&
        !normalizedName.startsWith('proxy-') &&
        !normalizedName.startsWith('sec-')
      );
    }),
  );
}

function visitorTokenHeaders(
  options: AttachmentUploadOptions,
): Record<string, string> {
  if (options.scope !== 'visitor') {
    return {};
  }

  const headers: Record<string, string> = {};
  if (typeof options.visitorToken === 'string' && options.visitorToken !== '') {
    headers['X-Helmdesk-Visitor-Token'] = options.visitorToken;
  }
  if (
    typeof options.visitorUserToken === 'string' &&
    options.visitorUserToken !== ''
  ) {
    headers.Authorization = `Bearer ${options.visitorUserToken}`;
  }

  return headers;
}
