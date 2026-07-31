<!--
  应用所有者两步验证初始化页，支持生成密钥、确认绑定和会话级跳过。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import owner from '@/routes/app/owner';
import { home as appHome } from '@/routes/app';
import logout from '@/routes/logout';
import type { ShowAppOwnerTwoFactorSetupPagePropsData } from '@/types/generated';
import { Form, Link } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<ShowAppOwnerTwoFactorSetupPagePropsData>();

const { t } = useI18n();
const { copy, copied } = useClipboard();
const code = ref<string>('');
</script>

<template>
  <div
    class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
  >
    <div class="w-full max-w-sm">
      <div class="flex flex-col gap-8">
        <div class="flex flex-col items-center gap-4">
          <div
            class="mb-1 flex size-9 items-center justify-center rounded-md bg-foreground text-background"
          >
            <span class="text-sm font-bold">2FA</span>
          </div>
          <div class="space-y-2 text-center">
            <h1 class="text-xl font-medium">{{ t('启用两步验证') }}</h1>
            <p class="text-center text-sm text-muted-foreground">
              {{
                t(
                  '为保障应用管理员账号安全，建议启用两步验证；你也可以暂时跳过',
                )
              }}
            </p>
          </div>
        </div>

        <div v-if="!props.two_factor_enabled" class="space-y-6">
          <p class="text-sm text-muted-foreground">
            {{
              t(
                '启用两步验证后，每次登录都需要输入身份验证器应用提供的动态验证码。请先生成密钥。',
              )
            }}
          </p>

          <Form
            :action="owner.twoFactor.enable.url()"
            method="post"
            #default="{ processing }"
          >
            <Button type="submit" class="w-full" :disabled="processing">
              <Spinner v-if="processing" class="size-4" />
              {{ t('生成密钥') }}
            </Button>
          </Form>
        </div>

        <div v-else-if="!props.two_factor_confirmed" class="space-y-6">
          <div class="space-y-2">
            <p class="text-sm text-muted-foreground">
              {{
                t(
                  '使用 Google Authenticator 等身份验证器应用扫描二维码，或手动输入密钥。',
                )
              }}
            </p>

            <div class="flex justify-center">
              <div
                v-if="props.qr_code_svg"
                v-html="props.qr_code_svg"
                class="rounded-lg border border-border bg-white p-3 [&_svg]:size-44"
              />
            </div>

            <div
              v-if="props.manual_setup_key"
              class="flex items-stretch overflow-hidden rounded-lg border border-border"
            >
              <input
                type="text"
                readonly
                :value="props.manual_setup_key"
                class="w-full bg-background px-3 py-2 font-mono text-sm text-foreground"
              />
              <button
                type="button"
                class="border-l border-border px-3 hover:bg-muted"
                :aria-label="t('复制密钥')"
                @click="copy(props.manual_setup_key || '')"
              >
                <Check v-if="copied" class="size-4 text-foreground" />
                <Copy v-else class="size-4" />
              </button>
            </div>
          </div>

          <Form
            :action="owner.twoFactor.confirm.url()"
            method="post"
            reset-on-error
            @finish="code = ''"
            v-slot="{ errors, processing }"
          >
            <input type="hidden" name="code" :value="code" />
            <div class="space-y-3">
              <p class="text-sm font-medium">{{ t('输入验证码以确认') }}</p>
              <div class="flex flex-col items-center gap-3">
                <InputOTP
                  id="code"
                  v-model="code"
                  :maxlength="6"
                  :disabled="processing"
                >
                  <InputOTPGroup>
                    <InputOTPSlot
                      v-for="index in 6"
                      :key="index"
                      :index="index - 1"
                    />
                  </InputOTPGroup>
                </InputOTP>
                <InputError :message="errors?.code" />
              </div>

              <Button
                type="submit"
                class="w-full"
                :disabled="processing || code.length < 6"
              >
                <Spinner v-if="processing" class="size-4" />
                {{ t('确认并进入应用') }}
              </Button>
            </div>
          </Form>
        </div>

        <div v-else class="space-y-4 text-center">
          <p class="text-sm text-muted-foreground">
            {{ t('两步验证已启用，可以进入应用。') }}
          </p>
          <Button as-child class="w-full">
            <Link :href="appHome.url()">{{ t('进入应用') }}</Link>
          </Button>
        </div>

        <div v-if="!props.two_factor_confirmed" class="space-y-2 text-center">
          <Form
            :action="owner.twoFactor.skip.url()"
            method="post"
            #default="{ processing }"
          >
            <Button
              type="submit"
              variant="outline"
              class="w-full"
              :disabled="processing"
            >
              <Spinner v-if="processing" class="size-4" />
              {{ t('暂时跳过') }}
            </Button>
          </Form>

          <Form
            :action="logout.web.url()"
            method="post"
            #default="{ processing }"
          >
            <Button
              type="submit"
              variant="link"
              class="text-sm text-muted-foreground"
              :disabled="processing"
            >
              {{ t('退出登录') }}
            </Button>
          </Form>
        </div>
      </div>
    </div>
  </div>
</template>
