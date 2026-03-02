<?php

namespace Wonder\Http\Symfony;

use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Throwable;
use Wonder\Http\Symfony\Controllers\GatewayController;
use Wonder\Http\Symfony\Controllers\HealthController;

class ApiKernel
{
    private ContainerBuilder $container;
    private RouteCollection $routes;

    public function __construct()
    {
        $this->container = $this->buildContainer();
        $this->routes = $this->buildRoutes();
    }

    public function handle(Request $request): Response
    {
        $routePath = $this->resolveRoutePath($request);

        try {
            $context = (new RequestContext())->fromRequest($request);
            $matcher = new UrlMatcher($this->routes, $context);
            $parameters = $matcher->match($routePath);

            $controllerId = (string) ($parameters['_controller'] ?? '');
            unset($parameters['_controller'], $parameters['_route']);

            return $this->invokeController($controllerId, $request, $parameters);
        } catch (ResourceNotFoundException) {
            return $this->errorResponse(404, 'not_found', 'Route non trovata');
        } catch (MethodNotAllowedException $exception) {
            return $this->errorResponse(405, 'method_not_allowed', 'Metodo HTTP non consentito');
        } catch (Throwable $exception) {
            return $this->errorResponse(500, 'internal_error', 'Errore interno', $exception);
        }
    }

    private function resolveRoutePath(Request $request): string
    {
        $queryRoute = trim((string) $request->query->get('r', ''));
        if ($queryRoute !== '') {
            return str_starts_with($queryRoute, '/') ? $queryRoute : '/'.$queryRoute;
        }

        $pathInfo = $request->getPathInfo();
        if ($pathInfo === '' || $pathInfo === '/' || str_ends_with($pathInfo, '/index.php')) {
            return '/';
        }

        return $pathInfo;
    }

    private function invokeController(string $controllerId, Request $request, array $parameters): Response
    {
        if ($controllerId === '' || !$this->container->has($controllerId)) {
            throw new RuntimeException('Controller non registrato: '.$controllerId);
        }

        $controller = $this->container->get($controllerId);

        if (!is_callable($controller)) {
            throw new RuntimeException('Controller non invocabile: '.$controllerId);
        }

        $response = $controller($request, $parameters);

        if (!$response instanceof Response) {
            throw new RuntimeException('Il controller deve restituire una Response.');
        }

        return $response;
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register(GatewayController::class, GatewayController::class)->setPublic(true);
        $container->register(HealthController::class, HealthController::class)->setPublic(true);

        $container->compile();

        return $container;
    }

    private function buildRoutes(): RouteCollection
    {
        $routes = new RouteCollection();

        $routes->add('sf8_gateway', new Route(
            '/',
            ['_controller' => GatewayController::class],
            [],
            [],
            '',
            [],
            ['GET']
        ));

        $routes->add('sf8_health', new Route(
            '/health',
            ['_controller' => HealthController::class],
            [],
            [],
            '',
            [],
            ['GET']
        ));

        return $routes;
    }

    private function errorResponse(int $status, string $error, string $message, ?Throwable $exception = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'error' => $error,
            'message' => $message,
        ];

        if ($exception !== null && $this->isDebug()) {
            $payload['debug'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        return new JsonResponse($payload, $status);
    }

    private function isDebug(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
