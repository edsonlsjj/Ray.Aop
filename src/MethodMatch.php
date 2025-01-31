<?php

declare(strict_types=1);

namespace Ray\Aop;

use Doctrine\Common\Annotations\Reader;
use Ray\ServiceLocator\ServiceLocator;
use ReflectionClass;
use ReflectionMethod;

use function array_key_exists;
use function get_class;
use function str_starts_with;

final class MethodMatch
{
    /** @var Reader */
    private $reader;

    /** @var BindInterface */
    private $bind;

    /** @var bool */
    private $attributeIsClass = false;

    public function __construct(BindInterface $bind)
    {
        $this->bind = $bind;
        $this->reader = ServiceLocator::getReader();
    }

    /**
     * @param ReflectionClass<object> $class
     * @param Pointcut[]              $pointcuts
     */
    public function __invoke(ReflectionClass $class, ReflectionMethod $method, array $pointcuts): void
    {
        /** @var array<int, object> $annotations */
        $annotations = $this->reader->getMethodAnnotations($method);
        // priority bind
        foreach ($pointcuts as $key => $pointcut) {
            if ($pointcut instanceof PriorityPointcut) {
                $this->annotatedMethodMatchBind($class, $method, $pointcut);
                unset($pointcuts[$key]);
            }
        }

        $onion = $this->onionOrderMatch($class, $method, $pointcuts, $annotations);

        // default binding
        foreach ($onion as $pointcut) {
            $this->annotatedMethodMatchBind($class, $method, $pointcut);
        }
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function annotatedMethodMatchBind(ReflectionClass $class, ReflectionMethod $method, Pointcut $pointCut): void
    {
        $isMethodMatch = $pointCut->methodMatcher->matchesMethod($method, $pointCut->methodMatcher->getArguments());
        if ((! $isMethodMatch && ! $this->attributeIsClass) || str_starts_with($method->name, '__construct')) {
            return;
        }

        $isClassMatch = $pointCut->classMatcher->matchesClass($class, $pointCut->classMatcher->getArguments());
        if (! $isClassMatch) {
            return;
        }

        /** @var MethodInterceptor[] $interceptors */
        $interceptors = $pointCut->interceptors;
        $this->bind->bindInterceptors($method->name, $interceptors);
    }

    /**
     * @param ReflectionClass<object> $class
     * @param Pointcut[]              $pointcuts
     * @param array<int, object>      $annotations
     *
     * @return Pointcut[]
     */
    private function onionOrderMatch(
        ReflectionClass $class,
        ReflectionMethod $method,
        array $pointcuts,
        array $annotations
    ): array {
        // method bind in annotation order
        foreach ($annotations as $annotation) {
            $annotationIndex = get_class($annotation);
            if (array_key_exists($annotationIndex, $pointcuts)) {
                $this->annotatedMethodMatchBind($class, $method, $pointcuts[$annotationIndex]);
                unset($pointcuts[$annotationIndex]);
            }
        }

        return $pointcuts;
    }

    public function setAtributeIsClass(bool $atributeIsClass): void
    {
        $this->attributeIsClass = $atributeIsClass;
    }
}
