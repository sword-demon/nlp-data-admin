import axios from "axios";
import type { AxiosInstance } from "axios";

interface ApiResponse<T = unknown> {
  code: number;
  message: string;
  data: T;
}

const http: AxiosInstance = axios.create({
  baseURL: "/api",
  // 创作工坊的 select-title / update-outline 等同步请求需等待 AI 生成大纲（~30-60s），默认 30s 会 ERR_ABORTED。
  timeout: 120000,
  headers: {
    "Content-Type": "application/json",
  },
});

http.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      localStorage.removeItem("token_expires_at");
      // 登录页已实现；避免在登录/注册页自身触发循环跳转
      const path = window.location.pathname;
      if (path !== "/login" && path !== "/register") {
        window.location.href = `/login?redirect=${encodeURIComponent(path)}`;
      }
    }
    return Promise.reject(error);
  },
);

export function createEventSource(
  url: string,
  onMessage: (data: string) => void,
  onError?: (error: Event) => void,
): EventSource {
  const token = localStorage.getItem("token");
  // 注意：原生 EventSource 无法设置 Authorization header，只能走 query 传递
  // 后端 JwtAuthMiddleware 已兼容从 ?token= 读取
  const fullUrl = token
    ? `${url}${url.includes("?") ? "&" : "?"}token=${encodeURIComponent(token)}`
    : url;

  const es = new EventSource(fullUrl);

  es.onmessage = (event: MessageEvent) => {
    onMessage(event.data);
  };

  es.onerror = (event: Event) => {
    onError?.(event);
  };

  return es;
}

export { http };
export type { ApiResponse };
