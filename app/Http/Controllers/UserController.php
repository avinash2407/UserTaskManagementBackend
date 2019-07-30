<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordEmail;
use App\Mail\WelcomeEmail;
use App\Mail\WelcomeEmailTwo;
use App\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Cookie;

class UserController extends Controller
{
    private $request;
    public function jwt(User $user)
    {
        $payload = [
            'iss' => "lumen-jwt",
            'sub' => $user->id,
            'iat' => time(),
            'ide' => 'logintoken',
            'exp' => time() + 60 * 60,
        ];
        return JWT::encode($payload, env('JWT_SECRET'));
    }
    public function jwtmail(User $user)
    {
        $payload = [
            'iss' => "lumen-jwt",
            'sub' => $user->id,
            'ide' => "mail",
            'iat' => time(),
            'exp' => time() + 60 * 60,
        ];
        return JWT::encode($payload, env('JWT_SECRET'));
    }
    public function signup(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required|min:3',
            'password' => 'required|min:8|regex:[(?=.*[A-Z])(?=.*[0-9])(?=.*[a-z])(?=.*[@#$%^&])]',
        ]);
        $checkuser = User::where('email', $request->input('email'))->first();
        if ($checkuser === null) {
            $user = new \App\User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->password = Hash::make($request->input('password'));
            $user->save();
            $result = User::where('email', $request->input('email'))->first();
            if ($result !== null) {
                $user->created_by = $result->id;
                $user->save();
            }
            Mail::to($result->email)->queue(new WelcomeEmail($result));
            return response()->json([
                'name' => $user->name,
                'email' => $user->email,
            ], 201);
        }
        return response()->json([
            'error' => "Email already registered",
        ], 400);
    }
    public function create(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required|min:3',
            'password' => 'required|min:8|regex:[(?=.*[A-Z])(?=.*[0-9])(?=.*[a-z])(?=.*[@#$%^&])]',
        ]);
        $currentuser = Helper::retuser($request);
        $checkuser = User::where('email', $request->input('email'))->first();
        if ($currentuser->role === "Admin") {
            if ($checkuser === null) {
                $user = new \App\User;
                $user->name = $request->input('name');
                $user->email = $request->input('email');
                $user->password = Hash::make($request->input('password'));
                $user->save();
                $result = User::where('email', $request->input('email'))->first();
                if ($result !== null) {
                    $admin = Helper::retuser($request);
                    $user->created_by = $admin->id;
                    $user->save();
                }
                Mail::to($result->email)->queue(new WelcomeEmailTwo($result, $currentuser));
                return response()->json([
                    'name' => $user->name,
                    'email' => $user->email,
                ], 201);
            }
            return response()->json([
                'error' => "Email already registered",
            ], 400);
        }
        return response()->json([
            'error' => 'UnAuthorized',
        ], 401);
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $result = User::where('email', $request->input('email'))->whereNull('deleted_by')->first();
        if ($result === null) {
            //$tkn
            return response()->json([
                'error' => 'User does not exist',
            ], 400);
        }
        if (!Hash::check($request->input('password'), $result->password)) {
            return response()->json([
                'error' => 'Wrong Password',
            ], 400);
        }
        $value = $request->cookie('tokencookie');
        return response()->json([
            'token' => $this->jwt($result),
            'email' => $result->email,
            'name' => $result->name,
            'role' => $result->role,
            'id' => $result->id,
            'cookie' => $value,
        ], 200)->withCookie(new Cookie('tokencookie', $this->jwt($result), strtotime('now + 60 minutes'), '/', '', false, false))
            ->withCookie(new Cookie('userid', $result->id, strtotime('now + 60 minutes'), '/', '', false, false))
            ->withCookie(new Cookie('role', $result->role, strtotime('now + 60 minutes'), '/', '', false, false));
    }
    public function delete(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
        ]);
        $user = Helper::retuser($request);
        if ($user->role === 'Admin') {
            $token = $request->input('token');
            $result = User::where('email', $request->input('email'))->first();
            if ($result === null) {
                return response()->json([
                    'error' => 'User does not exist',
                ], 400);
            }
            if ($result->deleted_by === null && $result->role !== "Admin") {
                $result->deleted_by = $user->id;
                $result->deleted_at = Carbon::now()->timezone('ASIA/KOLKATA');
                $result->save();
                return response()->json([
                    'message' => 'success',
                ], 200);
            }
            if ($result->deleted_by !== null) {
                return response()->json([
                    'error' => 'User Already Deleted',
                ], 400);
            }
        }
        return response()->json([
            'error' => 'UnAuthorized',
        ], 401);
    }
    public function roleChange(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'role' => ['required', Rule::in(['Admin', 'Normal'])],
        ]);
        $user = Helper::retuser($request);
        if ($user->role === 'Admin') {
            $result = User::where('email', $request->input('email'))->whereNull('deleted_by')->first();
            if ($result === null) {
                return response()->json([
                    'error' => 'User does not exist',
                ], 400);
            }
            if ($result->role !== 'Admin') {
                $result->role = $request->input('role');
                $result->save();
                return response()->json([
                    'message' => 'success',
                ], 200);
            }
        }
        return response()->json([
            'error' => 'UnAuthorized',
        ], 401);
    }
    public function sendmail(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
        ]);
        $mail = $request->input('email');
        $user = User::where('email', $mail)->whereNull('deleted_by')->first();
        if ($user === null) {
            return response()->json([
                'error' => 'User does not exist',
            ], 400);
        }
        $token = $this->jwtmail($user);
        Mail::to($user->email)->queue(new ResetPasswordEmail($user, $token));
        return response()->json([
            'token' => $token,
        ], 200);
        return "hello";
    }
    public function passReset(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'newpassword' => 'required|min:8|regex:[(?=.*[A-Z])(?=.*[0-9])(?=.*[a-z])(?=.*[@#$%^&])]',
            'confirmpassword' => 'required|min:8|same:newpassword',
        ]);
        $token = $request->input('token');
        $results = JWT::decode($token, env('JWT_SECRET'), ['HS256']);
        $retuser = User::find($results->sub);
        $mail = $request->input('email');
        $umail = $retuser->email;
        if ($retuser->role === 'Admin' && $umail === $mail) {
            $passwd = Hash::make($request->input('newpassword'));
            $retuser->password = $passwd;
            $retuser->save();
            return response()->json([
                'message' => 'success',
            ], 200);
        }
    }
    public function listUsers(Request $request)
    {
        $user = Helper::retuser($request);
        $keyname = $request->input('keyname');
        $keymail = $request->input('keymail');
        $keyrole = $request->input('keyrole');
        $value = $request->cookie('tokencookie');
        $keycreatedby = $request->input('keycreatedby');
        if ($user->role === 'Admin') {
            $users = User::Join('users as u', 'users.created_by', '=', 'u.id')->select('users.id', 'users.name', 'users.email', 'users.role', 'u.name as created_by');
            if ($keymail) {
                $users = $users->where("users.email", "LIKE", "%$keymail%");
            }

            if ($keyname) {
                $users = $users->Where("users.name", "LIKE", "%$keyname%");
            }

            if ($keyrole) {
                $users = $users->Where("users.role", "LIKE", "%$keyrole%");
            }

            if ($keycreatedby) {
                $users = $users->Where("u.name", "LIKE", "%$keycreatedby%");
            }

            $userlist = $users->whereNull('users.deleted_by')->paginate(9);
            return response()->json(['cookie' => $value, 'listofusers' => $userlist], 200);
        }
        $users = User::Join('users as u', 'users.created_by', '=', 'u.id')->select('users.name', 'users.role');
        if ($keymail) {
            $users = $users->where("users.email", "LIKE", "%$keymail%");
        }

        if ($keyname) {
            $users = $users->Where("users.name", "LIKE", "%$keyname%");
        }

        if ($keyrole) {
            $users = $ysers->Where("users.role", "LIKE", "%$keyrole%");
        }

        if ($keycreatedby) {
            $users = $users->Where("u.name", "LIKE", "%$keycreatedby%");
        }

        $userlist = $users->whereNull('users.deleted_by')->paginate(9);
        return response()->json(['cookie' => $value, 'listofusers' => $userlist], 200);
    }
    public function profile(Request $request, $id)
    {
        $result = User::find($id);
        if ($result === null) {
            return response()->json([
                'error' => 'User does not exist',
            ], 400);
        }
        $user = Helper::retuser($request);
        if ($user->role === 'Admin') {
            return response()->json([
                'name' => $result->name,
                'email' => $result->email,
                'role' => $result->role,
                'created_by' => $result->created_by,
            ], 200);
        }
        return response()->json([
            'name' => $result->name,
            'created_by' => $result->created_by,
        ], 200);
    }
}
