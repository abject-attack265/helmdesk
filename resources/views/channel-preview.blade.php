<!DOCTYPE html>
{{-- 网站渠道详情页右侧实时预览所嵌入的 iframe 文档：仅挂载预览入口，渠道草稿由父页面通过 postMessage 注入。 --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'HelmDesk') }}</title>

    <style>
      html,
      body,
      #app {
        height: 100%;
      }

      body {
        margin: 0;
        background-color: transparent;
      }
    </style>

    @vite(['resources/js/channel-preview.ts'])
  </head>
  <body class="font-sans antialiased">
    <div id="app"></div>
  </body>
</html>
