import { createRouter, createWebHistory } from "vue-router";
import type { RouteRecordRaw } from "vue-router";
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
        path: "dashboard",
        name: "Dashboard",
        component: () => import("@/pages/Dashboard.vue"),
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
    ],
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// 路由守卫：未登录访问受保护页面 → /login；已登录访问 /login|/register → 首页
router.beforeEach((to) => {
  const auth = useAuthStore();
  if (to.meta.requiresAuth && !auth.isLoggedIn) {
    return { path: "/login", query: { redirect: to.fullPath } };
  }
  if (to.meta.requiresGuest && auth.isLoggedIn) {
    return { path: "/" };
  }
  return true;
});

export default router;
