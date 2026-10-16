#!/usr/bin/env php
<?php

declare(strict_types=1);

// 简单的 OpenAPI DTO 生成器原型
// 依赖: symfony/yaml, twig/twig

require_once __DIR__ . '/../../../../../../backend/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$schemaFile = __DIR__ . '/schemas.yaml';
$templateFile = __DIR__ . '/dto.php.twig';
$outputDir = __DIR__ . '/generated';

if (!file_exists($schemaFile)) {
    fwrite(STDERR, "Schema file not found: $schemaFile\n");
    exit(1);
}

if (!file_exists($templateFile)) {
    fwrite(STDERR, "Template file not found: $templateFile\n");
    exit(1);
}

// 创建输出目录
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// 读取 OpenAPI schema
$openapi = Yaml::parseFile($schemaFile);
$schemas = $openapi['components']['schemas'] ?? [];

if (empty($schemas)) {
    fwrite(STDERR, "No schemas found in OpenAPI file\n");
    exit(1);
}

// 初始化 Twig
$loader = new FilesystemLoader(__DIR__);
$twig = new Environment($loader, [
    'autoescape' => false, // PHP 代码不需要转义
]);

echo "Generating DTOs from schemas.yaml...\n";

foreach ($schemas as $schemaName => $schema) {
    echo "  - Generating {$schemaName}...\n";

    $templateData = buildTemplateData($schemaName, $schema, $schemas);
    $phpCode = $twig->render('dto.php.twig', $templateData);

    $outputFile = $outputDir . '/' . $schemaName . '.php';
    file_put_contents($outputFile, $phpCode);

    echo "    ✓ Written to {$outputFile}\n";
}

echo "\nGeneration complete!\n";
echo "Run 'php verify.php' to test the generated classes.\n";

/**
 * 构建模板数据
 */
function buildTemplateData(string $schemaName, array $schema, array $allSchemas): array
{
    $namespace = 'Generated';
    $description = $schema['description'] ?? $schemaName;
    $properties = [];
    $validations = [];
    $hasNestedArray = false;
    $hasValidation = false;

    $requiredFields = $schema['required'] ?? [];

    foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
        $phpName = snakeToCamel($propName);
        $phpType = mapOpenApiTypeToPhp($propSchema, $allSchemas);
        $isRequired = in_array($propName, $requiredFields, true);
        $isNullable = ($propSchema['nullable'] ?? false) && !$isRequired;

        // 检测嵌套对象数组
        if ($propSchema['type'] === 'array' && isset($propSchema['items']['$ref'])) {
            $hasNestedArray = true;
        }

        // 构建 toArray 表达式
        $toArrayExpr = buildToArrayExpr($phpName, $propSchema, $allSchemas);

        // 构建 fromArray 表达式
        $fromArrayExpr = buildFromArrayExpr($phpName, $propName, $propSchema, $allSchemas, $isNullable);

        $properties[] = [
            'name' => $phpName,
            'schema_key' => $propName,
            'php_type' => $isNullable ? "?{$phpType}" : $phpType,
            'to_array_expr' => $toArrayExpr,
            'from_array_expr' => $fromArrayExpr,
        ];
    }

    // 生成验证逻辑
    foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
        if ($propSchema['type'] === 'array' && isset($propSchema['minItems'])) {
            $phpName = snakeToCamel($propName);
            $minItems = $propSchema['minItems'];
            $validations[] = "if (empty(\${$phpName})) {\n            throw new InvalidArgumentException('{$schemaName} requires at least {$minItems} item(s) in {$propName}');\n        }";
            $hasValidation = true;
        }
    }

    // 构建 toArray 返回类型注释
    $toArrayReturnType = buildToArrayReturnType($schema, $allSchemas);

    return [
        'namespace' => $namespace,
        'class_name' => $schemaName,
        'description' => $description,
        'properties' => $properties,
        'validations' => $validations,
        'has_constructor_params' => !empty($properties),
        'has_nested_array' => $hasNestedArray,
        'has_validation' => $hasValidation,
        'to_array_return_type' => $toArrayReturnType,
    ];
}

/**
 * 将 snake_case 转换为 camelCase
 */
function snakeToCamel(string $snake): string
{
    return lcfirst(str_replace('_', '', ucwords($snake, '_')));
}

/**
 * 将 OpenAPI 类型映射到 PHP 类型
 */
function mapOpenApiTypeToPhp(array $propSchema, array $allSchemas): string
{
    $type = $propSchema['type'] ?? 'string';

    if (isset($propSchema['$ref'])) {
        return extractRefName($propSchema['$ref']);
    }

    if ($type === 'array') {
        if (isset($propSchema['items']['$ref'])) {
            return 'array';
        }
        return 'array';
    }

    return match ($type) {
        'integer' => 'int',
        'number' => 'float',
        'boolean' => 'bool',
        'string' => 'string',
        'object' => 'array',
        default => 'mixed',
    };
}

/**
 * 构建 toArray 表达式
 */
function buildToArrayExpr(string $phpName, array $propSchema, array $allSchemas): string
{
    if ($propSchema['type'] === 'array' && isset($propSchema['items']['$ref'])) {
        // 嵌套对象数组: array_map
        return "array_map(static fn(\$t) => \$t->toArray(), \$this->{$phpName})";
    }

    return "\$this->{$phpName}";
}

/**
 * 构建 fromArray 表达式
 */
function buildFromArrayExpr(string $phpName, string $propName, array $propSchema, array $allSchemas, bool $isNullable): string
{
    $type = $propSchema['type'] ?? 'string';

    // 嵌套对象数组
    if ($type === 'array' && isset($propSchema['items']['$ref'])) {
        $itemClass = extractRefName($propSchema['items']['$ref']);
        $code = "(static function() use (\$data) {\n";
        $code .= "                \$items = (array) (\$data['{$propName}'] ?? []);\n";
        $code .= "                \$result = [];\n";
        $code .= "                foreach (\$items as \$item) {\n";
        $code .= "                    if (is_array(\$item)) {\n";
        $code .= "                        \$result[] = {$itemClass}::fromArray(\$item);\n";
        $code .= "                    }\n";
        $code .= "                }\n";
        $code .= "                return \$result;\n";
        $code .= "            })()";
        return $code;
    }

    // 可空类型
    if ($isNullable) {
        $cast = $type === 'integer' ? 'int' : ($type === 'string' ? 'string' : $type);
        return "isset(\$data['{$propName}']) && \$data['{$propName}'] !== null ? ({$cast}) \$data['{$propName}'] : null";
    }

    // 基本类型转换
    $defaultValue = match ($type) {
        'integer' => '0',
        'string' => "''",
        'boolean' => 'false',
        'array' => '[]',
        default => 'null',
    };

    $cast = match ($type) {
        'integer' => 'int',
        'string' => 'string',
        'boolean' => 'bool',
        'array' => 'array',
        default => '',
    };

    if ($cast) {
        return "({$cast}) (\$data['{$propName}'] ?? {$defaultValue})";
    }

    return "\$data['{$propName}'] ?? {$defaultValue}";
}

/**
 * 从 $ref 提取类名
 */
function extractRefName(string $ref): string
{
    return basename($ref);
}

/**
 * 构建 toArray 返回类型注释
 */
function buildToArrayReturnType(array $schema, array $allSchemas): string
{
    $parts = [];
    foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
        $type = $propSchema['type'] ?? 'string';
        $nullable = $propSchema['nullable'] ?? false;

        if ($type === 'array' && isset($propSchema['items']['$ref'])) {
            $parts[] = "{$propName}: array";
        } elseif ($type === 'integer') {
            $parts[] = "{$propName}: " . ($nullable ? '?int' : 'int');
        } elseif ($type === 'string') {
            $parts[] = "{$propName}: " . ($nullable ? '?string' : 'string');
        } else {
            $parts[] = "{$propName}: mixed";
        }
    }

    if (empty($parts)) {
        return 'array<empty, empty>';
    }

    return 'array{' . implode(', ', $parts) . '}';
}
