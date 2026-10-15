#!/bin/bash

# Swagger 注解快速验证脚本
# 用途: 检查所有 Payload DTO 是否包含 OpenAPI 注解

set -e

BACKEND_DIR="/Volumes/MOVESPEED/ai-coding/nlp-data-admin/backend"
PAYLOAD_DIR="$BACKEND_DIR/app/Service/Agent/Outcome/Payload"

echo "======================================"
echo "Swagger 注解验证脚本"
echo "======================================"
echo ""

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 计数器
TOTAL=0
PASSED=0
FAILED=0

# 核心 DTO 列表
CORE_DTOS=(
    "ResearchBundle.php"
    "TitleCandidate.php"
    "TitleCandidates.php"
    "OutlineNode.php"
    "OutlineDraft.php"
    "Placeholder.php"
    "ContentDraft.php"
    "ImageAnalysis.php"
    "ImageAnalyses.php"
    "ImageSlot.php"
    "GeneratedImages.php"
    "ReflectionPayload.php"
)

echo "1️⃣  检查文件是否存在..."
echo ""

for dto in "${CORE_DTOS[@]}"; do
    TOTAL=$((TOTAL + 1))
    FILE_PATH="$PAYLOAD_DIR/$dto"

    if [ -f "$FILE_PATH" ]; then
        echo -e "${GREEN}✅${NC} $dto 存在"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}❌${NC} $dto 不存在"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
echo "2️⃣  检查 Schema 注解..."
echo ""

SCHEMA_PASSED=0
SCHEMA_FAILED=0

for dto in "${CORE_DTOS[@]}"; do
    FILE_PATH="$PAYLOAD_DIR/$dto"

    if [ -f "$FILE_PATH" ]; then
        if grep -q "#\[OA\\\\Schema" "$FILE_PATH"; then
            echo -e "${GREEN}✅${NC} $dto 包含 Schema 注解"
            SCHEMA_PASSED=$((SCHEMA_PASSED + 1))
        else
            echo -e "${RED}❌${NC} $dto 缺少 Schema 注解"
            SCHEMA_FAILED=$((SCHEMA_FAILED + 1))
        fi
    fi
done

echo ""
echo "3️⃣  检查 Property 注解..."
echo ""

PROPERTY_PASSED=0
PROPERTY_FAILED=0

for dto in "${CORE_DTOS[@]}"; do
    FILE_PATH="$PAYLOAD_DIR/$dto"

    if [ -f "$FILE_PATH" ]; then
        if grep -q "#\[OA\\\\Property" "$FILE_PATH"; then
            echo -e "${GREEN}✅${NC} $dto 包含 Property 注解"
            PROPERTY_PASSED=$((PROPERTY_PASSED + 1))
        else
            echo -e "${RED}❌${NC} $dto 缺少 Property 注解"
            PROPERTY_FAILED=$((PROPERTY_FAILED + 1))
        fi
    fi
done

echo ""
echo "4️⃣  检查 OpenApi\Attributes 导入..."
echo ""

IMPORT_PASSED=0
IMPORT_FAILED=0

for dto in "${CORE_DTOS[@]}"; do
    FILE_PATH="$PAYLOAD_DIR/$dto"

    if [ -f "$FILE_PATH" ]; then
        if grep -q "use OpenApi\\\\Attributes as OA;" "$FILE_PATH"; then
            echo -e "${GREEN}✅${NC} $dto 正确导入 OpenApi\Attributes"
            IMPORT_PASSED=$((IMPORT_PASSED + 1))
        else
            echo -e "${RED}❌${NC} $dto 缺少 OpenApi\Attributes 导入"
            IMPORT_FAILED=$((IMPORT_FAILED + 1))
        fi
    fi
done

echo ""
echo "5️⃣  检查配置文件..."
echo ""

CONFIG_FILE="$BACKEND_DIR/config/autoload/swagger.php"
if [ -f "$CONFIG_FILE" ]; then
    echo -e "${GREEN}✅${NC} swagger.php 配置文件存在"

    if grep -q "app/Service/Agent/Outcome/Payload" "$CONFIG_FILE"; then
        echo -e "${GREEN}✅${NC} 扫描路径配置正确"
    else
        echo -e "${RED}❌${NC} 扫描路径配置错误"
    fi
else
    echo -e "${RED}❌${NC} swagger.php 配置文件不存在"
fi

echo ""
echo "6️⃣  检查文档文件..."
echo ""

DOC_FILE="$BACKEND_DIR/docs/swagger-validation.md"
if [ -f "$DOC_FILE" ]; then
    echo -e "${GREEN}✅${NC} swagger-validation.md 文档存在"
else
    echo -e "${RED}❌${NC} swagger-validation.md 文档不存在"
fi

TEST_FILE="$BACKEND_DIR/test/Unit/Service/Agent/Outcome/Payload/SwaggerAnnotationTest.php"
if [ -f "$TEST_FILE" ]; then
    echo -e "${GREEN}✅${NC} SwaggerAnnotationTest.php 测试文件存在"
else
    echo -e "${RED}❌${NC} SwaggerAnnotationTest.php 测试文件不存在"
fi

echo ""
echo "======================================"
echo "验证结果汇总"
echo "======================================"
echo ""
echo "文件存在性: $PASSED/$TOTAL"
echo "Schema 注解: $SCHEMA_PASSED/${#CORE_DTOS[@]}"
echo "Property 注解: $PROPERTY_PASSED/${#CORE_DTOS[@]}"
echo "导入语句: $IMPORT_PASSED/${#CORE_DTOS[@]}"
echo ""

# 计算总体通过率
TOTAL_CHECKS=$((${#CORE_DTOS[@]} * 4 + 2))  # 文件 + Schema + Property + Import + 配置文件 + 测试文件
TOTAL_PASSED=$((PASSED + SCHEMA_PASSED + PROPERTY_PASSED + IMPORT_PASSED))

# 检查配置和文档文件
if [ -f "$BACKEND_DIR/config/autoload/swagger.php" ] && grep -q "app/Service/Agent/Outcome/Payload" "$BACKEND_DIR/config/autoload/swagger.php"; then
    TOTAL_PASSED=$((TOTAL_PASSED + 1))
fi

if [ -f "$BACKEND_DIR/test/Unit/Service/Agent/Outcome/Payload/SwaggerAnnotationTest.php" ]; then
    TOTAL_PASSED=$((TOTAL_PASSED + 1))
fi

PASS_RATE=$(awk "BEGIN {printf \"%.1f\", ($TOTAL_PASSED/$TOTAL_CHECKS)*100}")

if [ "$PASS_RATE" = "100.0" ]; then
    echo -e "${GREEN}🎉 所有检查通过! ($PASS_RATE%)${NC}"
    echo ""
    echo "下一步:"
    echo "1. 安装 OpenAPI 生成工具:"
    echo "   cd backend && composer require zircote/swagger-php:^4.0 --dev"
    echo ""
    echo "2. 生成 swagger.json:"
    echo "   ./vendor/bin/openapi --output storage/swagger.json --format json app/Service/Agent/Outcome/Payload"
    echo ""
    echo "3. 运行单元测试:"
    echo "   vendor/bin/pest test/Unit/Service/Agent/Outcome/Payload/SwaggerAnnotationTest.php"
    exit 0
else
    echo -e "${YELLOW}⚠️  部分检查失败 ($PASS_RATE%)${NC}"
    echo ""
    echo "请检查上述失败项并修复"
    exit 1
fi
