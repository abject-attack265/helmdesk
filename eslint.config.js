import prettier from 'eslint-config-prettier/flat';
import vue from 'eslint-plugin-vue';

import {
  defineConfigWithVueTs,
  vueTsConfigs,
} from '@vue/eslint-config-typescript';

export default defineConfigWithVueTs(
  vue.configs['flat/essential'],
  vueTsConfigs.recommended,
  {
    ignores: [
      'vendor',
      'node_modules',
      'public',
      'bootstrap/ssr',
      'tailwind.config.js',
      'resources/js/components/ui/*',
    ],
  },
  {
    rules: {
      'vue/multi-word-component-names': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
    },
  },
  {
    // generated.d.ts 由 typescript:transform 生成，禁止手改；多态标记基类会产出空对象类型，对此文件放行该规则。
    files: ['resources/js/types/generated.d.ts'],
    rules: {
      '@typescript-eslint/no-empty-object-type': 'off',
    },
  },
  prettier,
);
