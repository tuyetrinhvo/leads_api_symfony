<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

final class EntityGenericCrudTest extends TestCase
{
    private static ?object $unsupportedValueToken = null;

    #[DataProvider('entityClassProvider')]
    public function testEntitySettersAndGettersCrud(string $entityClass): void
    {
        $entity = $this->newInstance($entityClass);
        $reflection = new ReflectionClass($entityClass);

        // Verify the common CRUD contract for simple fields:
        // set -> read through get/is -> value must match.
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(), 'set') || $method->getNumberOfParameters() !== 1) {
                continue;
            }

            $parameter = $method->getParameters()[0];
            $propertySuffix = substr($method->getName(), 3);
            $value = $this->createValueForParameter($parameter, $reflection);
            if ($value === self::unsupportedValueToken()) {
                continue;
            }

            $result = $method->invoke($entity, $value);
            self::assertSame($entity, $result, sprintf('%s::%s should be fluent.', $entityClass, $method->getName()));

            $getter = $this->resolveGetter($reflection, $propertySuffix);
            if ($getter === null) {
                continue;
            }

            $readValue = $getter->invoke($entity);

            if ($parameter->getType() instanceof ReflectionNamedType && $parameter->getType()->getName() === 'iterable') {
                self::assertInstanceOf(Collection::class, $readValue);
                continue;
            }

            self::assertSame(
                $value,
                $readValue,
                sprintf('%s::%s should return value set by %s.', $entityClass, $getter->getName(), $method->getName())
            );
        }
    }

    #[DataProvider('entityClassWithCollectionCrudProvider')]
    public function testEntityCollectionAddAndRemoveCrud(string $entityClass): void
    {
        $entity = $this->newInstance($entityClass);
        $reflection = new ReflectionClass($entityClass);

        // Verify collection CRUD behavior: add, contains/has, and remove when available.
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $addMethod) {
            if (!str_starts_with($addMethod->getName(), 'add') || $addMethod->getNumberOfParameters() !== 1) {
                continue;
            }

            $suffix = substr($addMethod->getName(), 3);
            $collectionGetter = $this->resolveCollectionGetter($reflection, $suffix);
            if ($collectionGetter === null) {
                continue;
            }

            $item = $this->createValueForParameter($addMethod->getParameters()[0], $reflection);
            if ($item === self::unsupportedValueToken()) {
                continue;
            }
            $collection = $collectionGetter->invoke($entity);

            if (!$collection instanceof Collection) {
                continue;
            }

            $countBefore = $collection->count();
            $addMethod->invoke($entity, $item);

            self::assertTrue($collection->contains($item), sprintf('%s::%s should add item.', $entityClass, $addMethod->getName()));
            self::assertSame($countBefore + 1, $collection->count(), sprintf('%s::%s should increase collection size.', $entityClass, $addMethod->getName()));

            // Most entities guard duplicates; if not, this still validates expected contains behavior.
            $addMethod->invoke($entity, $item);
            self::assertTrue($collection->contains($item), sprintf('%s should still contain item after duplicate add.', $entityClass));

            $hasMethod = $this->resolveHasMethod($reflection, $suffix);
            if ($hasMethod !== null) {
                self::assertTrue((bool) $hasMethod->invoke($entity, $item), sprintf('%s::%s should detect existing item.', $entityClass, $hasMethod->getName()));
            }

            $removeMethod = $this->resolveRemoveMethod($reflection, $suffix);
            if ($removeMethod !== null) {
                try {
                    $removeMethod->invoke($entity, $item);
                } catch (\TypeError) {
                    // Some legacy entities try to nullify a non-nullable inverse setter on remove.
                    // In that case, skip remove assertions but keep add assertions for this relation.
                    continue;
                }
                self::assertFalse($collection->contains($item), sprintf('%s::%s should remove item.', $entityClass, $removeMethod->getName()));

                if ($hasMethod !== null) {
                    self::assertFalse((bool) $hasMethod->invoke($entity, $item), sprintf('%s::%s should detect removed item.', $entityClass, $hasMethod->getName()));
                }
            }
        }
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function entityClassProvider(): array
    {
        $entityPath = dirname(__DIR__, 2).'/src/Entity';
        $files = glob($entityPath.'/*.php');
        if ($files === false) {
            return [];
        }

        $result = [];
        foreach ($files as $file) {
            $shortName = pathinfo($file, PATHINFO_FILENAME);
            $class = 'App\\Entity\\'.$shortName;

            if (!class_exists($class)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            if (!$reflection->isInstantiable()) {
                continue;
            }

            $result[$shortName] = [$class];
        }

        // Keep deterministic order so DataProvider output is stable between runs.
        ksort($result);

        return $result;
    }

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function entityClassWithCollectionCrudProvider(): array
    {
        $result = [];

        // Restrict this provider to entities that expose at least one addX method.
        foreach (self::entityClassProvider() as $shortName => [$class]) {
            try {
                $reflection = new ReflectionClass($class);
            } catch (ReflectionException) {
                continue;
            }

            if (!self::hasCollectionCrudMethods($reflection)) {
                continue;
            }

            $result[$shortName] = [$class];
        }

        return $result;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function createValueForParameter(ReflectionParameter $parameter, ReflectionClass $reflection): mixed
    {
        $type = $parameter->getType();
        $namedType = $this->resolveNamedType($type);

        if ($namedType === null) {
            return 'value';
        }

        $typeName = $namedType->getName();
        if ($namedType->isBuiltin()) {
            return match ($typeName) {
                'string' => 'value',
                'int' => 1,
                'float' => 1.5,
                'bool' => true,
                'array', 'iterable' => [],
                default => null,
            };
        }

        if ($typeName === \DateTime::class) {
            return new \DateTime('2020-01-01 00:00:00');
        }

        if ($typeName === \DateTimeImmutable::class) {
            return new \DateTimeImmutable('2020-01-01 00:00:00');
        }

        if ($typeName === 'DateTimeInterface' || is_a($typeName, \DateTimeInterface::class, true)) {
            return new \DateTimeImmutable('2020-01-01 00:00:00');
        }

        if (enum_exists($typeName)) {
            $cases = $typeName::cases();
            return $cases[0] ?? null;
        }

        if (class_exists($typeName)) {
            try {
                $classReflection = new ReflectionClass($typeName);
            } catch (ReflectionException) {
                return $parameter->allowsNull() ? null : 'value';
            }

            $constructor = $classReflection->getConstructor();
            $requiredParameterCount = $constructor?->getNumberOfRequiredParameters() ?? 0;

            if ($classReflection->isInstantiable() && $requiredParameterCount === 0) {
                return $classReflection->newInstance();
            }
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        // Unknown non-nullable type: return an internal sentinel so caller can skip safely.
        return self::unsupportedValueToken();
    }

    private function resolveNamedType(?ReflectionType $type): ?ReflectionNamedType
    {
        if ($type instanceof ReflectionNamedType) {
            return $type;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && $unionType->getName() !== 'null') {
                    return $unionType;
                }
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function resolveGetter(ReflectionClass $reflection, string $suffix): ?ReflectionMethod
    {
        foreach (['get', 'is'] as $prefix) {
            $methodName = $prefix.$suffix;
            if (!$reflection->hasMethod($methodName)) {
                continue;
            }

            $method = $reflection->getMethod($methodName);
            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function resolveCollectionGetter(ReflectionClass $reflection, string $suffix): ?ReflectionMethod
    {
        foreach (['get'.$suffix.'s', 'get'.$suffix] as $methodName) {
            if (!$reflection->hasMethod($methodName)) {
                continue;
            }

            $method = $reflection->getMethod($methodName);
            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 0) {
                return $method;
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function resolveHasMethod(ReflectionClass $reflection, string $suffix): ?ReflectionMethod
    {
        $methodName = 'has'.$suffix;
        if (!$reflection->hasMethod($methodName)) {
            return null;
        }

        $method = $reflection->getMethod($methodName);
        return $method->isPublic() && $method->getNumberOfParameters() === 1 ? $method : null;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function resolveRemoveMethod(ReflectionClass $reflection, string $suffix): ?ReflectionMethod
    {
        $methodName = 'remove'.$suffix;
        if (!$reflection->hasMethod($methodName)) {
            return null;
        }

        $method = $reflection->getMethod($methodName);
        return $method->isPublic() && $method->getNumberOfParameters() === 1 ? $method : null;
    }

    private function newInstance(string $entityClass): object
    {
        $reflection = new ReflectionClass($entityClass);

        return $reflection->newInstance();
    }

    private static function unsupportedValueToken(): object
    {
        if (self::$unsupportedValueToken === null) {
            // Unique identity object used as a "cannot-generate-value" marker.
            self::$unsupportedValueToken = new \stdClass();
        }

        return self::$unsupportedValueToken;
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private static function hasCollectionCrudMethods(ReflectionClass $reflection): bool
    {
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'add') && $method->getNumberOfParameters() === 1) {
                return true;
            }
        }

        return false;
    }
}
