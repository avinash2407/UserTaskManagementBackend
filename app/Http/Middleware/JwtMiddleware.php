<?php
namespace App\Http\Middleware;

use Closure;
use App\User;
use Firebase\JWT\JWT;


class JwtMiddleware
{
	
	public function handle($request , Closure $next){
		$token = $request->cookie('tokencookie');
		if($token !== NULL){
			$results = JWT::decode($token,env('JWT_SECRET'),['HS256']);
			$user = User::find($results->sub);
			if($user === NULL){
				return response()->json([
					'error' => "User does not exist"
				],400);
			}
			else if($results->ide !== 'logintoken'){
				return response()->json([
					'error' => "UnAuthorized"
				],401);
			}
			return $next($request);
		}
		else{
			return response()->json([
				'error' => 'Need a token to authenticate'
			],401);
		}
	}
}