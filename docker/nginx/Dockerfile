# ============================================================
# Nginx + 前端静态资源 多阶段镜像
#
# 构建上下文：项目根（.），所以能同时访问 frontend/ 与 docker/nginx/
#
# Stage 1 frontend-builder
#   - 用 node:20-alpine 编译 Vue 前端为 dist/
#   - 构建期环境变量：VITE_API_BASE_URL（可选，通常走 Nginx 同源 /api，无需设置）
# Stage 2 nginx-runtime
#   - 基于 nginx:1.27-alpine
#   - 拷贝编译后的 dist 与自定义 Nginx 配置
# ============================================================

# ---------- Stage 1 ----------
FROM node:20-alpine AS frontend-builder

ARG VITE_API_BASE_URL=""
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL}

WORKDIR /app

# 充分利用层缓存：先拷贝锁文件
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund

# 拷贝源码并构建
COPY frontend/ ./
RUN npm run build

# ---------- Stage 2 ----------
FROM nginx:1.27-alpine AS nginx-runtime

LABEL maintainer="nlp-data-admin" \
    description="NLP Data Admin Nginx（反代后端 + 托管前端 SPA + SSE 支持）"

# 时区
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone

# 替换默认配置
RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/ /etc/nginx/conf.d/

# 前端产物
COPY --from=frontend-builder /app/dist /usr/share/nginx/html

EXPOSE 80

# 构建期校验配置
RUN nginx -t

CMD ["nginx", "-g", "daemon off;"]
