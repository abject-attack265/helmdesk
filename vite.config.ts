import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'url';
import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    host: '127.0.0.1',
  },
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.ts',
        'resources/js/standalone.ts',
        'resources/js/widget.ts',
        'resources/js/channel-preview.ts',
      ],
      ssr: 'resources/js/ssr.ts',
      refresh: true,
    }),
    tailwindcss(),
    wayfinder({
      formVariants: true,
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false,
        },
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  build: {
    // Vite 默认在构建开始就清空 outDir、结束才写回，期间旧页面引用的资源全部 404（实测约 25 秒）。
    // 线上构建时旧进程仍在服务，故保留旧产物；资源名带内容 hash，不会冲突。
    emptyOutDir: false,
    rolldownOptions: {
      // 抑制 Rolldown 对第三方依赖（如 @vueuse/core）中
      // 位置无效的 /* #__PURE__ */ 注解发出的 INVALID_ANNOTATION 警告。
      // 注解来自依赖产物、我们无法修改，且不影响构建正确性。
      checks: {
        invalidAnnotation: false,
      },
      output: {
        codeSplitting: true,
      },
    },
  },
});
