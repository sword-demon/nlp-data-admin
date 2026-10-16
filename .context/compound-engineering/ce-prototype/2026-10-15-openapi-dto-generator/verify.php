#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * 验证生成的 DTO 类
 *
 * 不依赖 composer autoload，直接 require 文件
 */

// 手动加载生成的类
require_once __DIR__ . '/generated/TitleCandidate.php';
require_once __DIR__ . '/generated/TitleCandidates.php';
require_once __DIR__ . '/generated/OutlineNode.php';

use Generated\TitleCandidate;
use Generated\TitleCandidates;
use Generated\OutlineNode;

echo "================================\n";
echo "验证生成的 DTO 类\n";
echo "================================\n\n";

$errors = [];
$passed = 0;

// Test 1: TitleCandidate 创建和序列化
echo "Test 1: TitleCandidate 创建和序列化...\n";
try {
    $candidate = new TitleCandidate(
        title: '如何提升代码质量',
        analysis: '简洁明了,包含关键词',
        score: 85
    );

    $array = $candidate->toArray();

    if ($array['title'] !== '如何提升代码质量') {
        $errors[] = "TitleCandidate::toArray() - title 不匹配";
    } elseif ($array['analysis'] !== '简洁明了,包含关键词') {
        $errors[] = "TitleCandidate::toArray() - analysis 不匹配";
    } elseif ($array['score'] !== 85) {
        $errors[] = "TitleCandidate::toArray() - score 不匹配";
    } else {
        echo "  ✓ 构造函数和 toArray() 工作正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "TitleCandidate 测试失败: " . $e->getMessage();
}

// Test 2: TitleCandidate 反序列化
echo "\nTest 2: TitleCandidate 反序列化...\n";
try {
    $data = [
        'title' => '测试标题',
        'analysis' => '测试分析',
        'score' => 90,
    ];

    $candidate = TitleCandidate::fromArray($data);

    if ($candidate->title !== '测试标题') {
        $errors[] = "TitleCandidate::fromArray() - title 不匹配";
    } elseif ($candidate->analysis !== '测试分析') {
        $errors[] = "TitleCandidate::fromArray() - analysis 不匹配";
    } elseif ($candidate->score !== 90) {
        $errors[] = "TitleCandidate::fromArray() - score 不匹配";
    } else {
        echo "  ✓ fromArray() 工作正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "TitleCandidate::fromArray() 测试失败: " . $e->getMessage();
}

// Test 3: TitleCandidates 嵌套数组处理
echo "\nTest 3: TitleCandidates 嵌套数组处理...\n";
try {
    $candidate1 = new TitleCandidate('标题1', '分析1', 80);
    $candidate2 = new TitleCandidate('标题2', '分析2', 90);

    $candidates = new TitleCandidates([$candidate1, $candidate2]);

    $array = $candidates->toArray();

    if (!isset($array['titles']) || !is_array($array['titles'])) {
        $errors[] = "TitleCandidates::toArray() - titles 字段缺失或类型错误";
    } elseif (count($array['titles']) !== 2) {
        $errors[] = "TitleCandidates::toArray() - titles 数量不匹配";
    } elseif ($array['titles'][0]['title'] !== '标题1') {
        $errors[] = "TitleCandidates::toArray() - 嵌套对象未正确序列化";
    } else {
        echo "  ✓ 嵌套对象数组序列化正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "TitleCandidates 嵌套数组测试失败: " . $e->getMessage();
}

// Test 4: TitleCandidates 反序列化
echo "\nTest 4: TitleCandidates 反序列化...\n";
try {
    $data = [
        'titles' => [
            ['title' => 'A', 'analysis' => 'AA', 'score' => 70],
            ['title' => 'B', 'analysis' => 'BB', 'score' => 80],
        ],
    ];

    $candidates = TitleCandidates::fromArray($data);

    if (count($candidates->titles) !== 2) {
        $errors[] = "TitleCandidates::fromArray() - titles 数量不匹配";
    } elseif (!($candidates->titles[0] instanceof TitleCandidate)) {
        $errors[] = "TitleCandidates::fromArray() - 元素类型错误";
    } elseif ($candidates->titles[0]->title !== 'A') {
        $errors[] = "TitleCandidates::fromArray() - 嵌套对象数据不匹配";
    } else {
        echo "  ✓ 嵌套对象数组反序列化正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "TitleCandidates::fromArray() 测试失败: " . $e->getMessage();
}

// Test 5: TitleCandidates minItems 验证
echo "\nTest 5: TitleCandidates minItems 验证...\n";
try {
    new TitleCandidates([]);
    $errors[] = "TitleCandidates minItems 验证未生效 - 应该抛出异常";
} catch (InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'at least 1 item')) {
        echo "  ✓ minItems 验证工作正常\n";
        $passed++;
    } else {
        $errors[] = "TitleCandidates minItems 验证消息不正确: " . $e->getMessage();
    }
} catch (Throwable $e) {
    $errors[] = "TitleCandidates minItems 验证失败: " . $e->getMessage();
}

// Test 6: OutlineNode 可空字段
echo "\nTest 6: OutlineNode 可空字段...\n";
try {
    $node = new OutlineNode(
        id: 1,
        text: '引言',
        level: 1,
        parentId: null
    );

    $array = $node->toArray();

    if ($array['parent_id'] !== null) {
        $errors[] = "OutlineNode::toArray() - parent_id 应该是 null";
    } else {
        echo "  ✓ 可空字段序列化正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "OutlineNode 可空字段测试失败: " . $e->getMessage();
}

// Test 7: OutlineNode 反序列化可空字段
echo "\nTest 7: OutlineNode 反序列化可空字段...\n";
try {
    $data = ['id' => 2, 'text' => '子节点', 'level' => 2, 'parent_id' => 1];
    $node = OutlineNode::fromArray($data);

    if ($node->parentId !== 1) {
        $errors[] = "OutlineNode::fromArray() - parent_id 值不匹配";
    } else {
        echo "  ✓ 可空字段反序列化正常\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "OutlineNode 可空字段反序列化失败: " . $e->getMessage();
}

// Test 8: 往返转换一致性
echo "\nTest 8: 往返转换一致性...\n";
try {
    $original = [
        'titles' => [
            ['title' => 'X', 'analysis' => 'Y', 'score' => 95],
        ],
    ];

    $obj = TitleCandidates::fromArray($original);
    $roundtrip = $obj->toArray();

    if ($roundtrip['titles'][0]['title'] !== 'X') {
        $errors[] = "往返转换数据不一致";
    } else {
        echo "  ✓ 往返转换一致性验证通过\n";
        $passed++;
    }
} catch (Throwable $e) {
    $errors[] = "往返转换测试失败: " . $e->getMessage();
}

// 输出结果
echo "\n================================\n";
echo "测试结果\n";
echo "================================\n";
echo "通过: {$passed}/8\n";

if (!empty($errors)) {
    echo "\n失败的测试:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
    exit(1);
} else {
    echo "\n✓ 所有测试通过!\n\n";
    echo "生成的 DTO 类:\n";
    echo "  - TitleCandidate: 基本属性, toArray/fromArray\n";
    echo "  - TitleCandidates: 嵌套对象数组, minItems 验证\n";
    echo "  - OutlineNode: 可空字段处理\n\n";
    echo "代码风格特征:\n";
    echo "  ✓ readonly 属性\n";
    echo "  ✓ 命名构造器参数\n";
    echo "  ✓ toArray() 方法\n";
    echo "  ✓ fromArray() 静态工厂\n";
    echo "  ✓ 验证逻辑 (minItems)\n";
    echo "  ✓ PHPDoc 类型注释\n";
    exit(0);
}
