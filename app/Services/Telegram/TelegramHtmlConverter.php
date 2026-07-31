<?php

namespace App\Services\Telegram;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * 把 AI / 客服回复中的 CommonMark（GFM）文本转换成 Telegram sendMessage 支持的 HTML 子集。
 *
 * Telegram 的 parse_mode=HTML 只接受有限标签（b/i/u/s/code/pre/a/blockquote 等），
 * 直接把标准 Markdown 当 MarkdownV2 发会因未转义特殊字符频繁失败。这里先用 league/commonmark
 * 渲染成标准 HTML，再遍历 DOM 把块级标签降级为换行、未知标签拆解为纯文本，只保留 Telegram 白名单标签。
 */
class TelegramHtmlConverter
{
    private readonly GithubFlavoredMarkdownConverter $markdown;

    /**
     * 初始化底层 Markdown 渲染器（启用 GFM：删除线、自动链接、任务列表）。
     */
    public function __construct()
    {
        $this->markdown = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * 将 Markdown 文本转换为 Telegram 可直接发送的 HTML 字符串。
     */
    public function convert(string $markdown): string
    {
        $html = $this->markdown->convert($markdown)->getContent();

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // <?xml encoding> 强制按 UTF-8 解析 commonmark 输出的 HTML 片段。
        $dom->loadHTML('<?xml encoding="UTF-8"?><html><body>'.$html.'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);

        $rendered = '';
        foreach ($body->childNodes as $child) {
            $rendered .= $this->renderNode($child);
        }

        // 合并块级降级产生的多余空行，并去除首尾空白。
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /**
     * 递归把单个 DOM 节点渲染为 Telegram HTML 片段：文本转义、白名单标签保留、块级标签降级为换行。
     */
    private function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->escapeText((string) $node->nodeValue);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $this->renderNode($child);
        }

        return match (strtolower($node->nodeName)) {
            'b', 'strong' => "<b>{$inner}</b>",
            'i', 'em' => "<i>{$inner}</i>",
            'u', 'ins' => "<u>{$inner}</u>",
            's', 'strike', 'del' => "<s>{$inner}</s>",
            'code' => $this->renderCode($node, $inner),
            'pre' => "<pre>{$inner}</pre>",
            'a' => $this->renderLink($node, $inner),
            'blockquote' => '<blockquote>'.trim($inner)."</blockquote>\n",
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '<b>'.trim($inner)."</b>\n\n",
            'p' => trim($inner)."\n\n",
            'li' => '• '.trim($inner)."\n",
            'br', 'hr' => "\n",
            'img' => $this->escapeText($node->getAttribute('alt')),
            // ul / ol / table / div / span 等容器标签拆解，仅保留子节点内容。
            default => $inner,
        };
    }

    /**
     * 渲染 code 标签：代码块（pre 内）保留 language-* 类名，行内代码去除属性。
     */
    private function renderCode(DOMElement $node, string $inner): string
    {
        $class = $node->getAttribute('class');
        if (str_starts_with($class, 'language-')) {
            return '<code class="'.$this->escapeAttribute($class).'">'.$inner.'</code>';
        }

        return "<code>{$inner}</code>";
    }

    /**
     * 渲染链接：保留 href，缺失时降级为纯文本。
     */
    private function renderLink(DOMElement $node, string $inner): string
    {
        $href = $node->getAttribute('href');
        if ($href === '') {
            return $inner;
        }

        return '<a href="'.$this->escapeAttribute($href).'">'.$inner.'</a>';
    }

    /**
     * 转义文本内容中的 HTML 保留字符。
     */
    private function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 转义标签属性值。
     */
    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
