(function () {
  var script =
    document.currentScript ||
    (function () {
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length - 1] || null;
    })();
  if (!script) return;

  var hostConfig = currentHostConfig();
  var code = (
    hostConfig.channelCode ||
    script.getAttribute('data-channel-code') ||
    ''
  ).trim();
  if (!/^wch_[a-z0-9]+$/.test(code)) return;

  var rootId = 'helmdesk-widget-' + code;
  if (document.getElementById(rootId)) return;
  window.__HELMDESK_WIDGETS__ = window.__HELMDESK_WIDGETS__ || {};
  if (window.__HELMDESK_WIDGETS__[code]) return;
  window.__HELMDESK_WIDGETS__[code] = true;

  var src = script.getAttribute('src') || '';
  var baseUrl;
  try {
    baseUrl = new URL(src, document.baseURI).origin;
  } catch {
    return;
  }

  var MSG = {
    WIDGET_READY: 'helmdesk:widget:ready',
    WIDGET_CLOSE: 'helmdesk:widget:close',
    WIDGET_UNREAD: 'helmdesk:widget:unread',
    WIDGET_TOAST: 'helmdesk:widget:toast',
    HOST_CONTEXT: 'helmdesk:host:context',
    HOST_VISIBILITY: 'helmdesk:host:visibility',
    HOST_TRACK: 'helmdesk:host:track',
    HOST_SHUTDOWN: 'helmdesk:host:shutdown',
  };

  var root = document.createElement('div');
  root.id = rootId;
  var shadow = root.attachShadow ? root.attachShadow({ mode: 'open' }) : root;
  var defaultEntry = {
    mode: 'bubble',
    position: 'right',
    style: 'system',
    iconSize: 'large',
    backgroundColor: '#111827',
    iconColor: '#ffffff',
    size: 52,
    bottomOffset: 30,
    defaultIconUrl: '',
    activeIconUrl: '',
  };
  var featureFlags = { unreadBadge: true, inlineToast: false };
  var mobileFullscreenEnabled = true;
  // 宿主页通过 HelmDesk.setBottomInset(px) 设置的底部占用高度，移动端全屏时据此收缩内容区。
  var bottomInset = 0;
  var mobileViewportQuery = window.matchMedia
    ? window.matchMedia('(max-width: 640px), (pointer: coarse)')
    : null;
  var hostScrollLock = { applied: false, bodyOverflow: '', htmlOverflow: '' };
  var nonce = script.nonce || script.getAttribute('nonce') || '';
  var style = document.createElement('style');
  if (nonce) style.setAttribute('nonce', nonce);
  style.textContent = [
    ':host{all:initial}',
    '.hd-panel{position:fixed;z-index:2147483000;overflow:hidden;border:1px solid rgba(15,23,42,.14);border-radius:16px;background:#fff;box-shadow:0 24px 80px rgba(15,23,42,.22);display:none}',
    '.hd-panel[data-open="true"]{display:block}',
    '.hd-frame{display:block;width:100%;height:100%;border:0;background:#fff}',
    '.hd-button{position:fixed;z-index:2147483001;display:inline-flex;align-items:center;justify-content:center;border:0;box-shadow:0 16px 44px rgba(15,23,42,.25);cursor:pointer;padding:0;transition:transform .18s ease,box-shadow .18s ease}',
    '.hd-button:hover{transform:translateY(-1px);box-shadow:0 18px 50px rgba(15,23,42,.28)}',
    '.hd-button:focus-visible{outline:3px solid rgba(17,24,39,.28);outline-offset:3px}',
    '.hd-icon{display:block;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}',
    '.hd-icon-close{display:none}',
    '.hd-button[data-open="true"] .hd-icon-chat{display:none}',
    '.hd-button[data-open="true"] .hd-icon-close{display:block}',
    '.hd-img{display:none;width:100%;height:100%;object-fit:contain;box-sizing:border-box}',
    '.hd-button[data-custom="true"] .hd-icon{display:none}',
    '.hd-button[data-custom="true"] .hd-img-chat{display:block}',
    '.hd-button[data-custom="true"][data-open="true"] .hd-img-chat{display:none}',
    '.hd-button[data-custom="true"][data-open="true"] .hd-img-close{display:block}',
    '.hd-badge{position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#EF4444;color:#fff;font:600 11px/18px ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;text-align:center;box-shadow:0 0 0 2px #fff;display:none;pointer-events:none}',
    '.hd-button[data-unread="true"] .hd-badge{display:block}',
    '.hd-toast{position:fixed;z-index:2147483002;max-width:300px;padding:10px 14px;border-radius:12px;background:rgba(17,24,39,.94);color:#fff;font:500 13px/1.4 ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:0 12px 28px rgba(15,23,42,.28);cursor:pointer;opacity:0;transform:translateY(8px);transition:opacity .18s ease,transform .18s ease;pointer-events:none}',
    '.hd-toast[data-visible="true"]{opacity:1;transform:translateY(0);pointer-events:auto}',
  ].join('');

  var panel = document.createElement('div');
  panel.className = 'hd-panel';
  panel.setAttribute('aria-hidden', 'true');

  var frame = document.createElement('iframe');
  frame.className = 'hd-frame';
  frame.title = 'HelmDesk chat';
  frame.loading = 'lazy';
  frame.referrerPolicy = 'strict-origin-when-cross-origin';
  frame.allow = 'clipboard-write';
  frame.src = baseUrl + '/embed/widget/' + encodeURIComponent(code);
  panel.appendChild(frame);

  var button = document.createElement('button');
  button.className = 'hd-button';
  button.type = 'button';
  button.setAttribute('aria-controls', rootId + '-panel');
  button.setAttribute('aria-expanded', 'false');
  button.setAttribute('aria-label', 'Open chat');
  button.appendChild(
    createIcon('hd-icon-chat', [
      [
        'path',
        {
          d: 'M21 12a8.6 8.6 0 0 1-9 8.5 9.8 9.8 0 0 1-3.8-.8L3 21l1.4-4.7A8.2 8.2 0 0 1 3 12a8.6 8.6 0 0 1 9-8.5A8.6 8.6 0 0 1 21 12Z',
        },
      ],
      ['path', { d: 'M8.5 11.5h7' }],
      ['path', { d: 'M8.5 14.5H13' }],
    ]),
  );
  button.appendChild(
    createIcon('hd-icon-close', [
      ['path', { d: 'M18 6 6 18' }],
      ['path', { d: 'm6 6 12 12' }],
    ]),
  );
  var imgChat = document.createElement('img');
  imgChat.className = 'hd-img hd-img-chat';
  imgChat.alt = '';
  imgChat.setAttribute('aria-hidden', 'true');
  button.appendChild(imgChat);
  var imgClose = document.createElement('img');
  imgClose.className = 'hd-img hd-img-close';
  imgClose.alt = '';
  imgClose.setAttribute('aria-hidden', 'true');
  button.appendChild(imgClose);
  var badge = document.createElement('span');
  badge.className = 'hd-badge';
  badge.textContent = '';
  button.appendChild(badge);
  panel.id = rootId + '-panel';

  var toast = document.createElement('div');
  toast.className = 'hd-toast';
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');
  toast.addEventListener('click', function () {
    hideToast();
    setOpen(true);
  });
  var toastTimer = null;
  var lastEntrySettings = defaultEntry;
  // 入口模式（bubble/custom）在 bootstrap 返回前未知，先把气泡判定为隐藏，
  // 避免自定义入口站点在加载瞬间闪出 HelmDesk 默认气泡。
  var entrySettingsResolved = false;

  applyEntrySettings(defaultEntry);
  loadEntrySettings();

  function createIcon(className, nodes) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'hd-icon ' + className);
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    nodes.forEach(function (node) {
      var el = document.createElementNS('http://www.w3.org/2000/svg', node[0]);
      Object.keys(node[1]).forEach(function (key) {
        el.setAttribute(key, node[1][key]);
      });
      svg.appendChild(el);
    });
    return svg;
  }

  var widgetReady = false;
  var lastContextJSON = '';
  var unreadCount = 0;

  function setOpen(next) {
    panel.dataset.open = next ? 'true' : 'false';
    button.dataset.open = next ? 'true' : 'false';
    panel.setAttribute('aria-hidden', next ? 'false' : 'true');
    button.setAttribute('aria-expanded', next ? 'true' : 'false');
    button.setAttribute('aria-label', next ? 'Close chat' : 'Open chat');
    updateButtonVisibility();
    syncHostScrollLock();
    if (next) {
      applyUnreadCount(0);
      hideToast();
    }
    emitVisibility();
  }

  button.addEventListener('click', function () {
    setOpen(panel.dataset.open !== 'true');
  });

  function applyUnreadCount(count) {
    var sanitized = Math.max(0, parseInt(count, 10) || 0);
    unreadCount = sanitized;
    if (!featureFlags.unreadBadge || sanitized <= 0) {
      button.dataset.unread = 'false';
      badge.textContent = '';
      return;
    }
    button.dataset.unread = 'true';
    badge.textContent = sanitized > 99 ? '99+' : String(sanitized);
  }

  function showToast(text) {
    if (lastEntrySettings.mode === 'custom') return;
    if (!featureFlags.inlineToast) return;
    var content = (text == null ? '' : String(text)).trim();
    if (content === '') return;
    toast.textContent =
      content.length > 140 ? content.slice(0, 137) + '…' : content;
    placeToastNearEntry();
    toast.dataset.visible = 'true';
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 6000);
  }

  function hideToast() {
    if (toastTimer) {
      clearTimeout(toastTimer);
      toastTimer = null;
    }
    toast.dataset.visible = 'false';
  }

  function placeToastNearEntry() {
    if (lastEntrySettings.mode === 'custom') return;
    var entryHeight =
      lastEntrySettings.height || lastEntrySettings.size || defaultEntry.size;
    var offsetY =
      (lastEntrySettings.bottomOffset || defaultEntry.bottomOffset) +
      entryHeight +
      12;
    placeElement(
      toast,
      lastEntrySettings.position || defaultEntry.position,
      offsetY,
    );
  }

  function sendToIframe(type, payload) {
    var target = frame.contentWindow;
    if (!target) return;
    var message = { type: type };
    if (payload !== undefined) message.payload = payload;
    try {
      target.postMessage(message, baseUrl);
    } catch {}
  }

  function hostContextPayload() {
    var pageUrl = '';
    var pageTitle = '';
    var referrer = '';
    try {
      pageUrl = window.location ? stripUrlParams(window.location.href) : '';
    } catch {}
    try {
      pageTitle = document && document.title ? String(document.title) : '';
    } catch {}
    try {
      referrer =
        document && document.referrer ? stripUrlParams(document.referrer) : '';
    } catch {}
    var hostConfig = currentHostConfig();
    return {
      page_url: pageUrl,
      page_title: pageTitle,
      referrer: referrer,
      query_params: cloneQueryParams(hostConfig.queryParams),
      user_token: hostConfig.userToken || null,
    };
  }

  function emitVisibility() {
    if (!widgetReady) return;
    sendToIframe(MSG.HOST_VISIBILITY, {
      visible: panel.dataset.open === 'true',
    });
  }

  // 仅在宿主页 URL/标题真的变化时重发 context，避免 SPA 重复事件刷屏。
  function emitContextIfChanged() {
    if (!widgetReady) return;
    var payload = hostContextPayload();
    var serialized;
    try {
      serialized = JSON.stringify(payload);
    } catch {
      serialized = '';
    }
    if (serialized === lastContextJSON) return;
    lastContextJSON = serialized;
    sendToIframe(MSG.HOST_CONTEXT, payload);
  }

  function handleWidgetReady() {
    widgetReady = true;
    var payload = hostContextPayload();
    try {
      lastContextJSON = JSON.stringify(payload);
    } catch {
      lastContextJSON = '';
    }
    sendToIframe(MSG.HOST_CONTEXT, payload);
    emitVisibility();
  }

  // 监听宿主页路由变化，把新的 page_url / page_title / referrer 推给 iframe。
  // pushState / replaceState 不会触发 popstate，因此做一次温和的 monkey-patch
  // 发自定义事件，再统一收口到 safeEmit。
  function installHostNavigationListeners() {
    function safeEmit() {
      try {
        emitContextIfChanged();
      } catch {}
    }
    window.addEventListener('popstate', safeEmit);
    window.addEventListener('hashchange', safeEmit);

    var locationChangeEventName = 'helmdesk:host:locationchange';
    ['pushState', 'replaceState'].forEach(function (method) {
      var original;
      try {
        original = window.history && window.history[method];
      } catch {
        original = null;
      }
      if (typeof original !== 'function') return;
      try {
        window.history[method] = function () {
          var result = original.apply(this, arguments);
          try {
            window.dispatchEvent(new Event(locationChangeEventName));
          } catch {}
          return result;
        };
      } catch {}
    });
    window.addEventListener(locationChangeEventName, safeEmit);
  }

  window.addEventListener('message', function (event) {
    if (event.origin !== baseUrl) return;
    if (event.source !== frame.contentWindow) return;
    var data = event.data;
    if (!data || typeof data !== 'object' || typeof data.type !== 'string')
      return;

    switch (data.type) {
      case MSG.WIDGET_READY:
        handleWidgetReady();
        return;
      case MSG.WIDGET_CLOSE:
        setOpen(false);
        return;
      case MSG.WIDGET_UNREAD:
        var payload =
          data.payload && typeof data.payload === 'object' ? data.payload : {};
        if (panel.dataset.open === 'true') {
          applyUnreadCount(0);
        } else {
          applyUnreadCount(payload.count);
        }
        return;
      case MSG.WIDGET_TOAST:
        if (panel.dataset.open === 'true') return;
        var toastPayload =
          data.payload && typeof data.payload === 'object' ? data.payload : {};
        showToast(toastPayload.text);
        return;
      default:
        return;
    }
  });

  installHostNavigationListeners();
  installHostApi();
  installDeclarativeTriggers();
  installResponsiveListeners();

  function loadEntrySettings() {
    if (!window.fetch) {
      markEntrySettingsResolved();
      return;
    }

    window
      .fetch(
        baseUrl + '/embed/widget/' + encodeURIComponent(code) + '/bootstrap',
        {
          credentials: 'omit',
          headers: { Accept: 'application/json' },
        },
      )
      .then(function (response) {
        if (!response.ok) return null;
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.channel) return;
        var channel = payload.channel;
        if (typeof channel.mobile_fullscreen_enabled === 'boolean') {
          mobileFullscreenEnabled = channel.mobile_fullscreen_enabled;
        }
        if (channel.entry) {
          var normalized = normalizeEntrySettings(
            channel.entry,
            channel.theme_color,
          );
          lastEntrySettings = normalized;
          applyEntrySettings(normalized);
        } else {
          applyEntrySettings(lastEntrySettings);
        }
        if (typeof channel.unread_badge_enabled === 'boolean') {
          featureFlags.unreadBadge = channel.unread_badge_enabled;
          applyUnreadCount(unreadCount);
        }
        if (typeof channel.inline_toast_enabled === 'boolean') {
          featureFlags.inlineToast = channel.inline_toast_enabled;
          if (!featureFlags.inlineToast || lastEntrySettings.mode === 'custom')
            hideToast();
        }
        if (lastEntrySettings.mode === 'custom') {
          featureFlags.unreadBadge = false;
          featureFlags.inlineToast = false;
          applyUnreadCount(0);
          hideToast();
        }
      })
      .catch(function () {})
      .finally(markEntrySettingsResolved);
  }

  // bootstrap 无论成功、失败还是无 fetch，结算后都标记入口已确定，
  // 再按真实 mode 决定气泡显隐：custom 保持隐藏，bubble 才显示。
  function markEntrySettingsResolved() {
    if (entrySettingsResolved) return;
    entrySettingsResolved = true;
    updateButtonVisibility();
  }

  function normalizeEntrySettings(raw, themeColor) {
    var entryMode =
      ['bubble', 'custom'].indexOf(raw.mode) >= 0
        ? raw.mode
        : defaultEntry.mode;
    var iconSize =
      ['small', 'medium', 'large'].indexOf(raw.icon_size) >= 0
        ? raw.icon_size
        : defaultEntry.iconSize;
    var size = iconSizePixels(iconSize);
    var entryStyle =
      ['system', 'custom'].indexOf(raw.style) >= 0
        ? raw.style
        : defaultEntry.style;
    // 自定义图标须成对出现才生效，否则使用系统内置图标。
    var defaultIconUrl =
      entryStyle === 'custom' ? resolveIconUrl(raw.default_icon_url) : '';
    var activeIconUrl =
      entryStyle === 'custom' ? resolveIconUrl(raw.active_icon_url) : '';
    var hasCustomIcons = defaultIconUrl !== '' && activeIconUrl !== '';

    return {
      mode: entryMode,
      position:
        ['left', 'right'].indexOf(raw.position) >= 0
          ? raw.position
          : defaultEntry.position,
      style: entryStyle,
      iconSize: iconSize,
      backgroundColor: normalizeHex(themeColor, defaultEntry.backgroundColor),
      iconColor: '#ffffff',
      size: size,
      width: size,
      height: size,
      bottomOffset: clampInteger(
        raw.bottom_offset,
        defaultEntry.bottomOffset,
        0,
        120,
      ),
      defaultIconUrl: hasCustomIcons ? defaultIconUrl : '',
      activeIconUrl: hasCustomIcons ? activeIconUrl : '',
    };
  }

  // 入口自定义图标地址来自 bootstrap：绝对 http(s) 直接用，相对路径（本地存储签名 URL）拼回 baseUrl，其余丢弃。
  function resolveIconUrl(value) {
    var url = stringOption(value);
    if (url === '') return '';
    if (/^https?:\/\//i.test(url)) return url;
    if (url.charAt(0) === '/') return baseUrl + url;
    return '';
  }

  function iconSizePixels(value) {
    if (value === 'small') return 36;
    if (value === 'medium') return 48;
    return 52;
  }

  function normalizeHex(value, defaultValue) {
    return /^#[0-9a-fA-F]{6}$/.test(String(value || '')) ? value : defaultValue;
  }

  function clampInteger(value, defaultValue, min, max) {
    var number = parseInt(value, 10);
    if (!Number.isFinite(number)) return defaultValue;
    return Math.min(max, Math.max(min, number));
  }

  function normalizeHostConfig(raw) {
    var config = raw && typeof raw === 'object' ? raw : {};
    var channelCode = stringOption(config.channelCode || config.channel_code);
    var queryParams = {};
    appendParams(queryParams, config.params);
    appendVisitor(queryParams, config.visitor);
    // 签名身份作为独立字段传给 iframe，并由接待端点按 Authorization 处理。
    var visitor =
      config.visitor && typeof config.visitor === 'object'
        ? config.visitor
        : {};
    var userToken = stringOption(
      config.user_token ||
        config.userToken ||
        visitor.user_token ||
        visitor.userToken,
    );
    return {
      channelCode: channelCode,
      queryParams: queryParams,
      userToken: userToken,
    };
  }

  function currentHostConfig() {
    return normalizeHostConfig(
      window.HelmDeskWidget || window.HelmdeskWidget || {},
    );
  }

  function appendVisitor(out, raw) {
    if (!raw || typeof raw !== 'object') return;
    appendParam(out, 'external_id', raw.external_id || raw.externalId);
    appendParam(out, 'email', raw.email);
    appendParam(out, 'phone', raw.phone);
    appendParam(out, 'name', raw.name);
  }

  function appendParams(out, raw) {
    if (!raw || typeof raw !== 'object') return;
    Object.keys(raw).forEach(function (key) {
      appendParam(out, key, raw[key]);
    });
  }

  function appendParam(out, key, value) {
    if (!acceptParamKey(key)) return;
    var normalized = stringOption(value);
    if (normalized === '') return;
    out[key] =
      normalized.length > 1024 ? normalized.slice(0, 1024) : normalized;
  }

  function acceptParamKey(key) {
    var name = stringOption(key);
    if (!/^[a-zA-Z0-9_.-]{1,64}$/.test(name)) return false;
    return (
      ['user_token', 'session_token', '_token', 'signature', 'sig'].indexOf(
        name.toLowerCase(),
      ) < 0
    );
  }

  function stringOption(value) {
    if (value === null || value === undefined) return '';
    return String(value).trim();
  }

  function cloneQueryParams(params) {
    var copy = {};
    Object.keys(params || {}).forEach(function (key) {
      copy[key] = params[key];
    });
    return copy;
  }

  function stripUrlParams(value) {
    try {
      var parsed = new URL(String(value), document.baseURI);
      return parsed.origin + parsed.pathname;
    } catch {
      return '';
    }
  }

  function applyEntrySettings(settings) {
    lastEntrySettings = settings;
    var iconSize = Math.max(18, Math.min(30, Math.floor(settings.size * 0.5)));
    var panelGap = 12;
    var verticalReserve =
      settings.bottomOffset + settings.height + panelGap + 24;
    var panelWidth = 380;
    var panelHeight = 620;

    button.style.width = settings.width + 'px';
    button.style.height = settings.height + 'px';
    button.style.borderRadius = '999px';
    button.style.color = settings.iconColor;

    if (settings.defaultIconUrl && settings.activeIconUrl) {
      // 自定义图标直接作为入口本身：去掉主题色圆圈背景与阴影，让图标铺满入口。
      imgChat.src = settings.defaultIconUrl;
      imgClose.src = settings.activeIconUrl;
      button.dataset.custom = 'true';
      button.style.backgroundColor = 'transparent';
      button.style.boxShadow = 'none';
    } else {
      imgChat.removeAttribute('src');
      imgClose.removeAttribute('src');
      button.dataset.custom = 'false';
      button.style.backgroundColor = settings.backgroundColor;
      button.style.boxShadow = '';
    }

    Array.prototype.forEach.call(
      button.querySelectorAll('.hd-icon'),
      function (icon) {
        icon.style.width = iconSize + 'px';
        icon.style.height = iconSize + 'px';
      },
    );

    placeElement(button, settings.position, settings.bottomOffset);

    if (isMobileFullscreenActive()) {
      panel.style.left = '0';
      panel.style.right = '';
      panel.style.top = '0';
      panel.style.bottom = '';
      panel.style.width = '100vw';
      panel.style.height = supportsDynamicViewportUnit() ? '100dvh' : '100vh';
      panel.style.borderRadius = '0';
      panel.style.border = '0';
      panel.style.boxShadow = 'none';
      // 让出底部 bottomInset：面板保持满屏（背景不露出宿主页），只收缩 iframe 内容区。
      frame.style.height =
        bottomInset > 0 ? 'calc(100% - ' + bottomInset + 'px)' : '';
    } else {
      panel.style.width = 'min(' + panelWidth + 'px, calc(100vw - 16px))';
      panel.style.height =
        'min(' + panelHeight + 'px, calc(100vh - ' + verticalReserve + 'px))';
      panel.style.borderRadius = '16px';
      panel.style.border = '';
      panel.style.boxShadow = '';
      frame.style.height = '';
      placeElement(
        panel,
        settings.position,
        settings.bottomOffset + settings.height + panelGap,
      );
    }

    updateButtonVisibility();
    syncHostScrollLock();
  }

  function isMobileViewport() {
    if (mobileViewportQuery) return mobileViewportQuery.matches;
    return window.innerWidth <= 640;
  }

  function isMobileFullscreenActive() {
    return mobileFullscreenEnabled && isMobileViewport();
  }

  function supportsDynamicViewportUnit() {
    return (
      window.CSS &&
      window.CSS.supports &&
      window.CSS.supports('height', '100dvh')
    );
  }

  function updateButtonVisibility() {
    var hidden =
      !entrySettingsResolved ||
      lastEntrySettings.mode === 'custom' ||
      (panel.dataset.open === 'true' && isMobileFullscreenActive());
    button.style.display = hidden ? 'none' : 'inline-flex';
  }

  function syncHostScrollLock() {
    var shouldLock =
      panel.dataset.open === 'true' && isMobileFullscreenActive();
    if (shouldLock && !hostScrollLock.applied) {
      hostScrollLock.bodyOverflow = document.body
        ? document.body.style.overflow
        : '';
      hostScrollLock.htmlOverflow = document.documentElement
        ? document.documentElement.style.overflow
        : '';
      if (document.body) document.body.style.overflow = 'hidden';
      if (document.documentElement)
        document.documentElement.style.overflow = 'hidden';
      hostScrollLock.applied = true;
      return;
    }

    if (!shouldLock && hostScrollLock.applied) {
      if (document.body)
        document.body.style.overflow = hostScrollLock.bodyOverflow;
      if (document.documentElement)
        document.documentElement.style.overflow = hostScrollLock.htmlOverflow;
      hostScrollLock.applied = false;
    }
  }

  function releaseHostScrollLock() {
    if (!hostScrollLock.applied) return;
    if (document.body)
      document.body.style.overflow = hostScrollLock.bodyOverflow;
    if (document.documentElement)
      document.documentElement.style.overflow = hostScrollLock.htmlOverflow;
    hostScrollLock.applied = false;
  }

  function placeElement(element, position, offsetY) {
    element.style.left = '';
    element.style.right = '';
    element.style.top = '';
    element.style.bottom = '';

    if (position === 'left') {
      element.style.left = '0';
    } else {
      element.style.right = '0';
    }
    element.style.bottom = offsetY + 'px';
  }

  function installResponsiveListeners() {
    var refresh = function () {
      applyEntrySettings(lastEntrySettings);
    };
    if (mobileViewportQuery && mobileViewportQuery.addEventListener) {
      mobileViewportQuery.addEventListener('change', refresh);
    } else if (mobileViewportQuery && mobileViewportQuery.addListener) {
      mobileViewportQuery.addListener(refresh);
    }
    window.addEventListener('resize', refresh);
    window.addEventListener('orientationchange', refresh);
  }

  function installDeclarativeTriggers() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!target || !target.closest) return;
      var trigger = target.closest('[data-helmdesk-open]');
      if (!trigger) return;
      var targetCode = stringOption(trigger.getAttribute('data-helmdesk-open'));
      if (targetCode !== '' && targetCode !== code) return;
      event.preventDefault();
      setOpen(true);
    });
  }

  function mountRoot() {
    shadow.appendChild(style);
    shadow.appendChild(panel);
    shadow.appendChild(button);
    shadow.appendChild(toast);
    document.body.appendChild(root);
  }

  // 暴露给宿主页的最小公开 API：
  //   HelmDesk.show()        强制打开聊天面板
  //   HelmDesk.hide()        强制关闭聊天面板
  //   HelmDesk.track(name, props?)  让宿主页可以把业务事件透传给 iframe，
  //                                 由 widget 端做埋点/AI 上下文等扩展
  //   HelmDesk.shutdown()    彻底卸载 widget DOM，并通知 iframe 清理
  //   HelmDesk.setBottomInset(px)  预留底部空间（移动端全屏时收缩 iframe 内容区），px=0 复原。
  //                                 供宿主在底部被遮挡（如软键盘）时让出空间。
  //   HelmDesk.setUserToken(token)  更新签名身份 token 并立即推给 iframe。
  //                                 供宿主在长会话中续签，避免 token 过期后访客被回落为匿名身份。
  //
  // 多渠道场景下统一通过 window.HelmDesk.channels[code] 拿到对应实例：
  // 顶层 API 会作用在最后一个挂载的实例上，足够覆盖 99% 单页只装一个 widget 的场景。
  function installHostApi() {
    var helm = window.HelmDesk;
    if (!helm) {
      helm = window.HelmDesk = { channels: {} };
    }
    if (!helm.channels) helm.channels = {};

    var instance = {
      code: code,
      show: function () {
        setOpen(true);
      },
      hide: function () {
        setOpen(false);
      },
      track: function (name, props) {
        var event = String(name || '').trim();
        if (event === '') return;
        var payload = { event: event };
        if (props && typeof props === 'object') {
          try {
            JSON.stringify(props);
            payload.properties = props;
          } catch {}
        }
        sendToIframe(MSG.HOST_TRACK, payload);
      },
      shutdown: function () {
        sendToIframe(MSG.HOST_SHUTDOWN);
        try {
          root.remove();
        } catch {}
        hideToast();
        releaseHostScrollLock();
        if (window.__HELMDESK_WIDGETS__) {
          delete window.__HELMDESK_WIDGETS__[code];
        }
        if (helm.channels) delete helm.channels[code];
      },
      setBottomInset: function (px) {
        var next = Math.max(0, Math.min(2000, Math.round(Number(px) || 0)));
        if (next === bottomInset) return;
        bottomInset = next;
        applyEntrySettings(lastEntrySettings);
      },
      setUserToken: function (token) {
        var next = stringOption(token);
        var cfg =
          window.HelmDeskWidget && typeof window.HelmDeskWidget === 'object'
            ? window.HelmDeskWidget
            : (window.HelmDeskWidget = {});
        cfg.user_token = next === '' ? undefined : next;
        // 立即把续签后的身份 token 通过 host:context 推给 iframe，无需等宿主页下次导航。
        // widget 尚未 ready 时为 no-op，ready 时会自动带上当前 token。
        emitContextIfChanged();
      },
    };

    helm.channels[code] = instance;
    [
      'show',
      'hide',
      'track',
      'shutdown',
      'setBottomInset',
      'setUserToken',
    ].forEach(function (key) {
      helm[key] = instance[key];
    });
  }

  if (document.body) {
    mountRoot();
  } else {
    document.addEventListener('DOMContentLoaded', mountRoot, { once: true });
  }
})();
