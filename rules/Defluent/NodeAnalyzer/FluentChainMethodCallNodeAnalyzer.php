<?php

declare (strict_types=1);
namespace Rector\Defluent\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\MutatingScope;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use Rector\Core\PhpParser\AstResolver;
use Rector\Core\PhpParser\Comparing\NodeComparator;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;
/**
 * Utils for chain of MethodCall Node:
 * "$this->methodCall()->chainedMethodCall()"
 */
final class FluentChainMethodCallNodeAnalyzer
{
    /**
     * Types that look like fluent interface, but actually create a new object.
     * Should be skipped, as they return different object. Not an fluent interface!
     *
     * @var class-string<MutatingScope>[]
     */
    private const KNOWN_FACTORY_FLUENT_TYPES = ['PHPStan\\Analyser\\MutatingScope'];
    /**
     * @var \Rector\NodeNameResolver\NodeNameResolver
     */
    private $nodeNameResolver;
    /**
     * @var \Rector\NodeTypeResolver\NodeTypeResolver
     */
    private $nodeTypeResolver;
    /**
     * @var \PhpParser\NodeFinder
     */
    private $nodeFinder;
    /**
     * @var \Rector\Core\PhpParser\AstResolver
     */
    private $astResolver;
    /**
     * @var \Rector\Core\PhpParser\Node\BetterNodeFinder
     */
    private $betterNodeFinder;
    /**
     * @var \Rector\Core\PhpParser\Comparing\NodeComparator
     */
    private $nodeComparator;
    public function __construct(\Rector\NodeNameResolver\NodeNameResolver $nodeNameResolver, \Rector\NodeTypeResolver\NodeTypeResolver $nodeTypeResolver, \PhpParser\NodeFinder $nodeFinder, \Rector\Core\PhpParser\AstResolver $astResolver, \Rector\Core\PhpParser\Node\BetterNodeFinder $betterNodeFinder, \Rector\Core\PhpParser\Comparing\NodeComparator $nodeComparator)
    {
        $this->nodeNameResolver = $nodeNameResolver;
        $this->nodeTypeResolver = $nodeTypeResolver;
        $this->nodeFinder = $nodeFinder;
        $this->astResolver = $astResolver;
        $this->betterNodeFinder = $betterNodeFinder;
        $this->nodeComparator = $nodeComparator;
    }
    /**
     * Checks that in:
     * $this->someCall();
     *
     * The method is fluent class method === returns self
     * public function someClassMethod()
     * {
     *      return $this;
     * }
     */
    public function isFluentClassMethodOfMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall) : bool
    {
        if ($this->isCall($methodCall->var)) {
            return \false;
        }
        $calleeStaticType = $this->nodeTypeResolver->getStaticType($methodCall->var);
        // we're not sure
        if ($calleeStaticType instanceof \PHPStan\Type\MixedType) {
            return \false;
        }
        $methodReturnStaticType = $this->nodeTypeResolver->getStaticType($methodCall);
        // is fluent type
        if (!$calleeStaticType->equals($methodReturnStaticType)) {
            return \false;
        }
        if ($calleeStaticType instanceof \PHPStan\Type\ObjectType) {
            foreach (self::KNOWN_FACTORY_FLUENT_TYPES as $knownFactoryFluentType) {
                if ($calleeStaticType->isInstanceOf($knownFactoryFluentType)->yes()) {
                    return \false;
                }
            }
        }
        return !$this->isMethodCallCreatingNewInstance($methodCall);
    }
    public function isLastChainMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall) : bool
    {
        // is chain method call
        if (!$methodCall->var instanceof \PhpParser\Node\Expr\MethodCall && !$methodCall->var instanceof \PhpParser\Node\Expr\New_) {
            return \false;
        }
        $nextNode = $methodCall->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::NEXT_NODE);
        // is last chain call
        return !$nextNode instanceof \PhpParser\Node;
    }
    /**
     * @return string[]|null[]
     */
    public function collectMethodCallNamesInChain(\PhpParser\Node\Expr\MethodCall $desiredMethodCall) : array
    {
        $methodCalls = $this->collectAllMethodCallsInChain($desiredMethodCall);
        $methodNames = [];
        foreach ($methodCalls as $methodCall) {
            $methodNames[] = $this->nodeNameResolver->getName($methodCall->name);
        }
        return $methodNames;
    }
    /**
     * @return MethodCall[]
     */
    public function collectAllMethodCallsInChain(\PhpParser\Node\Expr\MethodCall $methodCall) : array
    {
        $chainMethodCalls = [$methodCall];
        // traverse up
        $currentNode = $methodCall->var;
        while ($currentNode instanceof \PhpParser\Node\Expr\MethodCall) {
            $chainMethodCalls[] = $currentNode;
            $currentNode = $currentNode->var;
        }
        // traverse down
        if (\count($chainMethodCalls) === 1) {
            $currentNode = $methodCall->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::PARENT_NODE);
            while ($currentNode instanceof \PhpParser\Node\Expr\MethodCall) {
                $chainMethodCalls[] = $currentNode;
                $currentNode = $currentNode->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::PARENT_NODE);
            }
        }
        return $chainMethodCalls;
    }
    /**
     * @return MethodCall[]
     */
    public function collectAllMethodCallsInChainWithoutRootOne(\PhpParser\Node\Expr\MethodCall $methodCall) : array
    {
        $chainMethodCalls = $this->collectAllMethodCallsInChain($methodCall);
        foreach ($chainMethodCalls as $key => $chainMethodCall) {
            if (!$chainMethodCall->var instanceof \PhpParser\Node\Expr\MethodCall && !$chainMethodCall->var instanceof \PhpParser\Node\Expr\New_) {
                unset($chainMethodCalls[$key]);
                break;
            }
        }
        return \array_values($chainMethodCalls);
    }
    /**
     * Checks "$this->someMethod()->anotherMethod()"
     *
     * @param string[] $methods
     */
    public function isTypeAndChainCalls(\PhpParser\Node $node, \PHPStan\Type\Type $type, array $methods) : bool
    {
        if (!$node instanceof \PhpParser\Node\Expr\MethodCall) {
            return \false;
        }
        // node chaining is in reverse order than code
        $methods = \array_reverse($methods);
        foreach ($methods as $method) {
            if (!$this->nodeNameResolver->isName($node->name, $method)) {
                return \false;
            }
            $node = $node->var;
        }
        $variableType = $this->nodeTypeResolver->resolve($node);
        if ($variableType instanceof \PHPStan\Type\MixedType) {
            return \false;
        }
        return $variableType->isSuperTypeOf($type)->yes();
    }
    /**
     * @return \PhpParser\Node\Expr|\PhpParser\Node\Name
     */
    public function resolveRootExpr(\PhpParser\Node\Expr\MethodCall $methodCall)
    {
        $callerNode = $methodCall->var;
        while ($callerNode instanceof \PhpParser\Node\Expr\MethodCall || $callerNode instanceof \PhpParser\Node\Expr\StaticCall) {
            $callerNode = $callerNode instanceof \PhpParser\Node\Expr\StaticCall ? $callerNode->class : $callerNode->var;
        }
        return $callerNode;
    }
    public function resolveRootMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall) : ?\PhpParser\Node\Expr\MethodCall
    {
        $callerNode = $methodCall->var;
        while ($callerNode instanceof \PhpParser\Node\Expr\MethodCall && $callerNode->var instanceof \PhpParser\Node\Expr\MethodCall) {
            $callerNode = $callerNode->var;
        }
        if ($callerNode instanceof \PhpParser\Node\Expr\MethodCall) {
            return $callerNode;
        }
        return null;
    }
    private function isCall(\PhpParser\Node\Expr $expr) : bool
    {
        if ($expr instanceof \PhpParser\Node\Expr\MethodCall) {
            return \true;
        }
        return $expr instanceof \PhpParser\Node\Expr\StaticCall;
    }
    private function isMethodCallCreatingNewInstance(\PhpParser\Node\Expr\MethodCall $methodCall) : bool
    {
        $classMethod = $this->astResolver->resolveClassMethodFromMethodCall($methodCall);
        if (!$classMethod instanceof \PhpParser\Node\Stmt\ClassMethod) {
            return \false;
        }
        /** @var Return_[] $returns */
        $returns = $this->nodeFinder->findInstanceOf($classMethod, \PhpParser\Node\Stmt\Return_::class);
        foreach ($returns as $return) {
            $expr = $return->expr;
            if (!$expr instanceof \PhpParser\Node\Expr) {
                continue;
            }
            if (!$this->isNewInstance($expr)) {
                continue;
            }
            return \true;
        }
        return \false;
    }
    private function isNewInstance(\PhpParser\Node\Expr $expr) : bool
    {
        if ($expr instanceof \PhpParser\Node\Expr\Clone_ || $expr instanceof \PhpParser\Node\Expr\New_) {
            return \true;
        }
        return (bool) $this->betterNodeFinder->findFirstPreviousOfNode($expr, function (\PhpParser\Node $node) use($expr) : bool {
            if (!$node instanceof \PhpParser\Node\Expr\Assign) {
                return \false;
            }
            if (!$this->nodeComparator->areNodesEqual($node->var, $expr)) {
                return \false;
            }
            return $node->expr instanceof \PhpParser\Node\Expr\Clone_ || $node->expr instanceof \PhpParser\Node\Expr\New_;
        });
    }
}
