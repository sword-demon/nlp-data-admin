<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Agent\Outcome\Payload;

use App\Service\Agent\Outcome\Payload\ContentDraft;
use App\Service\Agent\Outcome\Payload\GeneratedImages;
use App\Service\Agent\Outcome\Payload\ImageAnalyses;
use App\Service\Agent\Outcome\Payload\OutlineDraft;
use App\Service\Agent\Outcome\Payload\OutlineNode;
use App\Service\Agent\Outcome\Payload\ReflectionPayload;
use App\Service\Agent\Outcome\Payload\ResearchBundle;
use App\Service\Agent\Outcome\Payload\TitleCandidates;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Swagger 注解验证测试
 *
 * 验证所有 Payload DTO 是否正确添加了 OpenAPI 注解
 */
class SwaggerAnnotationTest extends TestCase
{
    public function testSwaggerAttributesAreRuntimeDependencies(): void
    {
        $composer = json_decode(
            file_get_contents(dirname(__DIR__, 6) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('zircote/swagger-php', $composer['require']);

        $lock = json_decode(
            file_get_contents(dirname(__DIR__, 6) . '/composer.lock'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertContains('zircote/swagger-php', array_column($lock['packages'], 'name'));
        $this->assertNotContains('zircote/swagger-php', array_column($lock['packages-dev'], 'name'));
    }

    /**
     * 测试所有核心 Payload DTO 都有 Schema 注解
     */
    public function testAllPayloadDTOsHaveSchemaAnnotation(): void
    {
        $dtoClasses = [
            ResearchBundle::class,
            TitleCandidates::class,
            OutlineDraft::class,
            OutlineNode::class,
            ContentDraft::class,
            ImageAnalyses::class,
            GeneratedImages::class,
            ReflectionPayload::class,
        ];

        foreach ($dtoClasses as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes();

            $hasSchemaAttribute = false;
            foreach ($attributes as $attribute) {
                if (str_contains($attribute->getName(), 'Schema')) {
                    $this->assertInstanceOf(Schema::class, $attribute->newInstance());
                    $hasSchemaAttribute = true;
                    break;
                }
            }

            $this->assertTrue(
                $hasSchemaAttribute,
                sprintf('Class %s should have Schema attribute', $class)
            );
        }
    }

    /**
     * 测试 TitleCandidates 的 Schema 注解包含正确的 schema 名称
     */
    public function testTitleCandidatesSchemaName(): void
    {
        $reflection = new ReflectionClass(TitleCandidates::class);
        $attributes = $reflection->getAttributes();

        $schemaName = null;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Schema')) {
                $args = $attribute->getArguments();
                $schemaName = $args['schema'] ?? $args[0] ?? null;
                break;
            }
        }

        $this->assertEquals('TitleCandidates', $schemaName);
    }

    /**
     * 测试 ReflectionPayload 的 Schema 注解包含描述
     */
    public function testReflectionPayloadHasDescription(): void
    {
        $reflection = new ReflectionClass(ReflectionPayload::class);
        $attributes = $reflection->getAttributes();

        $hasDescription = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Schema')) {
                $args = $attribute->getArguments();
                if (!empty($args['description'])) {
                    $hasDescription = true;
                    $this->assertStringContainsString('评审', $args['description']);
                }
                break;
            }
        }

        $this->assertTrue($hasDescription, 'ReflectionPayload should have description');
    }

    /**
     * 测试所有构造函数参数都有 Property 注解
     */
    public function testAllConstructorParametersHavePropertyAnnotation(): void
    {
        $dtoClasses = [
            TitleCandidates::class,
            OutlineDraft::class,
            ReflectionPayload::class,
        ];

        foreach ($dtoClasses as $class) {
            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                continue;
            }

            $parameters = $constructor->getParameters();

            foreach ($parameters as $parameter) {
                $attributes = $parameter->getAttributes();

                $hasPropertyAttribute = false;
                foreach ($attributes as $attribute) {
                    if (str_contains($attribute->getName(), 'Property')) {
                        $hasPropertyAttribute = true;
                        break;
                    }
                }

                $this->assertTrue(
                    $hasPropertyAttribute,
                    sprintf('Parameter %s in %s should have Property attribute',
                        $parameter->getName(),
                        $class
                    )
                );
            }
        }
    }

    /**
     * 测试 Schema 注解包含 required 字段列表
     */
    public function testSchemaAnnotationsHaveRequiredFields(): void
    {
        $dtoClasses = [
            TitleCandidates::class => ['titles'],
            OutlineDraft::class => ['markdown', 'nodes'],
            ReflectionPayload::class => ['scores', 'suggestions', 'average_score'],
        ];

        foreach ($dtoClasses as $class => $expectedRequired) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes();

            $requiredFields = null;
            foreach ($attributes as $attribute) {
                if (str_contains($attribute->getName(), 'Schema')) {
                    $args = $attribute->getArguments();
                    $requiredFields = $args['required'] ?? null;
                    break;
                }
            }

            $this->assertNotNull($requiredFields, sprintf('%s should have required fields', $class));

            foreach ($expectedRequired as $field) {
                $this->assertContains(
                    $field,
                    $requiredFields,
                    sprintf('%s should have %s in required fields', $class, $field)
                );
            }
        }
    }

    /**
     * 测试 Property 注解包含示例值
     */
    public function testPropertyAnnotationsHaveExamples(): void
    {
        $reflection = new ReflectionClass(TitleCandidates::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $this->markTestSkipped('No constructor found');
            return;
        }

        $parameters = $constructor->getParameters();
        $titlesParam = null;

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === 'titles') {
                $titlesParam = $parameter;
                break;
            }
        }

        $this->assertNotNull($titlesParam, 'titles parameter should exist');

        $attributes = $titlesParam->getAttributes();
        $hasExample = false;

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Property')) {
                $args = $attribute->getArguments();
                // 数组字段可能没有直接的 example，但应该有 items 定义
                if (isset($args['items']) || isset($args['example'])) {
                    $hasExample = true;
                }
                break;
            }
        }

        $this->assertTrue($hasExample, 'titles property should have items or example definition');
    }

    /**
     * 测试嵌套引用的正确性
     */
    public function testNestedSchemaReferences(): void
    {
        $reflection = new ReflectionClass(TitleCandidates::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $this->markTestSkipped('No constructor found');
            return;
        }

        $parameters = $constructor->getParameters();
        $titlesParam = null;

        foreach ($parameters as $parameter) {
            if ($parameter->getName() === 'titles') {
                $titlesParam = $parameter;
                break;
            }
        }

        $this->assertNotNull($titlesParam);

        $attributes = $titlesParam->getAttributes();
        $hasItemsRef = false;

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'Property')) {
                $args = $attribute->getArguments();
                if (isset($args['items'])) {
                    $hasItemsRef = true;
                }
                break;
            }
        }

        $this->assertTrue($hasItemsRef, 'Array property should have items definition');
    }
}
