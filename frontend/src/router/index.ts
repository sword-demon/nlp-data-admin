import { createRouter, createWebHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";
import { message } from "ant-design-vue";
import { useAuthStore } from "@/stores/auth";

const routes: RouteRecordRaw[] = [
  {
    path: "/login",
    name: "Login",
    component: () => import("@/pages/Login.vue"),
    meta: { requiresGuest: true },
  },
  {
    path: "/register",
    name: "Register",
    component: () => import("@/pages/Register.vue"),
    meta: { requiresGuest: true },
  },
  {
    path: "/",
    component: () => import("@/layouts/DefaultLayout.vue"),
    meta: { requiresAuth: true },
    children: [
      {
        path: "",
        name: "Home",
        component: () => import("@/pages/Home.vue"),
      },
      {
        path: "workshop",
        name: "Workshop",
        component: () => import("@/pages/Workshop.vue"),
      },
      {
        path: "articles",
        name: "ArticleList",
        component: () => import("@/pages/ArticleList.vue"),
      },
      {
        path: "articles/:id(\\d+)",
        name: "ArticleDetail",
        component: () => import("@/pages/ArticleDetail.vue"),
      },
      {
        path: "profile",
        name: "Profile",
        component: () => import("@/pages/Profile.vue"),
      },
      {
        path: "vip",
        name: "VipCenter",
        component: () => import("@/pages/VipCenter.vue"),
      },
      {
        path: "orders",
        name: "OrderList",
        component: () => import("@/pages/OrderList.vue"),
      },
      {
        path: "dashboard",
        name: "Dashboard",
        component: () => import("@/pages/Dashboard.vue"),
        meta: { requiresAdmin: true },
      },
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// 路由守卫：
// - 未登录访问受保护页面 → /login（带 redirect）
// - 已登录访问 /login|/register → 首页
// - 非 admin 访问 requiresAdmin 页面 → 首页 + 提示
router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { path: "/login", query: { redirect: to.fullPath } };
  }
  if (to.meta.requiresGuest && auth.isLoggedIn) {
    return { path: "/" };
  }
  if (to.meta.requiresAdmin && auth.user?.role !== "admin") {
    message.error("仅管理员可访问");
    return { path: "/" };
  }
  return true;
});

export default router;
