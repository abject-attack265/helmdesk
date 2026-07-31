<!DOCTYPE html>
{{-- 访客独立聊天页 HTML 壳：挂载 resources/js/standalone.ts，
     渠道启动数据由 ShowStandaloneChatPageAction 经 window.__HELMDESK_STANDALONE__ 注入。 --}}
<html lang="{{ $lang }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    {{-- 与 app.blade.php 语义对齐：渲染前尽早把 .dark 打到 <html>，避免主题闪烁。 --}}
    <script>(function(){var a=localStorage.getItem('appearance')||'system';if(a==='dark'||(a==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');}})();</script>
    <style>html{background-color:oklch(1 0 0);}html.dark{background-color:oklch(0.145 0 0);}</style>

    <script>window.__HELMDESK_STANDALONE__={!! $bootstrapJson !!};</script>

    @vite('resources/js/standalone.ts')
  </head>
  <body class="font-sans antialiased">
    <div id="app"></div>
  </body>
</html>
