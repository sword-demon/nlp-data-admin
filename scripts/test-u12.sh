#!/bin/bash

# U12 快速验证脚本
# 功能: 验证前端类型生成流程是否正常工作

set -e

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "======================================"
echo "U12 前端类型生成 - 验证脚本"
echo "======================================"
echo ""

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

# 检查列表
CHECKS_PASSED=0
CHECKS_FAILED=0
TOTAL_CHECKS=0

check() {
    local name="$1"
    local command="$2"

    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))

    if eval "$command" >/dev/null 2>&1; then
        echo -e "${GREEN}✅${NC} $name"
        CHECKS_PASSED=$((CHECKS_PASSED + 1))
        return 0
    else
        echo -e "${RED}❌${NC} $name"
        CHECKS_FAILED=$((CHECKS_FAILED + 1))
        return 1
    fi
}

echo "1️⃣  文件存在性检查"
echo ""

check "类型生成脚本" "test -f frontend/scripts/generate-types.sh"
check "CI 配置文件" "test -f .github/workflows/ci.yml"
check "类型目录" "test -d frontend/src/types"
check "使用文档" "test -f frontend/src/types/README.md"
check "实现文档" "test -f docs/u12-frontend-type-generation.md"

echo ""
echo "2️⃣  package.json 配置检查"
echo ""

check "generate:types 脚本" "grep -q 'generate:types' frontend/package.json"
check "openapi-typescript 依赖" "grep -q 'openapi-typescript' frontend/package.json"

echo ""
echo "3️⃣  脚本权限检查"
echo ""

check "脚本可执行权限" "test -x frontend/scripts/generate-types.sh"

echo ""
echo "4️⃣  CI 配置验证"
echo ""

check "CI 触发路径配置" "grep -q 'backend/app/Service/Agent/Outcome/Payload' .github/workflows/ci.yml"
check "自动提交步骤" "grep -q 'git commit' .github/workflows/ci.yml"
check "TypeScript 编译步骤" "grep -q 'npm run build' .github/workflows/ci.yml"

echo ""
echo "5️⃣  脚本内容验证"
echo ""

check "swagger.json 路径检查" "grep -q 'SWAGGER_JSON=' frontend/scripts/generate-types.sh"
check "openapi-typescript 调用" "grep -q 'npx openapi-typescript' frontend/scripts/generate-types.sh"
check "关键类型验证" "grep -q 'TitleCandidate' frontend/scripts/generate-types.sh"
check "颜色输出" "grep -q 'GREEN=' frontend/scripts/generate-types.sh"

echo ""
echo "6️⃣  文档完整性检查"
echo ""

check "README 使用示例" "grep -q 'import type' frontend/src/types/README.md"
check "实现文档测试场景" "grep -q '场景 1' docs/u12-frontend-type-generation.md"
check "实现文档验收标准" "grep -q '验收标准' docs/u12-frontend-type-generation.md"

echo ""
echo "======================================"
echo "验证结果汇总"
echo "======================================"
echo ""
echo "通过: $CHECKS_PASSED/$TOTAL_CHECKS"
echo "失败: $CHECKS_FAILED/$TOTAL_CHECKS"
echo ""

PASS_RATE=$(awk "BEGIN {printf \"%.1f\", ($CHECKS_PASSED/$TOTAL_CHECKS)*100}")

if [ "$PASS_RATE" = "100.0" ]; then
    echo -e "${GREEN}🎉 所有检查通过! ($PASS_RATE%)${NC}"
    echo ""
    echo "✅ U12 实现完成 (代码层面)"
    echo ""
    echo "下一步 - 运行时验证:"
    echo "1. 生成 swagger.json:"
    echo "   cd backend && ./vendor/bin/openapi --output storage/swagger.json --format json app/Service/Agent/Outcome/Payload"
    echo ""
    echo "2. 生成前端类型:"
    echo "   cd frontend && npm run generate:types"
    echo ""
    echo "3. 验证 TypeScript 编译:"
    echo "   cd frontend && npm run build"
    echo ""
    echo "4. 推送到 GitHub 触发 CI:"
    echo "   git add . && git commit -m 'feat(u12): 实现前端类型自动生成' && git push"
    exit 0
else
    echo -e "${RED}❌ 部分检查失败 ($PASS_RATE%)${NC}"
    echo ""
    echo "请检查上述失败项"
    exit 1
fi
