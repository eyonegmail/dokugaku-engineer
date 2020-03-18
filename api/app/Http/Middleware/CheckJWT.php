<?php

namespace App\Http\Middleware;

use Closure;
use Auth0\SDK\JWTVerifier;
use App\Traits\JsonRespondController;

class CheckJWT
{
    use JsonRespondController;

    /**
     * JWTアクセストークンを検証する
     *
     * @param  \Illuminate\Http\Request  $request - Illuminate HTTP Request object.
     * @param  \Closure  $next - Function to call when middleware is complete.
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $scopeRequired = null)
    {
        $accessToken = $request->bearerToken();
        if (empty($accessToken)) {
            return $this->respondUnauthorized('Bearer token missing');
        }

        $laravelConfig = config('laravel-auth0');
        $jwtConfig = [
            'authorized_iss' => $laravelConfig['authorized_issuers'],
            'valid_audiences' => [$laravelConfig['api_identifier']],
            'supported_algs' => $laravelConfig['supported_algs'],
        ];

        try {
            $jwtVerifier = new JWTVerifier($jwtConfig);
            $decodedToken = $jwtVerifier->verifyAndDecode($accessToken);
        } catch (\Exception $e) {
            return $this->respondUnauthorized($e->getMessage());
        }

        if ($scopeRequired && !$this->tokenHasScope($decodedToken, $scopeRequired)) {
            return $this->respondInsufficientScope('Insufficient scope');
        }

        return $next($request);
    }

    /**
     * トークンにスコープが設定されている場合はチェックする
     *
     * @param \stdClass $token - JWT access token to check.
     * @param string $scopeRequired - Scope to check for.
     *
     * @return bool
     */
    protected function tokenHasScope($token, $scopeRequired)
    {
        if (empty($token->scope)) {
            return false;
        }

        $tokenScopes = explode(' ', $token->scope);
        return in_array($scopeRequired, $tokenScopes);
    }
}
