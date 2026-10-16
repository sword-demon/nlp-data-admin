<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Unit\Service\Provider;

use App\Constants\Code;
use App\Contract\ModelProviderInterface;
use App\Exception\BusinessException;
use App\Service\Provider\OpenAICompatibleProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class OpenAICompatibleProviderTest extends TestCase
{
    public function testToolCallingPreservesPlaceholderContract(): void
    {
        $provider = new OpenAICompatibleProvider([]);
        $this->assertInstanceOf(ModelProviderInterface::class, $provider);

        try {
            $provider->chatWithTools('system', [], []);
            $this->fail('Placeholder tool calling must throw a BusinessException');
        } catch (BusinessException $exception) {
            $this->assertSame(Code::AI_PROVIDER_ERROR, $exception->getCode());
            $this->assertSame(501, $exception->getHttpStatus());
            $this->assertSame(
                'OpenAICompatibleProvider is a placeholder; not implemented yet',
                $exception->getMessage()
            );
        }
    }
}
