<!DOCTYPE html>
{{-- 网站渠道小部件 iframe 内部页 HTML 壳：挂载 resources/js/widget.ts，
     渠道启动数据由 ShowWidgetFrameAction 经 window.__HELMDESK_WIDGET__ 注入。 --}}
<html lang="{{ $lang }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    {{-- 与 app.blade.php 语义对齐：渲染前尽早把 .dark 打到 <html>，避免主题闪烁。 --}}
    <script>(function(){var a=localStorage.getItem('appearance')||'system';if(a==='dark'||(a==='system'&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');}})();</script>
    <style>html,body,#app{height:100%;margin:0;overflow:hidden;}body{background:transparent;}</style>

    <script>window.__HELMDESK_WIDGET__={!! $bootstrapJson !!};</script>

    @vite('resources/js/widget.ts')
  </head>
  <body class="font-sans antialiased">
    <div id="app"></div>
  </body>
</html>
