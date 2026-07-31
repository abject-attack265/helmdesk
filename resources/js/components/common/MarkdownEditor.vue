<!--
  文件说明：基于 Vditor 的所见即所得 Markdown 编辑器封装，默认 WYSIWYG 模式，
  让不熟悉 Markdown 语法的用户也能直接通过工具栏编辑标题、列表、表格、链接等富文本。
  内部通过动态 import 加载 vditor，避免在 SSR 阶段访问 window 报错，并把它从主包里拆出去。
-->
<script setup lang="ts">
import type Vditor from 'vditor';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

type MarkdownEditorMode = 'wysiwyg' | 'ir' | 'sv';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    disabled?: boolean;
    height?: number | string;
    mode?: MarkdownEditorMode;
  }>(),
  {
    disabled: false,
    height: 360,
    mode: 'wysiwyg',
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const containerRef = ref<HTMLDivElement | null>(null);
let editor: Vditor | null = null;
let isReady = false;
let isUnmounted = false;

onMounted(async () => {
  const [vditorModule] = await Promise.all([
    import('vditor'),
    import('vditor/dist/index.css'),
  ]);
  const VditorCtor = vditorModule.default;

  if (isUnmounted || !containerRef.value) {
    return;
  }

  editor = new VditorCtor(containerRef.value, {
    mode: props.mode,
    lang: 'zh_CN',
    height: props.height,
    value: props.modelValue,
    cache: { enable: false },
    counter: { enable: false },
    toolbar: [
      'headings',
      'bold',
      'italic',
      'strike',
      '|',
      'list',
      'ordered-list',
      'check',
      '|',
      'quote',
      'line',
      'inline-code',
      'code',
      'link',
      'table',
      '|',
      'undo',
      'redo',
    ],
    preview: { actions: [] },
    after: () => {
      isReady = true;
      if (isUnmounted && editor) {
        editor.destroy();
        editor = null;
        isReady = false;
        return;
      }
      if (editor && editor.getValue() !== props.modelValue) {
        editor.setValue(props.modelValue ?? '');
      }
      if (props.disabled && editor) {
        editor.disabled();
      }
    },
    input: (value: string) => {
      emit('update:modelValue', value);
    },
  });
});

onBeforeUnmount(() => {
  isUnmounted = true;
  if (editor) {
    editor.destroy();
    editor = null;
    isReady = false;
  }
});

watch(
  () => props.modelValue,
  (next) => {
    if (!editor || !isReady) {
      return;
    }
    if (editor.getValue() !== next) {
      editor.setValue(next ?? '');
    }
  },
);

watch(
  () => props.disabled,
  (next) => {
    if (!editor || !isReady) {
      return;
    }
    if (next) {
      editor.disabled();
    } else {
      editor.enable();
    }
  },
);
</script>

<template>
  <div ref="containerRef" class="markdown-editor-host"></div>
</template>

<style scoped>
.markdown-editor-host :deep(.vditor) {
  border-radius: var(--radius, 0.5rem);
}

.markdown-editor-host :deep(.vditor-toolbar) {
  padding-left: 5px !important;
}

.markdown-editor-host :deep(.vditor-wysiwyg pre.vditor-reset),
.markdown-editor-host :deep(.vditor-ir pre.vditor-reset),
.markdown-editor-host :deep(.vditor-sv) {
  padding: 10px 12px !important;
}
</style>
