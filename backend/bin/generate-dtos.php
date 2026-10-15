#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * DTO 生成器脚本
 * 从 OpenAPI YAML 定义生成 PHP DTO 类
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DtoGenerator
{
    private Environment $twig;
    private string $schemasDir;
    private string $outputDir;
    private array $generatedFiles = [];

    public function __construct(string $schemasDir, string $outputDir, string $templatesDir)
    {
        $this->schemasDir = $schemasDir;
        $this->outputDir = $outputDir;

        // 初始化 Twig
        $loader = new FilesystemLoader($templatesDir);
        $this->twig = new Environment($loader, [
            'autoescape' => false, // PHP 代码不需要转义
            'strict_variables' => true,
        ]);
    }

    public function generate(): void
    {
        echo "🚀 开始生成 DTO 类...\n\n";

        // 确保输出目录存在
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
            echo "✓ 创建输出目录: {$this->outputDir}\n";
        }

        // 遍历所有 schema 文件
        $schemaFiles = glob($this->schemasDir . '/*.yaml');
        foreach ($schemaFiles as $schemaFile) {
            $this->processSchemaFile($schemaFile);
        }

        echo "\n✅ 生成完成! 共生成 " . count($this->generatedFiles) . " 个 DTO 类:\n";
        foreach ($this->generatedFiles as $file) {
            echo "   - $file\n";
        }
    }

    private function processSchemaFile(string $schemaFile): void
    {
        $moduleName = basename($schemaFile, '.yaml');
        echo "📄 处理模块: $moduleName\n";

        $schemaData = Yaml::parseFile($schemaFile);
        $schemas = $schemaData['components']['schemas'] ?? [];

        foreach ($schemas as $schemaName => $schemaDefinition) {
            $this->generateDtoClass($schemaName, $schemaDefinition, $moduleName);
        }
    }

    private function generateDtoClass(string $className, array $schema, string $moduleName): void
    {
        // 提取属性定义
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];
        $description = $schema['description'] ?? '';

        // 转换属性为模板可用格式
        $processedProperties = [];
        foreach ($properties as $propName => $propDef) {
            $processedProperties[] = [
                'name' => $propName,
                'type' => $this->mapOpenApiTypeToPhp($propDef),
                'description' => $propDef['description'] ?? '',
                'required' => in_array($propName, $required),
                'nullable' => $propDef['nullable'] ?? false,
                'example' => $propDef['example'] ?? null,
                'isArray' => ($propDef['type'] ?? '') === 'array',
                'arrayItemType' => $this->getArrayItemType($propDef),
                'isObject' => ($propDef['type'] ?? '') === 'object',
                'additionalProperties' => $propDef['additionalProperties'] ?? null,
                'minimum' => $propDef['minimum'] ?? null,
                'maximum' => $propDef['maximum'] ?? null,
            ];
        }

        // 渲染模板
        $output = $this->twig->render('dto.php.twig', [
            'className' => $className,
            'description' => $description,
            'properties' => $processedProperties,
            'namespace' => 'App\\DTO',
            'module' => ucfirst($moduleName),
        ]);

        // 写入文件
        $outputFile = $this->outputDir . '/' . $className . '.php';
        file_put_contents($outputFile, $output);
        $this->generatedFiles[] = $className . '.php';

        echo "  ✓ 生成: $className\n";
    }

    private function mapOpenApiTypeToPhp(array $propertyDef): string
    {
        $type = $propertyDef['type'] ?? 'mixed';
        $format = $propertyDef['format'] ?? null;

        // 处理 $ref 引用
        if (isset($propertyDef['$ref'])) {
            // 提取类名 (例如: '#/components/schemas/TitleCandidate' -> TitleCandidate)
            $parts = explode('/', $propertyDef['$ref']);
            return end($parts);
        }

        // OpenAPI -> PHP 类型映射
        return match ($type) {
            'string' => $format === 'date-time' ? '\\DateTimeInterface' : 'string',
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            'object' => 'array', // 通用对象映射为 array
            default => 'mixed',
        };
    }

    private function getArrayItemType(array $propertyDef): ?string
    {
        if (($propertyDef['type'] ?? '') !== 'array') {
            return null;
        }

        $items = $propertyDef['items'] ?? [];

        // 处理 $ref
        if (isset($items['$ref'])) {
            $parts = explode('/', $items['$ref']);
            return end($parts);
        }

        // 基本类型
        return $this->mapOpenApiTypeToPhp($items);
    }
}

// 主执行流程
$rootDir = dirname(__DIR__);
$schemasDir = $rootDir . '/openapi/schemas';
$outputDir = $rootDir . '/app/DTO';
$templatesDir = $rootDir . '/templates';

try {
    $generator = new DtoGenerator($schemasDir, $outputDir, $templatesDir);
    $generator->generate();
    exit(0);
} catch (\Throwable $e) {
    echo "\n❌ 生成失败: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
