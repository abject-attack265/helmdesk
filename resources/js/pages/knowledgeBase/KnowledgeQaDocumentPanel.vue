<!--
  问答创建与编辑面板，支持填写问题、其他问法和多个答案。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import type { ListKnowledgeQaEntryItemData } from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

const MAX_SIMILAR_QUESTION_COUNT = 20;
const MAX_ANSWER_COUNT = 10;

const props = defineProps<{
  mode: Mode;
  knowledgeBaseId: string;
  groupOptions: Array<{ id: string; label: string }>;
  defaultGroupId: string | null;
  entry: ListKnowledgeQaEntryItemData | null;
}>();

const emit = defineEmits<{
  cancel: [];
  saved: [];
}>();

const { t } = useI18n();

const form = useForm<{
  question: string;
  similar_questions: string[];
  answers: string[];
  group_id: string;
}>({
  question: '',
  similar_questions: [],
  answers: [''],
  group_id: '',
});

const initialFormSnapshot = ref('');

onMounted(initializeFormForCurrentTarget);

function snapshotForm(): string {
  return JSON.stringify({
    question: form.question,
    similar_questions: form.similar_questions,
    answers: form.answers,
    group_id: form.group_id,
  });
}

function snapshotInitial(): void {
  initialFormSnapshot.value = snapshotForm();
}

function initializeFormForCurrentTarget(): void {
  form.clearErrors();

  if (props.mode === 'create') {
    form.question = '';
    form.similar_questions = [];
    form.answers = [''];
    form.group_id = props.defaultGroupId ?? props.groupOptions[0]?.id ?? '';
    snapshotInitial();
    return;
  }

  const target = props.entry;
  if (!target) {
    return;
  }

  form.question = target.question;
  form.similar_questions = [...target.similar_questions];
  form.answers = target.answers.length > 0 ? [...target.answers] : [''];
  form.group_id = target.group_id;
  snapshotInitial();
}

const isDirty = computed(() => snapshotForm() !== initialFormSnapshot.value);

const normalizedAnswers = computed(() =>
  form.answers.map((answer) => answer.trim()).filter((answer) => answer !== ''),
);

const submitDisabled = computed(
  () =>
    form.processing ||
    form.question.trim() === '' ||
    normalizedAnswers.value.length === 0,
);

function addSimilarQuestion(): void {
  if (form.similar_questions.length >= MAX_SIMILAR_QUESTION_COUNT) {
    return;
  }
  form.similar_questions = [...form.similar_questions, ''];
}

function removeSimilarQuestion(index: number): void {
  form.similar_questions = form.similar_questions.filter((_, i) => i !== index);
}

function addAnswer(): void {
  if (form.answers.length >= MAX_ANSWER_COUNT) {
    return;
  }
  form.answers = [...form.answers, ''];
}

function removeAnswer(index: number): void {
  if (form.answers.length === 1) {
    form.answers = [''];
    return;
  }
  form.answers = form.answers.filter((_, i) => i !== index);
}

function confirmDiscardIfDirty(): boolean {
  if (form.processing) {
    return false;
  }
  if (!isDirty.value) {
    return true;
  }
  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

function hasUnsavedChanges(): boolean {
  return isDirty.value || form.processing;
}

defineExpose({ confirmDiscardIfDirty, hasUnsavedChanges });

function submit(): void {
  if (submitDisabled.value) {
    return;
  }

  const payload = (data: {
    question: string;
    similar_questions: string[];
    answers: string[];
    group_id: string;
  }) => ({
    question: data.question,
    similar_questions: data.similar_questions
      .map((question) => question.trim())
      .filter((question) => question !== ''),
    answers: data.answers
      .map((answer) => answer.trim())
      .filter((answer) => answer !== ''),
    group_id: data.group_id || null,
  });

  if (props.mode === 'create') {
    form.transform(payload).post(
      KnowledgeBase.Qa.CreateKnowledgeQaEntryAction.url({
        knowledgeBase: props.knowledgeBaseId,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          emit('saved');
        },
      },
    );
    return;
  }

  const target = props.entry;
  if (!target) {
    return;
  }

  form.transform(payload).put(
    KnowledgeBase.Qa.UpdateKnowledgeQaEntryAction.url({
      knowledgeBase: props.knowledgeBaseId,
      entry: target.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        emit('saved');
      },
    },
  );
}

function close(): void {
  if (form.processing) {
    return;
  }
  if (!confirmDiscardIfDirty()) {
    return;
  }
  emit('cancel');
}
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall :title="mode === 'create' ? t('添加问答') : t('编辑问答')" />

    <form class="space-y-6" @submit.prevent="submit">
      <FormField
        :label="t('问题')"
        label-for="qa-entry-question"
        :error="form.errors.question"
        required
      >
        <Input
          id="qa-entry-question"
          v-model="form.question"
          class="mt-1 block w-full"
          type="text"
          autocomplete="off"
          maxlength="500"
          :aria-invalid="Boolean(form.errors.question)"
          :disabled="form.processing"
          required
        />
      </FormField>

      <FormField
        v-if="mode === 'create' && groupOptions.length > 0"
        :label="t('分组')"
        label-for="qa-entry-group"
        :error="form.errors.group_id"
      >
        <Select v-model="form.group_id" :disabled="form.processing">
          <SelectTrigger id="qa-entry-group" class="mt-1 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="group in groupOptions"
              :key="group.id"
              :value="group.id"
            >
              {{ group.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>

      <FormField
        :label="t('其他问法（选填）')"
        :error="form.errors.similar_questions"
        :help="
          t('最多可添加 {count} 个其他问法。', {
            count: MAX_SIMILAR_QUESTION_COUNT,
          })
        "
      >
        <div class="mt-1 flex justify-end">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="
              form.processing ||
              form.similar_questions.length >= MAX_SIMILAR_QUESTION_COUNT
            "
            @click="addSimilarQuestion"
          >
            <Plus class="mr-1.5 h-4 w-4" />
            {{ t('添加问法') }}
          </Button>
        </div>
        <div
          v-if="form.similar_questions.length === 0"
          class="mt-2 text-sm text-muted-foreground"
        >
          {{ t('还没有其他问法') }}
        </div>
        <div
          v-for="(_, index) in form.similar_questions"
          :key="index"
          class="mt-2 flex items-center gap-2"
        >
          <Input
            v-model="form.similar_questions[index]"
            type="text"
            autocomplete="off"
            maxlength="500"
            :disabled="form.processing"
            :aria-label="
              t('其他问法 {number}', {
                number: index + 1,
              })
            "
          />
          <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="form.processing"
            :aria-label="
              t('删除第 {number} 个问法', {
                number: index + 1,
              })
            "
            @click="removeSimilarQuestion(index)"
          >
            <Trash2 class="h-4 w-4" />
          </Button>
        </div>
      </FormField>

      <FormField
        :label="t('答案')"
        :error="form.errors.answers"
        :help="
          t('最多可添加 {count} 个答案。', {
            count: MAX_ANSWER_COUNT,
          })
        "
        required
        role="group"
        aria-required="true"
      >
        <div class="mt-1 flex justify-end">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="
              form.processing || form.answers.length >= MAX_ANSWER_COUNT
            "
            @click="addAnswer"
          >
            <Plus class="mr-1.5 h-4 w-4" />
            {{ t('添加答案') }}
          </Button>
        </div>
        <div
          v-for="(_, index) in form.answers"
          :key="index"
          class="mt-2 space-y-2 rounded-md border p-3"
        >
          <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-medium">
              {{ t('答案') }} {{ index + 1 }}
            </span>
            <Button
              v-if="form.answers.length > 1"
              type="button"
              variant="ghost"
              size="icon"
              :disabled="form.processing"
              :aria-label="
                t('删除第 {number} 个答案', {
                  number: index + 1,
                })
              "
              @click="removeAnswer(index)"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
          <Textarea
            v-model="form.answers[index]"
            class="min-h-32"
            maxlength="200000"
            :disabled="form.processing"
            :aria-invalid="Boolean(form.errors.answers)"
            :aria-label="
              t('答案 {number}', {
                number: index + 1,
              })
            "
          />
        </div>
      </FormField>

      <FormActions
        :submit-label="form.processing ? t('保存中...') : t('保存')"
        :processing="form.processing"
        :submit-disabled="submitDisabled"
      >
        <template #submit>
          <LoaderCircle
            v-if="form.processing"
            class="mr-1.5 h-4 w-4 animate-spin"
          />
          {{ form.processing ? t('保存中...') : t('保存') }}
        </template>
        <Button
          type="button"
          variant="outline"
          :disabled="form.processing"
          @click="close"
        >
          {{ t('取消') }}
        </Button>
      </FormActions>
    </form>
  </div>
</template>
