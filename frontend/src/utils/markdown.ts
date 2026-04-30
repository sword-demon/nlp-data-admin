import MarkdownIt from "markdown-it";
import hljs from "highlight.js/lib/common";

// highlight.js 的 github 样式在 MarkdownRenderer 组件侧静态引入即可。
// 这里集中维护 markdown-it 实例，保证全站渲染一致。

/**
 * 创建一个启用代码高亮的 markdown-it 实例。
 * - html: false — 防 XSS（屏蔽原始 HTML 标签，图片/链接走 markdown 语法）
 * - linkify: true — 裸 URL 自动转链接
 * - breaks: true — 单换行符转 <br>，契合 AI 流式输出
 */
export function createMarkdown(): MarkdownIt {
  return new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
    highlight(code, lang) {
      if (lang && hljs.getLanguage(lang)) {
        try {
          return `<pre class="hljs"><code>${
            hljs.highlight(code, { language: lang, ignoreIllegals: true }).value
          }</code></pre>`;
        } catch {
          // fallthrough
        }
      }
      try {
        return `<pre class="hljs"><code>${hljs.highlightAuto(code).value}</code></pre>`;
      } catch {
        return `<pre class="hljs"><code>${escapeHtml(code)}</code></pre>`;
      }
    },
  });
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

/** 全局单例，避免重复创建解析器。 */
export const md: MarkdownIt = createMarkdown();
