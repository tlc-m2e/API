<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core;

/**
 * Class Container
 * Developed by Bastivan Consulting
 *
 * Simple Dependency Injection Container.
 */
class Container
{
    private static ?Container $instance = null;
    /** @var array<string, callable> */
    private array $services = [];
    /** @var array<string, object> */
    private array $instances = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $key, callable $resolver): void
    {
        $this->services[$key] = $resolver;
    }

    public function singleton(string $key, callable $resolver): void
    {
        $this->services[$key] = function ($container) use ($resolver, $key) {
            if (!isset($this->instances[$key])) {
                $this->instances[$key] = $resolver($container);
            }
            return $this->instances[$key];
        };
    }

    public function instance(string $key, object $instance): void
    {
        $this->instances[$key] = $instance;
        $this->services[$key] = fn() => $instance;
    }

    public function resolve(string $class): object
    {
        if (isset($this->services[$class])) {
            return $this->services[$class]($this);
        }

        return $this->autowire($class);
    }

    private function autowire(string $class): object
    {
        if (!class_exists($class)) {
            throw new \Exception("Class $class not found");
        }

        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
             throw new \Exception("Class $class is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                 // Try default value
                 if ($parameter->isDefaultValueAvailable()) {
                     $dependencies[] = $parameter->getDefaultValue();
                     continue;
                 }
                 throw new \Exception("Cannot resolve dependency {$parameter->getName()} for class $class");
            }

            $className = $type->getName();
            $dependencies[] = $this->resolve($className);
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
