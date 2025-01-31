<?php

declare(strict_types=1);

namespace Ray\Aop;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\NodeVisitorAbstract;
use Ray\Aop\Exception\MultipleClassInOneFileException;

use function get_class;
use function implode;

final class CodeVisitor extends NodeVisitorAbstract
{
    /** @var ?Namespace_ */
    public $namespace;

    /** @var Declare_[] */
    public $declare = [];

    /** @var Use_[] */
    public $use = [];

    /** @var Class_|null */
    public $class;

    /** @var ClassMethod[] */
    public $classMethod = [];

    /**
     * @return null
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Declare_) {
            $this->declare[] = $node;

            return null;
        }

        if ($node instanceof Use_) {
            $this->addUse($node);

            return null;
        }

        if ($node instanceof Namespace_) {
            $this->namespace = $node;

            return null;
        }

        return $this->enterNodeClass($node);
    }

    private function validateClass(Class_ $class): void
    {
        $isClassAlreadyDeclared = $this->class instanceof Class_;
        if ($isClassAlreadyDeclared) {
            $name = $class->name instanceof Node\Identifier ? $class->name->name : '';

            throw new MultipleClassInOneFileException($name);
        }
    }

    /**
     * @return null
     */
    private function enterNodeClass(Node $node)
    {
        if ($node instanceof Class_) {
            $this->validateClass($node);
            $this->class = $node;

            return null;
        }

        if ($node instanceof ClassMethod) {
            $this->classMethod[] = $node;
        }

        return null;
    }

    /** @param array<object> $annotations */
    public function addUses(array $annotations): void
    {
        foreach ($annotations as $annotation) {
            $this->addUse(new Use_([new UseUse(new Name(get_class($annotation)))]));
        }
    }

    private function addUse(Use_ $use): void
    {
        $index = implode('\\', $use->uses[0]->name->parts);
        $this->use[$index] = $use;
    }
}
