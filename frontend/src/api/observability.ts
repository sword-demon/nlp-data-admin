import { http } from "./client";
import type { ApiResponse } from "./client";

// 对齐后端 ObservabilityService 响应结构。

export interface ObservabilityOverview {
  total: number;
  success: number;
  failed: number;
  running: number;
  success_rate: number;
  avg_duration_ms: number;
  p95_duration_ms: number;
  slowest_agent: { name: string; avg_duration_ms: number } | null;
  start_date: string;
  end_date: string;
}

export interface AgentStatRow {
  name: string;
  total: number;
  success: number;
  failed: number;
  success_rate: number;
  avg_duration_ms: number;
}

export interface DailyTrendRow {
  date: string;
  total: number;
  success: number;
  failed: number;
  success_rate: number;
  avg_duration_ms: number;
}

export interface SlowLogRow {
  id: number;
  user_id: number | null;
  article_id: number | null;
  agent_name: string;
  duration_ms: number;
  status: string;
  error_message: string | null;
  created_at: string | null;
}

export interface AgentLogRow {
  id: number;
  user_id: number | null;
  article_id: number | null;
  agent_name: string;
  input_summary: string;
  output_summary: string;
  duration_ms: number;
  status: string;
  error_message: string | null;
  created_at: string | null;
}

export interface UserActivity {
  user_id: number;
  total: number;
  success: number;
  failed: number;
  success_rate: number;
  avg_duration_ms: number;
  agents: Array<{ name: string; count: number }>;
  start_date: string;
  end_date: string;
}

export interface DateRangeParams {
  start_date?: string;
  end_date?: string;
}

const BASE = "/admin/observability";

export async function fetchOverview(
  params: DateRangeParams = {},
): Promise<ObservabilityOverview> {
  const { data } = await http.get<ApiResponse<ObservabilityOverview>>(
    `${BASE}/overview`,
    { params },
  );
  return data.data;
}

export async function fetchAgentStats(
  params: DateRangeParams = {},
): Promise<AgentStatRow[]> {
  const { data } = await http.get<ApiResponse<{ list: AgentStatRow[] }>>(
    `${BASE}/agents`,
    { params },
  );
  return data.data.list;
}

export async function fetchTrend(
  params: DateRangeParams = {},
): Promise<DailyTrendRow[]> {
  const { data } = await http.get<ApiResponse<{ list: DailyTrendRow[] }>>(
    `${BASE}/trend`,
    { params },
  );
  return data.data.list;
}

export async function fetchSlowAgents(
  threshold = 10000,
  limit = 20,
): Promise<{ threshold_ms: number; list: SlowLogRow[] }> {
  const { data } = await http.get<
    ApiResponse<{ threshold_ms: number; list: SlowLogRow[] }>
  >(`${BASE}/slow`, { params: { threshold, limit } });
  return data.data;
}

export interface LogsQuery {
  agent_name?: string;
  status?: string;
  limit?: number;
}

export async function fetchLogs(query: LogsQuery = {}): Promise<AgentLogRow[]> {
  const { data } = await http.get<ApiResponse<{ list: AgentLogRow[] }>>(
    `${BASE}/logs`,
    { params: query },
  );
  return data.data.list;
}

export async function fetchUserActivity(
  userId: number,
  params: DateRangeParams = {},
): Promise<UserActivity> {
  const { data } = await http.get<ApiResponse<UserActivity>>(
    `${BASE}/user/${userId}`,
    { params },
  );
  return data.data;
}

// ============ UI 辅助 ============

export const AGENT_LABEL: Record<string, string> = {
  title_generator: "标题生成",
  outline_generator: "大纲生成",
  content_generator: "正文生成",
  content_generator_stream: "正文生成(流)",
  image_analyzer: "配图分析",
  parallel_image_generator: "并行配图",
};

export const LOG_STATUS_COLOR: Record<string, string> = {
  success: "green",
  failed: "red",
  running: "blue",
};
