<?php

namespace Sicet7\HTTP\Attributes;

use DI\Definition\Exception\InvalidDefinition;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Roave\BetterReflection\Reflection\Attribute\ReflectionAttributeHelper;
use Roave\BetterReflection\Reflector\Reflector as ReflectorInterface;
use Sicet7\HTTP\Interfaces\RouteCollectorInterface;
use Sicet7\HTTP\Middlewares\DeferredMiddleware;
use Sicet7\Plugin\Container\Interfaces\PluginInterface;
use Sicet7\Plugin\Container\MutableDefinitionSourceHelper;

readonly class HttpAttributeLoaderPlugin implements PluginInterface
{
    /**
     * @param ReflectorInterface $reflector
     */
    public function __construct(
        public ReflectorInterface $reflector
    ) {
    }

    /**
     * @param MutableDefinitionSourceHelper $source
     * @return void
     * @throws InvalidDefinition
     */
    public function register(MutableDefinitionSourceHelper $source): void
    {
        $source->value(HttpAttributeLoaderPlugin::class, $this);
        $source->decorate(RouteCollectorInterface::class, function (
            RouteCollectorInterface $routeCollector,
            ContainerInterface $container
        ): RouteCollectorInterface {
            $plugin = $container->get(HttpAttributeLoaderPlugin::class);
            foreach ($plugin->reflector->reflectAllClasses() as $class) {
                if (!$class->implementsInterface(RequestHandlerInterface::class)) {
                    continue;
                }

                $attributes = $class->getAttributes();

                if (empty($attributes)) {
                    continue;
                }

                //collect routes
                $routeAttributes = ReflectionAttributeHelper::filterAttributesByInstance($attributes, Route::class);
                $routeClassName = $class->getName();
                if (empty($routeAttributes)) {
                    continue;
                }

                //collect middlewares for the given routes
                /** @var MiddlewareInterface[] $middlewares */
                $middlewares = [];
                foreach ($attributes as $middlewareAttribute) {
                    $attributeClass = $middlewareAttribute->getClass();
                    $attributeClassName = $attributeClass->getName();

                    $isDeferred = (
                        $attributeClassName === Middleware::class ||
                        $attributeClass->isSubclassOf(Middleware::class)
                    );

                    if(
                        !$isDeferred &&
                        !$attributeClass->implementsInterface(MiddlewareInterface::class)
                    ) {
                        continue;
                    }

                    $args = $middlewareAttribute->getArguments();
                    if (empty($args)) {
                        $middlewares[] = (
                            $isDeferred ?
                                new DeferredMiddleware($container, (new $attributeClassName())->class) :
                                new $attributeClassName()
                        );
                    } else {
                        $middlewares[] = (
                            $isDeferred ?
                                new DeferredMiddleware($container, (new $attributeClassName(...$args))->class) :
                                new $attributeClassName(...$args)
                        );
                    }
                }

                //register routes
                foreach ($routeAttributes as $routeAttribute) {
                    $attributeClass = $routeAttribute->getClass();
                    $attributeClassName = $attributeClass->getName();
                    $args = $routeAttribute->getArguments();
                    /** @var Route $routeAttributeInstance */
                    $routeAttributeInstance = (empty($args) ? new $attributeClassName() : new $attributeClassName(...$args));
                    $httpRoute = $routeAttributeInstance->makeRoute($container, $routeClassName);
                    foreach ($middlewares as $middleware) {
                        $httpRoute->addMiddleware($middleware);
                    }
                    $routeCollector->add($httpRoute);
                }
            }
            return $routeCollector;
        });
    }
}