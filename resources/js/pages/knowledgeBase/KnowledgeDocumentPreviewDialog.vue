<!--
  知识库文档预览对话框，按文件类型加载对应渲染器。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { renderMarkdownToSafeHtml } from '@/lib/markdown';
import type { ListKnowledgeDocumentItemData } from '@/types/generated';
import DOMPurify from 'dompurify';
import { ExternalLink } from '@lucide/vue';
import type { PDFDocumentProxy } from 'pdfjs-dist';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

type PdfJsModule = typeof import('pdfjs-dist');

let pdfRendererPromise: Promise<PdfJsModule> | null = null;

async function loadPdfRenderer(): Promise<PdfJsModule> {
  if (pdfRendererPromise === null) {
    pdfRendererPromise = Promise.all([
      import('pdfjs-dist'),
      import('pdfjs-dist/build/pdf.worker.mjs?url'),
    ]).then(([pdfRenderer, workerModule]) => {
      pdfRenderer.GlobalWorkerOptions.workerSrc = workerModule.default;
      return pdfRenderer;
    });
  }

  return pdfRendererPromise;
}

const RENDERER_MAP: Record<string, string> = {
  md: 'markdown',
  markdown: 'markdown',
  txt: 'text',
  pdf: 'pdf',
  docx: 'docx',
  html: 'html',
  htm: 'html',
};

const props = defineProps<{
  open: boolean;
  knowledgeBaseId: string;
  document: ListKnowledgeDocumentItemData | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
}>();

const { t } = useI18n();

const loading = ref(false);
const loadError = ref(false);
const textContent = ref('');
const pdfContainer = ref<HTMLDivElement | null>(null);
const docxContainer = ref<HTMLDivElement | null>(null);
let requestSequence = 0;

const previewKind = computed(() => {
  if (props.document?.source_type === 'manual') {
    return 'markdown';
  }

  const ext = props.document?.extension?.toLowerCase();
  return ext ? (RENDERER_MAP[ext] ?? '') : '';
});

const streamUrl = computed(() => {
  if (!props.document) {
    return '';
  }

  return KnowledgeBase.Document.StreamKnowledgeDocumentPreviewFileAction.url({
    knowledgeBase: props.knowledgeBaseId,
    document: props.document.id,
  });
});

const markdownHtml = computed(() =>
  previewKind.value === 'markdown'
    ? renderMarkdownToSafeHtml(textContent.value)
    : '',
);

const safeHtml = computed(() =>
  previewKind.value === 'html' && DOMPurify.isSupported
    ? (DOMPurify.sanitize(textContent.value, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: ['script', 'iframe', 'object', 'embed', 'form'],
        FORBID_ATTR: ['style', 'onerror', 'onload'],
      }) as unknown as string)
    : '',
);

watch(
  () => [props.open, props.document?.id] as const,
  ([open]) => {
    if (!open || !props.document) {
      invalidatePreviewRequest();
      return;
    }

    void loadPreview();
  },
  { immediate: true },
);

onBeforeUnmount(invalidatePreviewRequest);

function invalidatePreviewRequest(): void {
  requestSequence += 1;
  loading.value = false;
  loadError.value = false;
  textContent.value = '';
  clearRenderedFilePreviews();
}

async function loadPreview(): Promise<void> {
  const sequence = ++requestSequence;
  loading.value = true;
  loadError.value = false;
  textContent.value = '';
  clearRenderedFilePreviews();
  await nextTick();

  try {
    const kind = previewKind.value;

    if (kind === 'pdf') {
      await renderPdf(streamUrl.value, sequence);
    } else if (kind === 'docx') {
      await renderDocx(streamUrl.value, sequence);
    } else if (kind === 'markdown' || kind === 'text' || kind === 'html') {
      const response = await fetch(streamUrl.value);
      if (!response.ok) {
        throw new Error(`Preview request failed: ${response.status}`);
      }

      const content = await response.text();
      if (sequence !== requestSequence) {
        return;
      }

      textContent.value = content;
    }
  } catch {
    if (sequence === requestSequence) {
      loadError.value = true;
    }
  } finally {
    if (sequence === requestSequence) {
      loading.value = false;
    }
  }
}

async function renderPdf(fileUrl: string, sequence: number): Promise<void> {
  const container = pdfContainer.value;
  if (!container) {
    throw new Error('PDF preview container is unavailable.');
  }

  const { getDocument } = await loadPdfRenderer();
  if (sequence !== requestSequence) {
    return;
  }

  // pdfjs v6 通过 loadingTask.destroy() 释放加载任务。
  const loadingTask = getDocument({ url: fileUrl });
  const pdf = await loadingTask.promise;
  if (sequence !== requestSequence) {
    await loadingTask.destroy();
    return;
  }

  try {
    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
      if (sequence !== requestSequence) {
        break;
      }

      await renderPdfPage(pdf, pageNumber, container, sequence);
    }
  } finally {
    await loadingTask.destroy();
  }
}

async function renderPdfPage(
  pdf: PDFDocumentProxy,
  pageNumber: number,
  container: HTMLDivElement,
  sequence: number,
): Promise<void> {
  const page = await pdf.getPage(pageNumber);
  const viewport = page.getViewport({ scale: 1.35 });
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('Canvas 2D context is unavailable.');
  }

  const pixelRatio = window.devicePixelRatio || 1;
  canvas.width = Math.floor(viewport.width * pixelRatio);
  canvas.height = Math.floor(viewport.height * pixelRatio);
  canvas.style.width = `${viewport.width}px`;
  canvas.style.height = `${viewport.height}px`;
  canvas.className = 'max-w-full rounded-sm bg-white shadow-sm';
  context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);

  await page.render({ canvas, canvasContext: context, viewport }).promise;
  if (sequence !== requestSequence) {
    return;
  }

  container.appendChild(canvas);
}

async function renderDocx(fileUrl: string, sequence: number): Promise<void> {
  const container = docxContainer.value;
  if (!container) {
    throw new Error('Word preview container is unavailable.');
  }

  const [response, { renderAsync: renderDocxAsync }] = await Promise.all([
    fetch(fileUrl, {
      headers: {
        Accept:
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      },
    }),
    import('docx-preview'),
  ]);

  if (!response.ok) {
    throw new Error(`Preview request failed: ${response.status}`);
  }
  if (sequence !== requestSequence) {
    return;
  }

  const blob = await response.blob();
  if (sequence !== requestSequence) {
    return;
  }

  const renderedContainer = document.createElement('div');
  await renderDocxAsync(blob, renderedContainer, undefined, {
    className: 'docx-preview',
    inWrapper: true,
    ignoreWidth: false,
    ignoreHeight: false,
    breakPages: true,
    renderHeaders: true,
    renderFooters: true,
  });

  if (sequence !== requestSequence) {
    return;
  }

  container.replaceChildren(...renderedContainer.childNodes);
}

function clearRenderedFilePreviews(): void {
  if (pdfContainer.value) {
    pdfContainer.value.innerHTML = '';
  }
  if (docxContainer.value) {
    docxContainer.value.innerHTML = '';
  }
}
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent
      class="flex h-[min(92dvh,860px)] w-[min(94vw,1180px)] max-w-none flex-col gap-0 p-0 sm:max-w-none"
    >
      <DialogHeader class="border-b py-3 pr-14 pl-4">
        <div class="flex min-w-0 items-start justify-between gap-3">
          <div class="min-w-0">
            <DialogTitle class="truncate text-base">
              {{ document?.original_filename ?? t('文档预览') }}
            </DialogTitle>
            <DialogDescription>
              {{ t('查看文档内容') }}
            </DialogDescription>
          </div>
          <Button
            v-if="streamUrl"
            as-child
            type="button"
            variant="outline"
            size="sm"
            class="shrink-0"
          >
            <a :href="streamUrl" target="_blank" rel="noopener noreferrer">
              <ExternalLink class="mr-1.5 h-4 w-4" />
              {{ t('在新窗口打开') }}
            </a>
          </Button>
        </div>
      </DialogHeader>

      <div class="min-h-0 flex-1 overflow-auto bg-muted/20">
        <div v-show="loading" class="flex h-full items-center justify-center">
          <Spinner class="h-6 w-6" />
        </div>

        <div
          v-if="!loading && loadError"
          class="flex h-full flex-col items-center justify-center gap-3 text-center"
        >
          <p class="text-sm text-muted-foreground">
            {{ t('文档加载失败，请重试。') }}
          </p>
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="loadPreview"
          >
            {{ t('重新加载') }}
          </Button>
        </div>

        <div
          v-show="!loading && !loadError && previewKind === 'markdown'"
          class="knowledge-preview mx-auto max-w-4xl bg-background px-8 py-6"
          v-html="markdownHtml"
        ></div>

        <pre
          v-show="!loading && !loadError && previewKind === 'text'"
          class="mx-auto min-h-full max-w-5xl bg-background px-8 py-6 font-mono text-sm leading-6 break-words whitespace-pre-wrap"
          >{{ textContent }}</pre>

        <div
          v-show="!loading && !loadError && previewKind === 'html'"
          class="knowledge-preview mx-auto max-w-4xl bg-background px-8 py-6"
          v-html="safeHtml"
        ></div>

        <div
          v-show="!loading && !loadError && previewKind === 'pdf'"
          ref="pdfContainer"
          class="flex flex-col items-center gap-4 px-4 py-4"
        ></div>

        <div
          v-show="!loading && !loadError && previewKind === 'docx'"
          ref="docxContainer"
          class="mx-auto w-full max-w-5xl overflow-x-auto bg-background px-4 py-4"
        ></div>
      </div>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.knowledge-preview :deep(p) {
  margin: 0.65rem 0;
}

.knowledge-preview :deep(h1),
.knowledge-preview :deep(h2),
.knowledge-preview :deep(h3),
.knowledge-preview :deep(h4) {
  margin: 1.25rem 0 0.65rem;
  font-weight: 600;
  line-height: 1.25;
}

.knowledge-preview :deep(h1) {
  font-size: 1.5rem;
}

.knowledge-preview :deep(h2) {
  font-size: 1.25rem;
}

.knowledge-preview :deep(h3) {
  font-size: 1.125rem;
}

.knowledge-preview :deep(ul),
.knowledge-preview :deep(ol) {
  margin: 0.65rem 0;
  padding-left: 1.5rem;
}

.knowledge-preview :deep(ul) {
  list-style: disc;
}

.knowledge-preview :deep(ol) {
  list-style: decimal;
}

.knowledge-preview :deep(pre) {
  overflow-x: auto;
  border-radius: 0.375rem;
  background: var(--muted);
  padding: 0.75rem;
}

.knowledge-preview :deep(code) {
  border-radius: 0.25rem;
  background: var(--muted);
  padding: 0.1rem 0.25rem;
  font-size: 0.875em;
}

.knowledge-preview :deep(pre code) {
  background: transparent;
  padding: 0;
}

.knowledge-preview :deep(blockquote) {
  border-left: 3px solid var(--border);
  margin: 0.9rem 0;
  padding-left: 0.9rem;
  color: var(--muted-foreground);
}

.knowledge-preview :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.9rem 0;
}

.knowledge-preview :deep(th),
.knowledge-preview :deep(td) {
  border: 1px solid var(--border);
  padding: 0.45rem 0.55rem;
  text-align: left;
}

.knowledge-preview :deep(a) {
  text-decoration: underline;
}

:deep(.docx-wrapper) {
  background: transparent;
  padding: 0;
}

:deep(.docx-wrapper > section.docx) {
  margin: 0 auto 1rem;
  box-shadow: 0 1px 4px rgb(0 0 0 / 0.12);
}
</style>
