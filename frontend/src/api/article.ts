import { http } from "./client";
import type { ApiResponse } from "./client";
import type { GeneratedImage, Outline, WorkshopStatus } from "./workshop";

/** 列表项（轻量，不含 content / outline / images） */
export interface ArticleSummary {
  id: number;
  title: string;
  topic: string;
  style: string;
  status: WorkshopStatus;
  word_count: number;
  ai_model: string | null;
  created_at: string | null;
  updated_at: string | null;
}

/** 详情（含完整正文与配图） */
export interface ArticleDetail extends ArticleSummary {
  selected_title: string | null;
  outline: Outline | null;
  content: string;
  images: GeneratedImage[] | null;
}

export interface ArticleListResult {
  list: ArticleSummary[];
  total: number;
  page: number;
  limit: number;
}

export interface ArticleListParams {
  page?: number;
  limit?: number;
  status?: string;
  keyword?: string;
}

export async function fetchArticles(
  params: ArticleListParams = {},
): Promise<ArticleListResult> {
  const { data } = await http.get<ApiResponse<ArticleListResult>>("/articles", {
    params,
  });
  return data.data;
}

export async function fetchArticle(id: number): Promise<ArticleDetail> {
  const { data } = await http.get<ApiResponse<ArticleDetail>>(
    `/articles/${id}`,
  );
  return data.data;
}

export async function deleteArticle(id: number): Promise<void> {
  await http.delete<ApiResponse<{ id: number }>>(`/articles/${id}`);
}

// ============ 状态 → 文本 / 颜色映射 ============

export const ARTICLE_STATUS_LABEL: Record<string, string> = {
  draft: "草稿",
  topic_researching: "正在检索选题背景",
  title_generating: "生成标题中",
  title_selecting: "等待选标题",
  outline_generating: "生成大纲中",
  outline_editing: "等待编辑大纲",
  content_generating: "生成正文中",
  image_analyzing: "配图分析中",
  image_generating: "配图生成中",
  completed: "已完成",
  failed: "失败",
};

export const ARTICLE_STATUS_COLOR: Record<string, string> = {
  draft: "default",
  topic_researching: "processing",
  title_generating: "processing",
  title_selecting: "blue",
  outline_generating: "processing",
  outline_editing: "cyan",
  content_generating: "processing",
  image_analyzing: "purple",
  image_generating: "purple",
  completed: "green",
  failed: "red",
};
