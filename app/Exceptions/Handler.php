<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($this->isHttpException($e)) {
            if ($e->getStatusCode() == 404) {
                return response()->json([
                    'success' => false,
                    'messages' => (object) [
                        'type' => 'error',
                        'message' => __('Not Found'),
                    ],
                    'data' => null,
                ], 404);
            } else if ($e->getStatusCode() == 403) {
                return response()->json([
                    'success' => false,
                    'messages' => (object) [
                        'type' => 'error',
                        'message' => __('Forbidden'),
                    ],
                    'data' => null,
                ], 403);
            } else if ($e->getStatusCode() == 419) {
                return response()->json([
                    'success' => false,
                    'messages' => (object) [
                        'type' => 'error',
                        'message' => __('Page Expired'),
                    ],
                    'data' => null,
                ], 503);
            } else if ($e->getStatusCode() == 429) {
                return response()->json([
                    'success' => false,
                    'messages' => (object) [
                        'type' => 'error',
                        'message' => __('Too Many Requests'),
                    ],
                    'data' => null,
                ], 503);
            } else if ($e->getStatusCode() == 500) {
                return response()->json([
                    'success' => false,
                    'messages' => (object) [
                        'type' => 'error',
                        'message' => __('Internal Server Error'),
                    ],
                    'data' => null,
                ], 500);
            }
        }

        return parent::render($request, $e);
    }
}
