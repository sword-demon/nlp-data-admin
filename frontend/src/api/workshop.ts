import { http } from "./client";
import type { ApiResponse } from "./client";

// ============ 类型定义（与后端 Agent 输出字段严格对齐） ============

export interface TitleCandidate {
  title: string;
  analysis: string;
  score: number;
}

/** 大纲节点（扁平化）：后端以 {id, text, level, parent_id} 形式返回。 */
export interface OutlineNode {
  id: number;
  text: string;
  level: number;
  parent_id: number | null;
}

export interface Outline {
  markdown: string;
  nodes: OutlineNode[];
}

export type ImageType =
  | "pexels"
  | "mermaid"
  | "iconify"
  | "emoji"
  | "svg"
  | "nanobanana";

export interface ImageAnalysis {
  placeholder_id: string;
  context: string;
  keywords: string[];
  suggested_type: ImageType;
  reasoning: string;
}

export interface GeneratedImage {
  placeholder_id: string;
  type: ImageType;
  /** 实际命中的策略名（可能因 fallback 与 type 不同） */
  source: string;
  url: string;
  /** 上传 OSS 前的原始 URL（若未启用 OSS 与 url 相同） */
  original_url?: string;
  keyword: string;
  alt: string;
  attribution: string | null;
  mime: string;
  raw: string | null;
  is_placeholder: boolean;
}

export type WorkshopStatus =
  | "draft"
  | "topic_researching"
  | "title_generating"
  | "title_selecting"
  | "outline_generating"
  | "outline_editing"
  | "content_generating"
  | "image_analyzing"
  | "image_generating"
  | "completed"
  | "failed";

export interface WorkshopStatusPayload {
  article_id: number;
  status: WorkshopStatus;
  is_terminal: boolean;
  is_waiting_user: boolean;
  topic: string;
  style: string;
  selected_title: string | null;
  generated_titles: TitleCandidate[] | null;
  title_supplement: string | null;
  outline: Outline | null;
  word_count: number;
  updated_at: string | null;
}

export interface WorkshopResult {
  article_id: number;
  status: WorkshopStatus;
  title: string;
  selected_title: string | null;
  topic: string;
  style: string;
  outline: Outline | null;
  content: string;
  images: GeneratedImage[] | null;
  word_count: number;
  ai_model: string | null;
  created_at: string | null;
  updated_at: string | null;
}

// ============ REST API ============

export interface CreateArticleResponse {
  article_id: number;
  status: WorkshopStatus;
  topic: string;
  style: string;
  titles: TitleCandidate[];
  /**
   * 选题研究降级标志：true 表示后端未能拿到研究资料（Exa 关闭 / 限流 / 异常 /
   * 零结果 / 锁等待超时等），已走基础模式生成标题。仅用于前端微量提示，
   * 成功路径不应返回或为 false。
   */
  research_fallback?: boolean;
}

export async function createArticle(
  topic: string,
  style: string,
): Promise<CreateArticleResponse> {
  const { data } = await http.post<ApiResponse<CreateArticleResponse>>(
    "/workshop/create",
    { topic, style },
  );
  return data.data;
}

export async function selectTitle(
  articleId: number,
  titleIndex: number,
  supplement: string,
): Promise<{
  outline: Outline;
  status: WorkshopStatus;
  selected_title: string;
}> {
  const { data } = await http.post<
    ApiResponse<{
      article_id: number;
      status: WorkshopStatus;
      selected_title: string;
      outline: Outline;
    }>
  >(`/workshop/${articleId}/select-title`, {
    title_index: titleIndex,
    supplement,
  });
  return data.data;
}

export async function updateOutline(
  articleId: number,
  outline: Outline,
): Promise<void> {
  await http.put<ApiResponse<unknown>>(`/workshop/${articleId}/outline`, {
    outline,
  });
}

export async function fetchStatus(
  articleId: number,
): Promise<WorkshopStatusPayload> {
  const { data } = await http.get<ApiResponse<WorkshopStatusPayload>>(
    `/workshop/${articleId}/status`,
  );
  return data.data;
}

export async function fetchResult(articleId: number): Promise<WorkshopResult> {
  const { data } = await http.get<ApiResponse<WorkshopResult>>(
    `/workshop/${articleId}/result`,
  );
  return data.data;
}

// ============ SSE 流式生成 ============

export interface StreamHandlers {
  onState?: (state: WorkshopStatus, extra: Record<string, unknown>) => void;
  onContentChunk?: (chunk: string) => void;
  onContentCompleted?: (payload: {
    word_count: number;
    placeholder_count: number;
  }) => void;
  onImageAnalyzed?: (payload: { analyses: ImageAnalysis[] }) => void;
  /** 单张配图完成（Phase 4 新增，逐张推送） */
  onImageReady?: (payload: GeneratedImage) => void;
  onImageGenerated?: (payload: { images: GeneratedImage[] }) => void;
  /** 正文 placeholder 全部替换为真实 URL 后的最终内容（Phase 4 新增） */
  onContentUpdated?: (payload: { content: string; word_count: number }) => void;
  onError?: (payload: { message: string; agent?: string }) => void;
  onDone?: () => void;
  onTransportError?: (event: Event) => void;
}

/**
 * 打开创作工坊 SSE 流。
 *
 * 注意：原生 EventSource 无法传 Authorization header，JWT 走 ?token= query，
 * 后端 JwtAuthMiddleware 已兼容。
 */
export function startGenerationStream(
  articleId: number,
  handlers: StreamHandlers,
): EventSource {
  const token = localStorage.getItem("token") ?? "";
  const url = `/api/workshop/${articleId}/generate-stream?token=${encodeURIComponent(
    token,
  )}`;

  const es = new EventSource(url);

  const parseJson = <T>(raw: string): T | null => {
    try {
      return JSON.parse(raw) as T;
    } catch {
      return null;
    }
  };

  es.addEventListener("state", (evt) => {
    const payload = parseJson<
      { state: WorkshopStatus } & Record<string, unknown>
    >((evt as MessageEvent).data);
    if (payload) {
      const { state, ...extra } = payload;
      handlers.onState?.(state, extra);
    }
  });

  es.addEventListener("content_chunk", (evt) => {
    const payload = parseJson<{ text: string }>((evt as MessageEvent).data);
    if (payload) handlers.onContentChunk?.(payload.text);
  });

  es.addEventListener("content_completed", (evt) => {
    const payload = parseJson<{
      word_count: number;
      placeholder_count: number;
    }>((evt as MessageEvent).data);
    if (payload) handlers.onContentCompleted?.(payload);
  });

  es.addEventListener("image_analyzed", (evt) => {
    const payload = parseJson<{ analyses: ImageAnalysis[] }>(
      (evt as MessageEvent).data,
    );
    if (payload) handlers.onImageAnalyzed?.(payload);
  });

  es.addEventListener("image_ready", (evt) => {
    const payload = parseJson<GeneratedImage>((evt as MessageEvent).data);
    if (payload) handlers.onImageReady?.(payload);
  });

  es.addEventListener("image_generated", (evt) => {
    const payload = parseJson<{ images: GeneratedImage[] }>(
      (evt as MessageEvent).data,
    );
    if (payload) handlers.onImageGenerated?.(payload);
  });

  es.addEventListener("content_updated", (evt) => {
    const payload = parseJson<{ content: string; word_count: number }>(
      (evt as MessageEvent).data,
    );
    if (payload) handlers.onContentUpdated?.(payload);
  });

  es.addEventListener("error_event", (evt) => {
    const payload = parseJson<{ message: string; agent?: string }>(
      (evt as MessageEvent).data,
    );
    if (payload) handlers.onError?.(payload);
  });

  es.addEventListener("done", () => {
    handlers.onDone?.();
    es.close();
  });

  es.onerror = (event: Event) => {
    handlers.onTransportError?.(event);
  };

  return es;
}
