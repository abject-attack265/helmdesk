<script setup lang="ts">
import ConversationDetailDrawer from '@/components/conversation/ConversationDetailDrawer.vue';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import app from '@/routes/app';
import { router } from '@inertiajs/vue3';

/**
 * 会话详情抽屉的 Sheet 封装：v-model 绑定要查看的会话 ID（null 关闭），
 * 「查看联系人」统一跳转联系人页。供经验提炼各页复用。
 */
const conversationId = defineModel<string | null>({ default: null });

const onViewContact = (contactId: string) => {
  router.visit(
    app.contacts.index.url(
      { type: 'all' },
      {
        query: { contact: contactId },
      },
    ),
  );
};
</script>

<template>
  <Sheet
    :open="conversationId !== null"
    @update:open="
      (open) => {
        if (!open) {
          conversationId = null;
        }
      }
    "
  >
    <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-2xl">
      <ConversationDetailDrawer
        v-if="conversationId"
        :conversation-id="conversationId"
        @view-contact="onViewContact"
      />
    </SheetContent>
  </Sheet>
</template>
