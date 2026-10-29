<?php

namespace Modules\Connector\Http\Controllers\Api;

use Illuminate\Routing\Controller;

class ApiController extends Controller
{
    protected $statusCode;

    protected $perPage;

    public function __construct()
    {
        $this->perPage = 10;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function respondUnauthorized($message = 'Unauthorized action.')
    {
        return $this->setStatusCode(403)->respondWithError($message);
    }

    public function respond($data)
    {
        return response()->json($data);
    }

    public function modelNotFoundExceptionResult($e)
    {
        return $this->setStatusCode(404)->respondWithError($e->getMessage());

        // return [
        //         'status' => 404,
        //         'class' => method_exists($e, 'getModel') ? $e->getModel() : '',
        //         'value' => method_exists($e, 'getIds') ? $e->getIds() : '',
        //         'message' =>
        //     ];
    }

    public function otherExceptions($e)
    {
        $msg = is_object($e) ? $e->getMessage() : $e;

        return $this->setStatusCode(400)->respondWithError($msg);

        // return [
        //         'status' => 400,
        //         'message' => $e->getMessage()
        //     ];
    }

    protected function respondWithError($message)
    {
        return response()->json([
            'error' => [
                'message' => $message,
            ],
        ], $this->getStatusCode());
    }

    /**
     * Retrieves current passport client from request
     */
    public function getClient()
    {
        $bearerToken = request()->bearerToken();
        if (empty($bearerToken)) {
            return null;
        }

        $parts = explode('.', $bearerToken);
        if (count($parts) < 2) {
            return null;
        }

        $payload = $parts[1];
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $padding = strlen($payload) % 4;
        if ($padding > 0) {
            $payload .= str_repeat('=', 4 - $padding);
        }

        $decodedPayload = json_decode(base64_decode($payload), true);
        $tokenId = $decodedPayload['jti'] ?? null;
        if (empty($tokenId)) {
            return null;
        }

        $token = \Laravel\Passport\Token::find($tokenId);

        return $token ? $token->client : null;
    }
}
