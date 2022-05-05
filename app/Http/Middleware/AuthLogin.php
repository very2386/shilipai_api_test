<?php

namespace App\Http\Middleware;

use App\Models\UserTokenLog;
use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class AuthLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {       
        
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (JWTException $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException){
                $message = 'Token is Invalid';
            }else if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                $message = 'Token is Expired';
            }else{
                $message = 'Authorization Token not found';
            }

            UserTokenLog::insert([
                'user_id'    => '',
                'token'      => request()->bearerToken(),
                'error'      => $message,
                'status'     => 'error',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return response()->json(['status' => false, 'type' => 'token', 'error' => ['message' => $message]]);
        } 
        
        return $next($request);
    }
}
