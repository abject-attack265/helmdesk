# HelmDesk 项目规范

## 概览

HelmDesk 是基于 Laravel 13、Vue 3、Inertia.js v3 和 Go 运行时的全栈应用。采用 Action 模式组织业务逻辑，使用 Laravel Data 做类型安全的数据流转，通过 Wayfinder 和 TypeScript Transformer 实现前后端类型同步。

HelmDesk 的业务数据、成员、权限、AI 供应商、AI 模型和调用日志由同一个系统实例统一管理。

**开发重点**:
- 后端框架: Laravel 13 + Fortify + Sanctum
- 架构模式: Laravel Actions (lorisleiva/laravel-actions)
- 数据层: Spatie Laravel Data + TypeScript Transformer
- 前端: Vue 3 + Inertia.js v3 + TypeScript + TailwindCSS 4
- 路由生成: Laravel Wayfinder

**底层运行时 / 基础设施** (日常开发无需关注):
- 运行时: Go + FrankenPHP；支持直接运行独立二进制和 Debian 容器镜像
- 数据库: SQLite（main / rag / cache / session / jobs 分库）
- 缓存: SQLite
- 队列: SQLite database driver。文件存储默认使用本地私有磁盘，也可在后台配置 S3 兼容存储
- 搜索: TNTSearch（Scout TNTSearch driver + 知识库节点向量检索）
- 实时: Go 运行时启动 FrankenPHP 内置 Mercure，前端用 EventSource 订阅主题

## 目录结构

以下目录树用于说明主要分层，实际领域目录以当前代码库为准；新增文件前先查看相邻领域的现有结构和命名。

```
app/                    # Laravel应用层（主要开发区域）
  ├─ Actions/           # 业务逻辑（Action模式），按领域分目录
  ├─ Data/              # 数据传输对象（DTO / PageProps / ViewModel），按领域分目录
  ├─ Models/            # Eloquent模型
  ├─ Http/              # HTTP层（中间件、少量传统Controller）
  ├─ Services/          # 业务服务（I/O、第三方、框架适配）
  ├─ Enums/             # 枚举
  └─ Settings/          # 系统设置（Spatie Settings）

resources/js/           # Vue 3前端
  ├─ pages/             # Inertia页面组件
  ├─ layouts/           # 布局组件
  ├─ components/        # 组件库
  ├─ composables/       # 组合式函数
  ├─ locales/           # 前端国际化（key为中文）
  ├─ types/             # TS类型定义
  │   └─ generated.d.ts # 自动生成（勿手动编辑）
  ├─ actions/           # Wayfinder生成的前端路由
  └─ routes/            # Wayfinder生成的路由工具

routes/                 # Laravel路由定义
lang/                   # 后端国际化（Laravel标准）
```

## 关键特性

**类型安全的数据流**:
1. 后端定义 Laravel Data（`app/Data/<Domain>/*.php`，通用 Data 例外）
2. 运行 `php artisan typescript:transform` 生成TS类型
3. 前端使用生成的类型（`resources/js/types/generated.d.ts`）
4. 数据自动转换为snake_case（配置于`config/data.php`）

**路由自动生成**:
- Wayfinder自动生成前端路由辅助函数
- 位于 `resources/js/actions` 和 `resources/js/routes`
- 前端通过类型安全的方式调用后端路由

## 通用逻辑放置约定

- **业务用例（可复用流程）**：优先做成 Action（可通过 `SomeAction::run()` 复用与编排）
- **纯转换/映射逻辑**：优先内聚到"所属对象"上（Enum / Data / Model 的 static 工厂方法）
- **基础设施封装（I/O、第三方、框架适配）**：放在 `app/Services/**`

## 开发阶段错误暴露

- 开发阶段优先让错误显性暴露，避免用过宽的防御性逻辑吞掉异常或数据问题
- 不要随手添加 `try/catch` 后返回空数组、默认值、`null` 或静默跳过；除非这是明确的业务降级路径，并且有日志、测试和用户可感知反馈
- 不要为了"更稳"而掩盖不符合预期的状态；未知状态应尽早失败，修正数据来源或调用方
- 边界层可以做输入校验和兼容解析，但核心业务逻辑应依赖明确类型、明确前置条件和测试覆盖

## 禁止事项

- **禁止在 Controller 中写业务逻辑** - 使用 Action 模式
- **禁止使用注解定义验证规则** - 使用 Laravel 官方 `rules()` 方法
- **禁止手动编辑** `generated.d.ts` - 运行 `php artisan typescript:transform`
- **禁止手动编辑** Wayfinder 生成的路由文件
- **禁止硬编码路由字符串** - 使用 Wayfinder
- **禁止不使用 Laravel Data 传输数据** - 失去类型安全
- **禁止前端多语言 key 使用英文** - 统一使用中文
- **禁止直接显示后端时间** - 必须使用 `formatDateTime()`
- **禁止自己实现 UI 组件** - 优先使用 Reka UI 组件库
- **禁止前端手写枚举值→文案映射** - 由后端统一下发
- **禁止用静默默认值掩盖异常状态** - 开发阶段应尽早暴露并修复问题
- **禁止自定义页面分页每页条数** - 一律用 `SimplePaginationData::DEFAULT_PER_PAGE`（10）
- **禁止随意更新** `.gitignore` - 只有在明确需要忽略项目级生成物且经确认后才修改

## 常用开发命令

```bash
composer setup                         # 首次初始化开发环境
make                                   # 启动 Go、FrankenPHP 和 Vite 开发环境
make build                             # 构建当前 CPU 架构的 Linux 独立二进制
make build-all                         # 构建 Linux AMD64 和 ARM64 独立二进制
make build-windows                     # 在 Windows x86_64 环境构建独立运行包
make image IMAGE=helmdesk:latest       # 构建当前 CPU 架构的运行镜像
make push TAG=latest                   # 推送 Linux AMD64 和 ARM64 镜像
npm run dev                            # 启动前端开发服务器（Vite 热重载）
php artisan typescript:transform       # 类型生成（修改 Data 后必须执行）
php artisan make:data MyData           # 创建 Data 类
php artisan make:action MyAction       # 创建 Action
php artisan test --compact             # 运行测试
php artisan test --compact --filter=x  # 按名称筛选测试
vendor/bin/pint --dirty --format agent # PHP 代码格式化
npm run lint                           # ESLint 检查
npm run format                         # Prettier 格式化
```

---

# 后端规范

## Action 模式

**禁止使用传统 Controller 编写业务逻辑**，统一使用 Action 模式。

### 目录组织

- Action 必须放在 `app/Actions/<Domain>/` 下
- 领域优先按业务概念命名（如 `User/`、`KnowledgeBase/`）
- 命名采用 "动词 + 名词 + Action"，如 `ShowContactListAction`、`UpdateContactAction`

### 基本结构

```php
class UpdateContactAction
{
    use AsAction;

    /**
     * 保存联系人表单提交的数据。
     */
    public function handle(Contact $contact, FormUpdateContactData $data): void
    {
        $contact->update($data->toArray());
    }

    /**
     * 将 HTTP 请求转换为表单 Data 并返回重定向响应。
     */
    public function asController(Request $request, Contact $contact): RedirectResponse
    {
        $data = FormUpdateContactData::from($request);
        $this->handle($contact, $data);
        return redirect()->route('...');
    }
}
```

### 关键原则

- **handle()**: 编写所有业务逻辑，保持纯粹可测试
- **asController()**: 仅负责请求/响应转换，不写业务逻辑
- **Action 互相调用**: 使用 `SomeAction::run()` 方法

### 对外 / 访客入口

面向访客的对外流量（访客聊天 API `routes/api.php` 的 `chat/{code}`、Telegram webhook 等）由对应的入口 Action 直接承接：`asController()` 负责解析请求并在入口处把请求收窄成业务类型（Enum / Data / Model ID），再调用业务 Action 的 `handle()`。业务 Action 的 `handle()` 直接声明明确类型。

- 业务语义上的非 200 响应用异常表达（`NotFoundHttpException` → 404，`ValidationException` → 422，`AuthorizationException` → 403）；禁止自造 `{ok, code}` envelope。
- 对外 JSON 返回值用 **Spatie `Data` / `DataCollection`** 或 PHP 原生数组/标量。

## Laravel Data

**所有数据传输必须使用 Laravel Data**。

### 目录组织

- Data 默认放在 `app/Data/<Domain>/` 下
- 跨领域复用的通用 Data 放在 `app/Data/` 根目录

### 命名规范

| 类型 | 命名格式 | 示例 |
|------|---------|------|
| 提交入参 | `Form*Data` | `FormCreateUserData`、`FormUpdateUserData` |
| 页面 Props | `Show*PagePropsData` | `ShowUserListPagePropsData` |
| 列表项 | `List*ItemData` | `ListUserItemData` |

- **提交用 Data 统一使用 `Form*` 前缀**
- 展示型页面使用 `Show*PagePropsData`，即使只有一个字段
- 非展示型/仅表单型页面可直接下发 `Create*FormData` / `Edit*FormData`

### 验证规则

使用 Laravel 官方 `rules()` 方法，**禁止使用注解**：

```php
class FormCreateContactData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
```

### 关键特性

- 已配置全局 `SnakeCaseMapper`，输入/输出自动转换为 `snake_case`
- PHP 属性统一使用 snake_case（如 `$logo_url`），与数据库字段和前端 props 保持一致
- 修改 Data 类后**必须执行** `php artisan typescript:transform`
- Data 保持轻量：只放字段、默认值合并和展示工厂；跨状态逻辑放到 Action / Service
- 避免为了转发调用在 Data / Enum 上增加薄包装方法；优先让业务代码直接调用真正负责的 Action / Service
- 通过模型 cast 访问 Data 后如需调用方法，先赋给有明确类型的局部变量，避免 IDE 无法识别具体类型

### 重构提交用 Data 的流程（Form* 前缀）

只改提交用 Data 时，先从 Action 入手找 `::from($request)` / `::validateAndCreate()`，锁定目标类：
1. 新增 `FormXxxData`（新文件名/类名）
2. 更新所有后端引用（Action `use`、`handle()` 参数类型、`asController()` 构建方式）
3. 删除旧 Data（避免 TS 重复类型与歧义）
4. 运行 `php artisan typescript:transform`
5. 前端显式类型引用（`useForm<T>()` / `defineProps<T>()`）同步切到新类型

## 枚举规范

### 基础约束

- 凡是会进入 DB / API / Inertia props 的枚举，必须是 Backed Enum（`string|int`）
- 需要显示文案的枚举必须 `implements \App\Contracts\LabeledEnum`，`label()` 必须用 `__()`

```php
enum Role: string implements LabeledEnum
{
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('app.roles.admin'),
        };
    }
}
```

### 输出规范

- **单值字段**：Data 中增加 `*_label` 字段，来自 `$enum->label()`
- **下拉/筛选**：PageProps 中增加 `*_options`，使用 `EnumOptionData::fromCases()` 生成
- **校验同源**：`rules()` 必须基于 `cases()` 或业务限定的 `assignableCases()` 生成 `Rule::in(...)`，让"校验可选项"与"页面 options 输出"来自同一处

```php
$roles = array_map(static fn (Role $r) => $r->value, Role::assignableCases());
return [
    'role' => ['required', Rule::in($roles)],
];
```

### 边界转换

- 业务层（Action `handle()`）优先使用 Enum 类型，不要接收 string
- 转换发生在入口边界：`asController()`、`Form*Data`
- `tryFrom()` 仅用于入口边界、原始 SQL row、第三方 payload、历史数据等防御性解析；不要把宽松类型扩散到核心业务签名
- PageProps / Data 可直接声明 Backed Enum，`Data::toArray()` 会自动序列化为 value；不要为了前端序列化手动改成 `?string` 再传 `$enum?->value`
- 前端需要强类型时在组件内对 `value` 做收窄（如 `value as Role`）

### 多语言 key 组织

- 按领域文件归档：`lang/*/app.php` → `app.roles.*`
- 优先使用嵌套 key 管理子概念

## Toast 消息规范

### 错误处理

抛出 `BusinessException`，前端自动拦截显示 error toast：

```php
throw new BusinessException(__('app.cannot_delete'));
```

### 成功消息：仅在结果不可见时才发 toast

- **不发 toast**：常规 CRUD、Switch 切换、排序、恢复（列表刷新本身就是反馈）
- **保留 toast**：连接测试、发送测试邮件、修改密码、重置他人 2FA 等结果不可见的操作

### 警告/提示消息

需要提醒用户但操作未失败时（如部分降级、依赖项缺失），可使用 `warning` / `info` 类型 toast。判断标准同上：结果是否在 UI 上直接可见。

```php
Inertia::flash('toast', ['type' => 'warning', 'message' => __('...')]);
Inertia::flash('toast', ['type' => 'info', 'message' => __('...')]);
```

### Toast 机制

- `BusinessException` → 前端自动拦截显示 error toast
- `Inertia::flash('toast')` → 前端自动显示对应类型 toast
- 无需在前端手动调用 toast 方法

### 业务异常优先级

- 跨字段 / 跨资源 / 前置条件类失败 → 优先 `BusinessException`（toast）
- 只有当错误必须绑定到具体输入项时，才使用字段级验证错误
- 前端即使做了提交前拦截，后端也必须保留校验

## 多语言（后端）

```
lang/
  ├─ en/
  │   ├─ auth.php
  │   └─ app.php
  └─ zh_CN/
      ├─ auth.php
      └─ app.php
```

- 按领域文件归档，避免碎文件
- 优先使用嵌套 key 管理同一领域下的子概念

## 分页约定

页面列表分页统一 **每页 10 条**，取值一律引用 `SimplePaginationData::DEFAULT_PER_PAGE`，禁止另写 `15` / `20` 之类的字面量：

```php
// Action 签名默认值
public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowContactListPagePropsData

// asController 解析 per_page 查询参数
$perPage = (int) $request->query('per_page', SimplePaginationData::DEFAULT_PER_PAGE);

// 不接受 per_page 参数的页面直接用常量
->paginate(SimplePaginationData::DEFAULT_PER_PAGE, ['*'], 'page', $page)
```

- 分页元信息用 `SimplePaginationData::fromPaginator()` 下发，前端用 `PaginationNavigator` 渲染
- 允许调用方传 `per_page` 的页面，仍按各自业务上限 clamp（如 `max(1, min($perPage, 50))`）；**上限不受本约定约束**，只统一默认值
- 排序字段不唯一时（如 `closed_at`、`created_at`）必须补一个唯一字段兜底（通常是 `id`），否则并列记录在页边界会重复或漏出
- **本约定只管「翻页看列表」的页面分页**。滚动加载（会话消息 cursor 分页、联系人时间线）和内部批量召回（TNTSearch recall）不属于此列，按各自场景定值

## PHP 8.5 写法约定

- **列表首尾元素**：用 `array_first()` / `array_last()`（空数组返回 null）；不要写 `$xs[array_key_last($xs)]`、`$xs[0] ?? null`，也不要用会移动内部指针的 `end()` / `reset()`。确实需要"键"本身时才用 `array_key_first()` / `array_key_last()`
- **管道运算符 `|>`**：三层以上的单参数函数嵌套链按数据流向书写，如 `array_merge($a, $b) |> array_unique(...) |> array_values(...)`；回调不在首参的函数（`array_map` 等）不必硬套管道，两层以内的嵌套保持原样即可
- **URL host 解析**：凡涉及安全判定（Origin/Referer 白名单、SSRF 校验、host 比对）必须用 `Uri\WhatWg\Url::parse()?->getAsciiHost()`（浏览器同款语义，IDN 归一为 ASCII），**禁止用 `parse_url()` 做安全判定**；非安全场景的普通取值可继续用 `parse_url()`
- **`#[\NoDiscard]`**：返回值被忽略即构成 bug 的校验/判定方法（如 SSRF 检查、白名单判定）加上该注解并写明原因

---

# 前端规范

## UI 组件库（Reka UI）

**必须优先使用 `resources/js/components/ui/` 中已有的组件**。

可用组件：
- **基础**: Button, Input, Label, Badge, Avatar
- **布局**: Card, Separator, Sidebar
- **反馈**: Dialog, Sheet, Toast, Alert, Skeleton
- **表单**: Select, Checkbox, InputOTP, Switch
- **导航**: Breadcrumb, NavigationMenu, DropdownMenu
- **其他**: Collapsible, Tooltip, Popover, Spinner

### Switch 组件

项目里的 `Switch` 封装自 Reka UI `SwitchRoot`，绑定 API 是 `modelValue` / `update:modelValue`。

- 双向绑定必须使用 `v-model`，如 `<Switch v-model="form.enabled" />`
- 显式绑定必须使用 `:model-value` + `@update:model-value`
- 禁止使用 `v-model:checked`、`:checked` 或 `@update:checked`，这些不是当前封装暴露的 API，会导致状态不同步
- 表单提交需要原生字段时，按现有模式额外放一个 hidden input，不要依赖 Switch 自动生成字段

### 图标（@lucide/vue）

```vue
<script setup lang="ts">
import { Search, Trash2 } from '@lucide/vue';
</script>
<template>
  <Search class="h-4 w-4" />
</template>
```

- 尺寸用 Tailwind 类控制：`h-4 w-4` / `h-5 w-5`
- 颜色跟随文本颜色：通过父级 `text-*` 或图标自身 `text-*`
- 图标按钮使用 Button 组件并补充 `aria-label` 或 Tooltip
- 禁止随意引入多个图标库
- 禁止内联复制粘贴大量 SVG（除非作为品牌图标且需复用）

## 表单布局

### 单列优先

设置类、详情类页面的表单字段**默认采用单列垂直堆叠**：

```vue
<Form>
  <div class="space-y-5">
    <div class="grid gap-2">
      <Label for="name" required>{{ t('渠道名称') }}</Label>
      <Input id="name" name="name" required />
      <InputError class="mt-2" :message="errors.name" />
    </div>
  </div>
</Form>
```

- `Input` / `SelectTrigger` 始终撑满容器宽度（`class="w-full"`）
- 不要用 `md:grid-cols-2` 把表单字段拼成两列
- **例外**：正文区与右侧预览/摘要的页面级左右分栏（如 `xl:grid-cols-[minmax(0,1fr)_22rem]`）不属于表单字段排版；语义强相关的窄字段成组排版（如颜色+透明度、起始日期+结束日期）可用 `flex gap-3` 并排

### 表单视觉风格

- 表单字段默认直接在页面或对话框内容区垂直排列，不要为了"层次感"额外分组、加边框或包成 Card
- 不要把表单拆成多个卡片区块，也不要在 Card 里再嵌套 Card；设置类页面尤其应保持克制、连续、易扫描
- 分组只在业务语义强、字段数量较多且已有相邻页面采用同类结构时使用；优先用简短标题、说明文字和 `space-y-*` 间距，不用装饰性容器
- Card 仅用于重复项、弹窗/抽屉内已有组件约定、或确实需要框定的独立工具；不要把普通表单字段卡片化

### 必填字段标记

凡是后端校验为必填的字段，`<Label>` 必须声明 `required`，让用户在填写前看到红色 `*`：

- `Label` 的 `required` 与控件的 `required` 应一一对应
- 不要在 Label 文本里手动拼接 `*`，统一通过 `required` prop 渲染
- `Label` 渲染的 `*` 自带 `aria-hidden="true"`，无障碍语义由控件自身的 `required` 提供

### Props 规范

- 优先直接使用 `props.xxx`，避免无意义的 computed 别名
- 仅对"派生状态"使用 computed
- 需要可变状态时用本地 ref

### 交互稳定性

- 表单保存前后主要布局保持稳定
- 字段级操作放到字段附近，不要堆到底部
- 敏感字段用图标级次要操作（清除/显示）

## 列表筛选交互

列表页面的「搜索 + 多维度筛选」必须遵循统一的工具条 + Popover 模式：

```
HeadingSmall (title + description)
[Search]  [筛选 ▾ Badge=N]  [可选主操作按钮]
List / Table
```

### Popover 结构

- 「筛选」按钮：`Button variant="outline" size="sm"`，前缀 `ListFilter` 图标，右侧 `Badge` 显示激活数
- 单一 `Popover`，宽度 `w-104`，`align="end"`
- 顶栏含 Tab 切换 + 「清空全部」按钮
- Tab 内容用 `v-show` 切换（不是 `v-if`）
- 各 Tab 由独立 `*Panel.vue` 组件提供

```vue
<!-- 筛选 Popover 代码骨架 -->
<Popover v-model:open="filterPanelOpen">
  <PopoverTrigger as-child>
    <Button variant="outline" size="sm">
      <ListFilter class="mr-1.5 h-4 w-4" />
      {{ t('筛选') }}
      <Badge v-if="totalActiveFilterCount > 0" class="ml-1.5 h-5 min-w-5 px-1 text-xs">
        {{ totalActiveFilterCount }}
      </Badge>
    </Button>
  </PopoverTrigger>
  <PopoverContent class="w-104 p-0" align="end">
    <!-- 顶栏：Tab 切换 + 清空 -->
    <div class="flex items-center justify-between gap-2 border-b px-3 py-2">
      <div class="flex rounded-md border bg-background p-0.5 text-xs">
        <button type="button" :class="activeTab === 'basic' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'" @click="activeTab = 'basic'">
          {{ t('基本') }}<span v-if="activeBasicCount > 0" class="ml-1 opacity-70">{{ activeBasicCount }}</span>
        </button>
        <!-- 其它 tab 同理 -->
      </div>
      <button v-if="totalActiveFilterCount > 0" type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="clearAllFilters">
        {{ t('清空全部') }}
      </button>
    </div>
    <!-- Tab 内容：v-show 切换，不用 v-if -->
    <div v-show="activeTab === 'basic'"><ListFilterBasicPanel ... /></div>
    <div v-show="activeTab === 'attributes'"><ListFilterAttributePanel ... /></div>
    <div v-show="activeTab === 'tags'"><ListFilterTagPanel ... /></div>
  </PopoverContent>
</Popover>
```

### Tab 划分

- **基本（basic）**：低基数枚举/状态筛选（Select）
- **属性（attributes）**：自定义属性筛选，`max-h-112 overflow-y-auto`
- **标签（tags）**：标签包含/排除筛选
- Tab 总数 ≤ 4

### 计数规则

- **基本 Tab**：每个 Select 当前值是否为 `'all'`（或等价的"未筛选"哨兵值），非 `'all'` 记 1，相加
- **属性 Tab**：`Object.keys(normalizedAttributeFilters).length`，区间型字段算一个 key
- **标签 Tab**：`untaggedOnly` 单独算 1；否则 `includeIds.length + excludeIds.length`
- **总计数**：直接相加；触发器 `Badge` 与 Tab 角标共用同一组计算属性

### Sentinel 常量

```ts
const FILTER_EMPTY_VALUE = '__helmdesk_filter_empty__';
const BOOLEAN_TRUE_FILTER_VALUE = '__helmdesk_filter_true__';
const BOOLEAN_FALSE_FILTER_VALUE = '__helmdesk_filter_false__';
```

`'all'` 仅用于基本 Tab 的内置三态 Select（包含「全部 X」的项）。

### 状态归一化

- 所有筛选状态由父页面 ref 持有，Panel 不存储中间状态
- 变更立即触发 `navigate()`，URL 与服务端 props 始终是真理之源
- 使用 `preserveState: true` 时必须写 `watch(() => props.xxx, ...)` 反同步

### 命名约定

- 各 Tab 的内容组件命名为 `*FilterBasicPanel.vue` / `*FilterAttributePanel.vue` / `*FilterTagPanel.vue`，文件位置紧邻其消费页面
- 内容面板必须是「内容版」（不带自己的 `Popover`），方便复用
- 不要做页面级 `*FilterPopover.vue`；如有遗留，重构成内容版 `*Panel.vue`
- 所有筛选文案必须走 `useI18n()`，不要硬编码中文

### 反模式

- 不要使用多个并列 Popover
- 不要把 Select 直接铺在工具条上
- 不要用 `v-if` 切换 Tab
- 每个 Tab 不要各自一个「清空」按钮

## 路由和多语言

### Wayfinder 路由

```typescript
import { app } from '@/actions';
router.visit(app.dashboard.get({ slug: 'my-app' }));
// 禁止硬编码路径，统一使用 Wayfinder 路由。
```

### 前端多语言

语言文件位于 `resources/js/locales/`，**key 使用中文**：

```vue
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
const { t } = useI18n();
</script>
<template>
  <button>{{ t('保存') }}</button>
</template>
```

### 类型使用

```typescript
import type { ListContactItemData } from '@/types/generated';
const props = defineProps<{ contact: ListContactItemData }>();
```

## 时间显示

**必须使用 `formatDateTime()` 显示后端传入的时间**：

```vue
<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime';
const { formatDateTime } = useDateTime();
</script>
<template>
  <p>{{ formatDateTime(props.created_at) }}</p>
  <p>{{ formatDateTime(props.created_at, 'YYYY-MM-DD') }}</p>
</template>
```

- 自动转换用户时区、适配多语言
- 禁止直接显示 `{{ props.created_at }}`
- 禁止在组件内重复实现 dayjs 逻辑

## 状态反馈

### 状态指示器去重

- 可切换状态：只放 `Switch`，不要再加 `Badge`；通过 `aria-label` / `title` 提供无障碍信息和禁用原因
- 禁用原因和后续步骤放在 `Switch` 的 `title` 或独立引导卡片里，不要写成行内文字
- 只读状态：用 `Badge`
- 列表与详情页对同一状态的呈现方式应保持一致

### Toast 约定

- 常规 CRUD / 切换 / 排序 / 恢复：不弹 toast，依赖 UI 自身变化
- 结果不可见的操作：保留成功 toast
- 异常优先走 toast，不要做成常驻页面提示块

### 异常交互风格

页面中的业务异常与保存失败，**优先统一走 toast 提示**：
- 提交时异常（前置条件未满足、对象被引用无法删除、权限不足等）→ toast
- 仅在错误需要持续占位且用户必须边看边修正时，才使用页面内 Alert
- 禁止把一次性的业务异常做成长期占位的页面提示块
- 禁止同一类异常一会儿走 Alert、一会儿走 toast

### 颜色约定

系统后台采用黑白灰极简科技风：
- 提示使用 `text-muted-foreground` 或 `text-foreground`
- 选中态使用 `border-foreground bg-foreground text-background`
- **禁止使用** `text-green-*`、`bg-green-*`、`text-emerald-*` 等彩色样式表达状态

---

# 注释规范

## 总体风格

- 注释统一使用中文
- 简洁具体贴近业务，不写空泛描述
- PHP 优先使用 PHPDoc 块
- 每个 PHP 方法都要有简短功能性 PHPDoc
- 已存在英文注释时应改成中文

### 直述型风格（硬约束）

注释一律用**直述型**描述当前行为与约束，站在"不了解代码历史的读者"视角写：

- **禁止历史对比措辞**：不写「改为 / 改成 / 不再 / 此前 / 原来 / 旧版 / 旧逻辑 / 新增了 / 替换了 / 迁移到」等以旧实现为参照的说法；直接陈述现在是什么、为什么必须这样
- **禁止辩解式注释**：不为"这次改动为什么对"辩护（那是 PR 描述的事）；注释只服务下一个读代码的人
- 判定标准：一个不知道任何历史的读者读了是否受益；不受益则删除，受益但带历史参照则改写成直述
- 允许的"时间性"表述：描述**运行时/数据生命周期**的先后（如「撤回后的消息不参与检索」「重投时跳过已投递记录」）不算历史对比；描述**外部系统版本差异**（如浏览器/第三方 API 行为）可保留版本限定语
- 例外：`database/migrations/**` 与 `app/Console/Commands/OneOff/**` 本身就是"变更记录"，其注释可以描述变更前后差异

## 各文件类型要求

| 文件类型 | 要求 |
|---------|------|
| Action | 类级 PHPDoc 说明具体做什么；`handle()`、`asController()` 都要有简短 PHPDoc |
| Data | 类级 PHPDoc 写明对应前端页面/组件/表单；`Form*Data` 写明提交来源 |
| Model | 类体内第一段（`class ... {` 之后、trait/属性之前）放业务说明；`@property` PHPDoc 由 `model-doc:generate` 维护 |
| Service | 类和每个方法都要 PHPDoc；写明封装的职责 |
| Enum | 类级 PHPDoc 说明领域约束或前端用途 |
| Settings | 类级 PHPDoc + 每个 public 字段的字段级 PHPDoc |
| Vue 页面 | 文件顶部 HTML 注释说明业务界面 |
| TS/JS | 文件顶部块注释说明用途 |

- `resources/js/components/**` 内的组件不强制要求文件级说明
- `resources/js/actions/**` 和 `resources/js/routes/**` 是 Wayfinder 生成文件，不手改
- `resources/js/locales/**` 文件说明应写明这是前端国际化文案
- `resources/js/pages/**` 文件说明应写明页面承接的业务界面及消费的后端 Data/PageProps
- 注释例外：第三方生成代码、专有名词、协议字段或英文原文更准确时可保留英文
- 删除或改写明显 AI 味的注释：避免重复代码本身、避免解释显而易见的赋值和 if 判断
- `@param` / `@return` 这类纯类型标注不算完整的方法注释，仍需简短功能性说明
- 构造函数和私有业务辅助方法也要有简短 PHPDoc
- Middleware 必须有类级 PHPDoc，说明它在请求链路中注入/校验/共享了什么
- Contract/接口必须有类级 PHPDoc，说明业务层依赖这个抽象的目的

### Composer 生成流程

如启用 `composer dump-autoload` 的 `post-autoload-dump` 自动生成流程，应包含 `@php artisan model-doc:generate --ansi` 和 `@php artisan typescript:transform --ansi`。这两个命令会重写模型 PHPDoc 和生成类型文件，人工注释必须放在不会被生成器覆盖的位置。

---

# 测试规范

- **只测核心业务逻辑的边界条件**：测试聚焦后端核心业务逻辑（Action / Service / 纯函数转换 / 状态流转 / 权限）里的边界与分支条件，验证真实业务行为和稳定契约
- **前端、文案、样式一律不做单元测试**：Vue 组件、UI 交互、i18n 文案、CSS/样式、纯展示层都不写单元测试；这些靠人工验收，不用测试锁死
- 测试应验证当前正向业务行为和稳定契约，不写针对旧逻辑、旧样式、旧字段缺失的反向断言；删除或重构旧实现时，不通过“确保旧实现不存在”来锁死内部结构
- PHP 使用 Pest：`php artisan make:test --pest {name}`
- 优先运行最小范围：`php artisan test --compact --filter=testName`
- 测试使用 SQLite；搜索测试使用 TNTSearch；附件存储用 `Storage::fake()` + `fakeAttachmentStorage()` 助手隔离
- 修改 PHP 文件后运行 `vendor/bin/pint --dirty --format agent`

## 低价值测试判定（不要写，发现可删）

宁缺毋滥：覆盖率不是目的，验证真实业务行为/契约才是。以下形态属"为测而测"，不要新增，存量可删：

- **同义反复**：断言 config 值等于其自身、容器能解析出某类、断言刚 `set` 的值又读出来比一遍
- **断言提示词/常量字符串**：`toContain('某中文词')` 比对 prompt 文本、断言固定文案——改措辞就红却不验证任何行为
- **工厂/注册表结构快照**：只断言工厂产出对象的类型/属性、反射读 protected 属性比对（protocol→provider 类映射这类纯分派除外，仍需配一条"未登记即抛"的有效断言）
- **弱断言框架行为**：只断言 200/重定向/渲染了某组件/默认值，未触及业务分支或权限
- **mock 后只验证 mock 被调用**：mock 业务 Action 再断言其返回值被原样透传
- **脆弱守护/基准**：正则扫源码比对 i18n key 是否存在；依赖统计阈值（召回率 `>=0.x`）的基准断言
- **判定相反的"高价值"信号（务必保留）**：走真实 HTTP+DB/TNTSearch 的 CRUD/权限/筛选/分页/状态流转；加密落库、token、外部 ID 命名空间等稳定契约；纯函数转换（分词器、分块器、消息映射、变量解析）；AI 功能测试只要断言的是**落库结果**（来源标记、人工值不被覆盖、幻觉值丢弃、阈值过滤）而非提示词，即属高价值

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/octane (OCTANE) - v2
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v11
- laravel/wayfinder (WAYFINDER) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/v12 rules ===

# Laravel 13

- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 13 Structure

- In Laravel 13, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 13 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== octane/core rules ===

# Octane

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- The Laravel container's `scoped` method may be used as a safe alternative to `singleton`.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or `bind()` instead:

```php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
```

- Never append to static properties, as they accumulate in memory across requests.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
