<?php

declare(strict_types=1);

namespace Ray\Aop;

use PHPUnit\Framework\TestCase;

use function assert;
use function class_exists;
use function passthru;
use function serialize;
use function unserialize;

class WeaverTest extends TestCase
{
    public function testConstruct(): Weaver
    {
        $matcher = new Matcher();
        $pointcut = new Pointcut($matcher->any(), $matcher->startsWith('return'), [new FakeDoubleInterceptor()]);
        $bind = (new Bind())->bind(FakeWeaverMock::class, [$pointcut]);
        $weaver = new Weaver($bind, __DIR__ . '/tmp');
        $this->assertInstanceOf(Weaver::class, $weaver);

        return $weaver;
    }

    /**
     * @depends testConstruct
     */
    public function testWeave(Weaver $weaver): void
    {
        $className = $weaver->weave(FakeWeaverMock::class);
        $this->assertTrue(class_exists($className, false));
    }

    /**
     * This tests cover compiled aop file loading.
     *
     * @covers \Ray\Aop\Weaver::loadClass
     * @covers \Ray\Aop\Weaver::weave
     */
    public function testWeaveLoad(): void
    {
        $matcher = new Matcher();
        $pointcut = new Pointcut($matcher->any(), $matcher->any(), []);
        $bind = (new Bind())->bind(FakeWeaverMock::class, [$pointcut]);
        $weaver = new Weaver($bind, __DIR__ . '/tmp_unerase');
        $className = $weaver->weave(FakeWeaverMock::class);
        $this->assertTrue(class_exists($className, false));
    }

    /**
     * @depends testConstruct
     */
    public function testNewInstance(Weaver $weaver): void
    {
        $weaved = $weaver->newInstance(FakeWeaverMock::class, []);
        assert($weaved instanceof FakeWeaverMock);
        $result = $weaved->returnSame(1);
        $this->assertSame(2, $result);
    }

    /**
     * @depends testConstruct
     */
    public function testCachedWeaver(Weaver $weaver): void
    {
        $weaver = unserialize(serialize($weaver));
        assert($weaver instanceof  Weaver);
        $weaved = $weaver->newInstance(FakeWeaverMock::class, []);
        assert($weaved instanceof FakeWeaverMock);
        $result = $weaved->returnSame(1);
        $this->assertSame(2, $result);
    }

    public function testWeaveCompiled(): void
    {
        passthru('php ' . __DIR__ . '/script/weave.php');
        $pointcut = new Pointcut(
            (new Matcher())->any(),
            (new Matcher())->any(),
            [new FakeInterceptor()]
        );
        $bind = (new Bind());
        $bind->bind(FakeWeaverScript::class, [$pointcut]);
        $weaver = new Weaver($bind, __DIR__ . '/tmp');
        $className = $weaver->weave(FakeWeaverScript::class);
        $this->assertTrue(class_exists($className, false));
    }
}
