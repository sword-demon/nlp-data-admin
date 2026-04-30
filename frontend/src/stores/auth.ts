import { defineStore } from "pinia";
import { ref, computed } from "vue";
import type { AuthUser, AuthTokenPayload } from "@/api/auth";
import * as authApi from "@/api/auth";

export const useAuthStore = defineStore("auth", () => {
  const token = ref<string>(localStorage.getItem("token") || "");
  const user = ref<AuthUser | null>(
    JSON.parse(localStorage.getItem("user") || "null"),
  );
  const expiresAt = ref<number>(
    Number(localStorage.getItem("token_expires_at") || 0),
  );

  const isLoggedIn = computed(
    () =>
      !!token.value &&
      (expiresAt.value === 0 || expiresAt.value * 1000 > Date.now()),
  );
  const quotaRemaining = computed(() =>
    user.value ? user.value.quota_total - user.value.quota_used : 0,
  );
  const isVip = computed(() =>
    user.value ? user.value.vip_level !== "free" : false,
  );

  function setSession(payload: AuthTokenPayload): void {
    token.value = payload.token;
    user.value = payload.user;
    expiresAt.value = payload.expires_at;
    localStorage.setItem("token", payload.token);
    localStorage.setItem("user", JSON.stringify(payload.user));
    localStorage.setItem("token_expires_at", String(payload.expires_at));
  }

  function setUser(newUser: AuthUser): void {
    user.value = newUser;
    localStorage.setItem("user", JSON.stringify(newUser));
  }

  function clear(): void {
    token.value = "";
    user.value = null;
    expiresAt.value = 0;
    localStorage.removeItem("token");
    localStorage.removeItem("user");
    localStorage.removeItem("token_expires_at");
  }

  async function login(email: string, password: string): Promise<void> {
    const payload = await authApi.login({ email, password });
    setSession(payload);
  }

  async function register(
    username: string,
    email: string,
    password: string,
  ): Promise<void> {
    const payload = await authApi.register({ username, email, password });
    setSession(payload);
  }

  async function fetchMe(): Promise<void> {
    const me = await authApi.getMe();
    setUser(me);
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout();
    } catch {
      // 忽略：即使后端调用失败也清理本地会话
    }
    clear();
  }

  return {
    token,
    user,
    expiresAt,
    isLoggedIn,
    quotaRemaining,
    isVip,
    setSession,
    setUser,
    clear,
    login,
    register,
    fetchMe,
    logout,
  };
});
