#!/bin/bash

# 前端类型生成脚本
# 功能: 从后端 OpenAPI spec 自动生成 TypeScript 类型定义
# 依赖: openapi-typescript

set -e

# 颜色定义
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "======================================"
echo "前端类型生成脚本"
echo "======================================"
echo ""

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BACKEND_DIR="$PROJECT_ROOT/backend"
FRONTEND_DIR="$PROJECT_ROOT/frontend"
SWAGGER_JSON="$BACKEND_DIR/storage/swagger.json"
OUTPUT_FILE="$FRONTEND_DIR/src/types/api.ts"

# 检查 swagger.json 是否存在
if [ ! -f "$SWAGGER_JSON" ]; then
    echo -e "${RED}❌ swagger.json 不存在${NC}"
    echo "请先生成 OpenAPI spec:"
    echo "  cd backend"
    echo "  ./vendor/bin/openapi --output storage/swagger.json --format json app/Service/Agent/Outcome/Payload"
    exit 1
fi

echo -e "${GREEN}✅ 找到 swagger.json${NC}"
echo ""

# 检查 openapi-typescript 是否已安装
if ! npm list openapi-typescript >/dev/null 2>&1; then
    echo -e "${YELLOW}⚠️  openapi-typescript 未安装，正在安装...${NC}"
    npm install --save-dev openapi-typescript
fi

# 创建输出目录
mkdir -p "$(dirname "$OUTPUT_FILE")"

# 生成类型
echo "🔄 生成 TypeScript 类型..."
npx openapi-typescript "$SWAGGER_JSON" -o "$OUTPUT_FILE"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ 类型生成成功: $OUTPUT_FILE${NC}"

    # 显示生成的类型统计
    TYPE_COUNT=$(grep -c "export interface" "$OUTPUT_FILE" || echo 0)
    echo ""
    echo "📊 生成统计:"
    echo "  - 生成的接口数量: $TYPE_COUNT"
    echo "  - 输出文件大小: $(du -h "$OUTPUT_FILE" | cut -f1)"

    # 验证关键类型是否存在
    echo ""
    echo "🔍 验证关键类型:"

    REQUIRED_TYPES=(
        "TitleCandidate"
        "TitleCandidates"
        "OutlineNode"
        "OutlineDraft"
        "ContentDraft"
        "ReflectionPayload"
        "ResearchBundle"
    )

    MISSING_TYPES=0
    for type_name in "${REQUIRED_TYPES[@]}"; do
        if grep -q "$type_name" "$OUTPUT_FILE"; then
            echo -e "  ${GREEN}✅${NC} $type_name"
        else
            echo -e "  ${RED}❌${NC} $type_name (缺失)"
            MISSING_TYPES=$((MISSING_TYPES + 1))
        fi
    done

    echo ""

    if [ $MISSING_TYPES -gt 0 ]; then
        echo -e "${YELLOW}⚠️  有 $MISSING_TYPES 个关键类型缺失${NC}"
        echo "这可能是正常的，如果后端尚未补充相应的 Swagger 注解"
        exit 0
    else
        echo -e "${GREEN}🎉 所有关键类型验证通过！${NC}"
    fi
else
    echo -e "${RED}❌ 类型生成失败${NC}"
    exit 1
fi
