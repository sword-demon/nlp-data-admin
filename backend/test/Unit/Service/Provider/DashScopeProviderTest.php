<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Service\Provider;

use App\Exception\BusinessException;
use App\Service\Provider\DashScopeProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * DashScopeProvider Function Calling 单元测试
 */
class DashScopeProviderTest extends TestCase
{
    private DashScopeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new DashScopeProvider(
            apiKey: 'test-api-key',
            baseUrl: 'https://dashscope.aliyuncs.com',
            defaultModel: 'qwen-plus'
        );
    }

    /**
     * 测试场景 1: 正常调用返回 tool_calls
     */
    public function testChatWithToolsReturnsToolCalls(): void
    {
        $mockResponse = [
            'output' => [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_abc123',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'get_weather',
                                        'arguments' => '{"location":"北京"}',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->createProviderWithMock(new Response(200, [], json_encode($mockResponse)));

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => '获取天气信息',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string', 'description' => '城市名称'],
                        ],
                        'required' => ['location'],
                    ],
                ],
            ],
        ];

        $result = $provider->chatWithTools(
            '你是天气助手',
            [['role' => 'user', 'content' => '北京天气如何？']],
            $tools
        );

        $this->assertNull($result['content']);
        $this->assertIsArray($result['tool_calls']);
        $this->assertCount(1, $result['tool_calls']);
        $this->assertEquals('get_weather', $result['tool_calls'][0]['function']['name']);
        $this->assertStringContainsString('北京', $result['tool_calls'][0]['function']['arguments']);
    }

    /**
     * 测试场景 2: 模型不调用工具返回纯文本
     */
    public function testChatWithToolsReturnsPlainText(): void
    {
        $mockResponse = [
            'output' => [
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '北京今天晴天，温度 15-25℃',
                            'tool_calls' => null,
                        ],
                    ],
                ],
            ],
        ];

        $provider = $this->createProviderWithMock(new Response(200, [], json_encode($mockResponse)));

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => '获取天气信息',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'location' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $provider->chatWithTools(
            '你是天气助手',
            [['role' => 'user', 'content' => '介绍一下天气预报的原理']],
            $tools
        );

        $this->assertIsString($result['content']);
        $this->assertStringContainsString('天', $result['content']);
        $this->assertNull($result['tool_calls']);
    }

    /**
     * 测试场景 3: API 调用失败
     */
    public function testChatWithToolsApiCallFails(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('DashScope API call failed');

        $mockException = new RequestException(
            'Connection timeout',
            new Request('POST', 'test'),
            new Response(500)
        );

        $provider = $this->createProviderWithMock($mockException);

        $provider->chatWithTools(
            '你是助手',
            [['role' => 'user', 'content' => 'test']],
            []
        );
    }

    /**
     * 测试场景 4: 响应格式异常
     */
    public function testChatWithToolsInvalidResponseFormat(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionCode(\App\Constants\Code::AI_PROVIDER_ERROR);

        // 缺少 message 字段的响应
        $mockResponse = [
            'output' => [
                'choices' => [
                    [
                        // message 字段缺失
                    ],
                ],
            ],
        ];

        $provider = $this->createProviderWithMock(new Response(200, [], json_encode($mockResponse)));

        $provider->chatWithTools(
            '你是助手',
            [['role' => 'user', 'content' => 'test']],
            []
        );
    }

    /**
     * 创建带 Mock HTTP 客户端的 Provider
     */
    private function createProviderWithMock($mockResponseOrException): DashScopeProvider
    {
        $mock = new MockHandler([$mockResponseOrException]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // 使用反射注入 mock client
        $provider = new DashScopeProvider(
            apiKey: 'test-api-key',
            baseUrl: 'https://dashscope.aliyuncs.com',
            defaultModel: 'qwen-plus'
        );

        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('makeClient');
        $method->setAccessible(true);

        // 替换 makeClient 的返回值
        $clientProperty = new \ReflectionProperty($provider, 'timeout');
        $clientProperty->setAccessible(true);

        // 简化：直接通过反射调用时使用 mock
        return new class($client, $provider) extends DashScopeProvider {
            private Client $mockClient;

            public function __construct(Client $mockClient, DashScopeProvider $original)
            {
                $reflection = new \ReflectionClass($original);
                $apiKeyProp = $reflection->getProperty('apiKey');
                $apiKeyProp->setAccessible(true);
                $baseUrlProp = $reflection->getProperty('baseUrl');
                $baseUrlProp->setAccessible(true);
                $modelProp = $reflection->getProperty('defaultModel');
                $modelProp->setAccessible(true);

                parent::__construct(
                    apiKey: $apiKeyProp->getValue($original),
                    baseUrl: $baseUrlProp->getValue($original),
                    defaultModel: $modelProp->getValue($original)
                );

                $this->mockClient = $mockClient;
            }

            protected function makeClient(bool $stream): Client
            {
                return $this->mockClient;
            }
        };
    }
}
