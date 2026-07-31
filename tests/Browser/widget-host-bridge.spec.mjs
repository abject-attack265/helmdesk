/**
 * 验证网站渠道宿主页与 iframe 的 postMessage 协议。
 * 测试前需启动 HelmDesk，并通过 HELMDESK_BASE_URL 指定访问地址。
 */
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const BASE_URL = process.env.HELMDESK_BASE_URL ?? 'http://localhost';
const CHANNEL_CODE = 'wch_smoketest123';

const HOST_PAGE_HTML = `<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>helmdesk widget protocol smoke</title>
  </head>
  <body>
    <h1 id="host-title">host page</h1>
    <script>
      window.__helmdeskReceivedMessages = [];
      window.addEventListener('message', function (event) {
        window.__helmdeskReceivedMessages.push({
          origin: event.origin,
          data: event.data,
        });
      });
    </script>
    <script async src="${BASE_URL}/embed/widget.js" data-channel-code="${CHANNEL_CODE}"></script>
    <script>
      window.HelmDeskWidget = {
        user_token: 'host-signed-token',
        visitor: {
          external_id: 'user-123',
          email: 'user@example.com',
          phone: '+8613800138000'
        },
        params: {
          utm_source: 'smoke'
        }
      };
    </script>
  </body>
</html>`;

const IFRAME_HARNESS_HTML = `<!DOCTYPE html>
<html><body>
<script>
  (function () {
    var trustedHostOrigin = null;
    window.__helmdeskIframeReceived = [];
    window.addEventListener('message', function (event) {
      window.__helmdeskIframeReceived.push({ origin: event.origin, data: event.data });
      if (trustedHostOrigin === null) trustedHostOrigin = event.origin;
    });
    window.helmdeskSend = function (type, payload) {
      var msg = { type: type };
      if (payload !== undefined) msg.payload = payload;
      window.parent.postMessage(msg, trustedHostOrigin || '*');
    };
    // 模拟 useWidgetHostBridge 在 mount 时发出 ready。
    window.helmdeskSend('helmdesk:widget:ready');
  })();
</script>
</body></html>`;

function log(label, payload) {
  if (payload === undefined) {
    console.log('[smoke]', label);
    return;
  }
  console.log('[smoke]', label, JSON.stringify(payload));
}

async function run() {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || undefined,
  });
  const context = await browser.newContext();
  const page = await context.newPage();

  // 拦截 iframe 引导请求和 iframe 页面，把它们替换成最小桥接模拟器。
  await page.route(
    `${BASE_URL}/embed/widget/${CHANNEL_CODE}/bootstrap`,
    (route) => {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          channel: {
            entry: { mode: 'bubble' },
            mobile_fullscreen_enabled: true,
          },
        }),
      });
    },
  );
  await page.route(`${BASE_URL}/embed/widget/${CHANNEL_CODE}`, (route) => {
    route.fulfill({
      status: 200,
      contentType: 'text/html; charset=utf-8',
      body: IFRAME_HARNESS_HTML,
    });
  });

  try {
    // 先导航到 BASE_URL 拿到 localhost origin，再 setContent 注入 host 页。
    await page.goto(BASE_URL + '/?should_not_leak=1', { waitUntil: 'load' });
    await page.setContent(HOST_PAGE_HTML, { waitUntil: 'load' });

    // 等待 widget 脚本注入按钮（位于 shadow DOM 内）。
    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        if (!root || !root.shadowRoot) return false;
        return !!root.shadowRoot.querySelector('.hd-button');
      },
      CHANNEL_CODE,
      { timeout: 5000 },
    );
    log('host entry mounted');

    // bubble 模式在 bootstrap 结算前隐藏气泡以避免闪现，结算后显示默认气泡。
    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        const btn =
          root &&
          root.shadowRoot &&
          root.shadowRoot.querySelector('.hd-button');
        return !!btn && btn.style.display !== 'none';
      },
      CHANNEL_CODE,
      { timeout: 5000 },
    );
    log('bubble entry visible after bootstrap');

    // 点击入口打开 panel，iframe 才会真正加载并发 ready。
    await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      root.shadowRoot.querySelector('.hd-button').click();
    }, CHANNEL_CODE);

    // 等到 iframe 收到 host:context（host 侧 widgetScript 收到 ready 后会回送）。
    const iframeReceived = await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        const iframe = root.shadowRoot.querySelector('iframe');
        if (!iframe || !iframe.contentWindow) return null;
        try {
          const list = iframe.contentWindow.__helmdeskIframeReceived;
          if (!Array.isArray(list)) return null;
          const ctx = list.find(
            (item) =>
              item && item.data && item.data.type === 'helmdesk:host:context',
          );
          return ctx ? ctx : null;
        } catch {
          return null;
        }
      },
      CHANNEL_CODE,
      { timeout: 5000 },
    );

    const ctx = await iframeReceived.jsonValue();
    log('iframe received host:context', ctx);

    assert.equal(
      ctx.origin,
      BASE_URL,
      'host:context must come from base URL origin',
    );
    assert.equal(
      ctx.data.type,
      'helmdesk:host:context',
      'message type must match',
    );
    assert.ok(ctx.data.payload, 'host:context payload must exist');
    assert.equal(
      typeof ctx.data.payload.page_url,
      'string',
      'payload.page_url must be string',
    );
    assert.equal(
      typeof ctx.data.payload.page_title,
      'string',
      'payload.page_title must be string',
    );
    assert.equal(
      typeof ctx.data.payload.referrer,
      'string',
      'payload.referrer must be string',
    );
    assert.equal(
      ctx.data.payload.page_url.includes('should_not_leak'),
      false,
      'host page query must not leak through page_url',
    );
    assert.deepEqual(
      ctx.data.payload.query_params,
      {
        utm_source: 'smoke',
        external_id: 'user-123',
        email: 'user@example.com',
        phone: '+8613800138000',
      },
      'host:context must include explicit widget params only',
    );
    assert.equal(
      ctx.data.payload.user_token,
      'host-signed-token',
      'host:context must carry signed user_token as a dedicated field',
    );

    const frameSrc = await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      return root.shadowRoot.querySelector('iframe').src;
    }, CHANNEL_CODE);
    assert.equal(
      frameSrc,
      `${BASE_URL}/embed/widget/${CHANNEL_CODE}`,
      'iframe src must not inherit host page query',
    );

    // ready 同步阶段应当主动推一次当前可见性。
    const visibilityOpen = await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        const iframe = root.shadowRoot.querySelector('iframe');
        const list = iframe.contentWindow.__helmdeskIframeReceived;
        if (!Array.isArray(list)) return null;
        const msg = list.find(
          (item) =>
            item &&
            item.data &&
            item.data.type === 'helmdesk:host:visibility' &&
            item.data.payload &&
            item.data.payload.visible === true,
        );
        return msg ? msg : null;
      },
      CHANNEL_CODE,
      { timeout: 5000 },
    );
    log(
      'iframe received host:visibility {visible:true}',
      await visibilityOpen.jsonValue(),
    );

    // SPA 路由变化（pushState）应当让 host 重新广播 host:context。
    const spaPath = '/widget-bridge-smoke-' + Date.now();
    await page.evaluate((path) => {
      history.pushState({}, '', path);
    }, spaPath);

    const refreshedContext = await page.waitForFunction(
      ({ code, marker }) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        const iframe = root.shadowRoot.querySelector('iframe');
        const list = iframe.contentWindow.__helmdeskIframeReceived;
        if (!Array.isArray(list)) return null;
        const msg = list.find(
          (item) =>
            item &&
            item.data &&
            item.data.type === 'helmdesk:host:context' &&
            item.data.payload &&
            typeof item.data.payload.page_url === 'string' &&
            item.data.payload.page_url.indexOf(marker) >= 0,
        );
        return msg ? msg : null;
      },
      { code: CHANNEL_CODE, marker: spaPath },
      { timeout: 5000 },
    );
    log(
      'iframe received refreshed host:context after pushState',
      await refreshedContext.jsonValue(),
    );

    // panel 在入口点击后应已打开。
    const panelOpenBeforeClose = await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      return root.shadowRoot.querySelector('.hd-panel').dataset.open;
    }, CHANNEL_CODE);
    assert.equal(
      panelOpenBeforeClose,
      'true',
      'panel should be open after entry click',
    );

    // 模拟 iframe 内部发 widget:close。
    await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      const iframe = root.shadowRoot.querySelector('iframe');
      iframe.contentWindow.helmdeskSend('helmdesk:widget:close');
    }, CHANNEL_CODE);

    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        return (
          root.shadowRoot.querySelector('.hd-panel').dataset.open !== 'true'
        );
      },
      CHANNEL_CODE,
      { timeout: 3000 },
    );
    log('panel closed after widget:close');

    // 声明式触发器应能打开当前渠道；不带 code 时作用于当前唯一实例。
    await page.evaluate(() => {
      var trigger = document.createElement('button');
      trigger.setAttribute('data-helmdesk-open', '');
      trigger.textContent = 'open support';
      document.body.appendChild(trigger);
      trigger.click();
    });
    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        return (
          root.shadowRoot.querySelector('.hd-panel').dataset.open === 'true'
        );
      },
      CHANNEL_CODE,
      { timeout: 3000 },
    );
    log('panel opened from data-helmdesk-open trigger');

    await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      const iframe = root.shadowRoot.querySelector('iframe');
      iframe.contentWindow.helmdeskSend('helmdesk:widget:close');
    }, CHANNEL_CODE);
    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        return (
          root.shadowRoot.querySelector('.hd-panel').dataset.open !== 'true'
        );
      },
      CHANNEL_CODE,
      { timeout: 3000 },
    );

    // 关闭后 host 应同步广播 host:visibility {visible:false}。
    const visibilityClosed = await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        const iframe = root.shadowRoot.querySelector('iframe');
        const list = iframe.contentWindow.__helmdeskIframeReceived;
        if (!Array.isArray(list)) return null;
        const msg = list.find(
          (item) =>
            item &&
            item.data &&
            item.data.type === 'helmdesk:host:visibility' &&
            item.data.payload &&
            item.data.payload.visible === false,
        );
        return msg ? msg : null;
      },
      CHANNEL_CODE,
      { timeout: 3000 },
    );
    log(
      'iframe received host:visibility {visible:false}',
      await visibilityClosed.jsonValue(),
    );

    // 反向校验：从无效 source（非 iframe 的 window）发同样的消息，host 应忽略。
    await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      // 重新打开，再用 window.postMessage（source 是顶层 window 而非 iframe），不应触发关闭。
      root.shadowRoot.querySelector('.hd-button').click();
      window.postMessage({ type: 'helmdesk:widget:close' }, location.origin);
    }, CHANNEL_CODE);

    // 等一拍让事件循环跑完。
    await page.waitForTimeout(200);
    const panelStillOpen = await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      return root.shadowRoot.querySelector('.hd-panel').dataset.open;
    }, CHANNEL_CODE);
    assert.equal(
      panelStillOpen,
      'true',
      'host must ignore messages whose source is not the embedded iframe',
    );
    log('spoofed message correctly ignored');

    await assertCustomEntryStaysHidden(context);

    log('ALL CHECKS PASSED');
  } finally {
    await context.close();
    await browser.close();
  }
}

// custom 入口模式：HelmDesk 不渲染默认气泡，全程 display:none，
// 既不在加载瞬间闪现，也不在 bootstrap 结算后出现。
async function assertCustomEntryStaysHidden(context) {
  const CUSTOM_CODE = 'wch_customhidden01';
  const page = await context.newPage();

  await page.route(
    `${BASE_URL}/embed/widget/${CUSTOM_CODE}/bootstrap`,
    (route) => {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          channel: {
            entry: { mode: 'custom' },
            mobile_fullscreen_enabled: true,
          },
        }),
      });
    },
  );
  await page.route(`${BASE_URL}/embed/widget/${CUSTOM_CODE}`, (route) => {
    route.fulfill({
      status: 200,
      contentType: 'text/html; charset=utf-8',
      body: IFRAME_HARNESS_HTML,
    });
  });

  const customHostHtml = `<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8" /><title>custom entry</title></head>
<body>
  <script async src="${BASE_URL}/embed/widget.js" data-channel-code="${CUSTOM_CODE}"></script>
</body></html>`;

  try {
    await page.goto(BASE_URL + '/', { waitUntil: 'load' });
    await page.setContent(customHostHtml, { waitUntil: 'load' });

    await page.waitForFunction(
      (code) => {
        const root = document.getElementById('helmdesk-widget-' + code);
        return !!(
          root &&
          root.shadowRoot &&
          root.shadowRoot.querySelector('.hd-button')
        );
      },
      CUSTOM_CODE,
      { timeout: 5000 },
    );

    // 等待 bootstrap 结算后入口模式被确定（entrySettingsResolved=true）。
    await page.waitForTimeout(400);

    const display = await page.evaluate((code) => {
      const root = document.getElementById('helmdesk-widget-' + code);
      return root.shadowRoot.querySelector('.hd-button').style.display;
    }, CUSTOM_CODE);

    assert.equal(
      display,
      'none',
      'custom entry mode must never render the default bubble',
    );
    log('custom entry stays hidden (no default bubble)');
  } finally {
    await page.close();
  }
}

run().catch((err) => {
  console.error('[smoke] FAILED:', err);
  process.exit(1);
});
