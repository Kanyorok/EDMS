<?php

namespace App\Http\Controllers;

use App\Exceptions\ErroredException;
use App\Support\SecureExceptionResponder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\ControllerMiddlewareOptions;
use Throwable;

abstract class Controller //implements HasMiddleware
{
    use AuthorizesRequests;

    /**
     * Get the middleware that should be assigned to the controller.
     */
    /*  public static function middleware(): array
      {
          return [];
      }*/

    /**
     * The middleware registered on the controller.
     *
     * @var array
     */
    protected array $middleware = [];

    /**
     * Register middleware on the controller.
     *
     * @param array|\Closure|string $middleware
     * @param array $options
     * @return \Illuminate\Routing\ControllerMiddlewareOptions
     */
    public function middleware(array|\Closure|string $middleware, array $options = []): ControllerMiddlewareOptions
    {
        foreach ((array) $middleware as $m) {
            $this->middleware[] = [
                                   'middleware' => $m,
                                   'options' => &$options,
                                  ];
        }

        return new ControllerMiddlewareOptions($options);
    }

    /**
     * Get the middleware assigned to the controller.
     *
     * @return array
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Execute an action on the controller.
     *
     * @param string $method
     * @param array $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function callAction($method, $parameters)
    {
        return $this->{$method}(...array_values($parameters));
    }

    public function succeeded(string $message, string $route = '', array $data = [], int $status = 202): JsonResponse
    {
        return response()->json(array_merge(
            ['message' => $message],
            ($route === '') ? [] : [ 'route' => $route],
            $data
        ), $status);
    }

    public function errored(string $message, array $data = [], int $status = 400): JsonResponse
    {
        return response()->json(array_merge(['message' => $message], $data), $status);
    }

    protected function unexpectedBack(
        Throwable $exception,
        string $message,
        bool $withInput = false,
        string $level = 'error',
        ?Request $request = null,
    ): RedirectResponse {
        $request ??= request();
        $response = redirect()->back();

        if ($withInput) {
            $response->withInput();
        }

        if ($exception instanceof ErroredException) {
            return $response->with($level, $exception->getMessage());
        }

        $reference = SecureExceptionResponder::capture($exception, $message, $request);

        return $response->with(
            $level,
            "{$message} Please try again or contact support with reference {$reference}.",
        );
    }

    public function br_response(string $status, string $message, array $extra = []): JsonResponse
    {
        return response()->json([
                                 'ourBranchID' => null,
                                 'resp' => [
                                                   'status' => $status,
                                                   'outputJSON' => (! empty($extra)) ? json_encode($extra) : '',
                                                   'message' => $message,
                                                  ],
                                ]);
    }
}
