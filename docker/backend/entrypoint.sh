#!/bin/sh
# ============================================================
# Hyperf 后端容器入口脚本
#
# 职责：
#   1. 等待 MySQL / Redis 可用（生产模式）
#   2. 首次启动或缺失 vendor 时自动 composer install
#   3. 运行数据库迁移（--force 无交互，幂等重试）
#   4. 启动 Hyperf HTTP Server（前台 PID 1，收到 SIGTERM 优雅退出）
# ============================================================
set -e

cd /opt/www

APP_ENV="${APP_ENV:-dev}"
DB_HOST="${DB_HOST:-nlp-mysql}"
DB_PORT="${DB_PORT:-3306}"
REDIS_HOST="${REDIS_HOST:-nlp-redis}"
REDIS_PORT="${REDIS_PORT:-6379}"

echo "============================================"
echo "  NLP Data Admin Backend"
echo "  APP_ENV   : ${APP_ENV}"
echo "  DB        : ${DB_HOST}:${DB_PORT}"
echo "  Redis     : ${REDIS_HOST}:${REDIS_PORT}"
echo "  Listen    : 0.0.0.0:9501"
echo "============================================"

# ------------ 1) 等待依赖服务 ------------
wait_for() {
    host="$1"; port="$2"; name="$3"; retries=60
    echo "[wait-for] waiting ${name} at ${host}:${port} ..."
    while ! nc -z "${host}" "${port}" >/dev/null 2>&1; do
        retries=$((retries - 1))
        if [ "${retries}" -le 0 ]; then
            echo "[wait-for] ${name} NOT READY after 60s, give up." >&2
            return 1
        fi
        sleep 1
    done
    echo "[wait-for] ${name} is ready."
}

wait_for "${DB_HOST}" "${DB_PORT}" "MySQL" || exit 1
wait_for "${REDIS_HOST}" "${REDIS_PORT}" "Redis" || exit 1

# ------------ 2) 依赖安装（兜底） ------------
if [ ! -f "vendor/autoload.php" ]; then
    echo "[composer] vendor missing, installing..."
    if [ "${APP_ENV}" = "prod" ] || [ "${APP_ENV}" = "production" ]; then
        composer install --no-dev --no-interaction --optimize-autoloader --ignore-platform-reqs
    else
        composer install --no-interaction --optimize-autoloader --ignore-platform-reqs
    fi
fi

# ------------ 3) 运行迁移（最多重试 3 次） ------------
migrate_ok=0
for i in 1 2 3; do
    if php bin/hyperf.php migrate --force 2>&1; then
        migrate_ok=1
        break
    fi
    echo "[migrate] attempt ${i} failed, retry in 3s..."
    sleep 3
done
if [ "${migrate_ok}" -ne 1 ]; then
    echo "[migrate] WARNING: migrations did not run cleanly, continuing anyway."
fi

# ------------ 4) 启动 Hyperf Server（exec 让 PHP 成为 PID 1，接管信号） ------------
echo "[hyperf] starting server..."
exec php bin/hyperf.php start
