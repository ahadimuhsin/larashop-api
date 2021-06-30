<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'username' => 'required|string|unique:users|min:4|max:30',
            'phone' => 'required|digits_between:10,12',
            'address' => 'required|string|min:8',
            'avatar' => 'nullable|mimes:jpeg,jpg,png,bmp',
            'password_confirmation' => 'required|same:password'
        ]);

        $status = "error";
        $message = "";
        $code = 400;
        $data = "";
        $token = "";

        if($validator->fails()){
            $errors = $validator->errors();
            $message = $errors;
        }
        else{
            $file_avatar = null;
            if($request->file('avatar')){
                $file_avatar = $request->file('avatar')->store('avatars', 'public');
            }
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'roles' => json_encode(["CUSTOMER"]),
                'username' => $request->username,
                'address' => $request->address,
                'phone'=> $request->phone,
                'avatar' => $file_avatar
            ]);

            if($user){
                $token = $user->createToken('Laravel Password Grant Client')->accessToken;
                $status = "Success";
                $message = "register berhasil";
                $data = $user->toArray();
                $code = 200;
            }
            else{
                $message = 'register failed';
            }
        }

        return response()->json([
            'status' => $status,
            'message'=> $message,
            'data' => $data,
            'token' => $token,
        ], $code);
    }

    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'string|email|max:255',
            'username' => 'string|max:255',
            'password' => 'required|string'
        ]);

        if($validator->fails()){
            $errors = $validator->errors();
            return response()->json([
                'data' => [
                    'message' => $errors
                ]
                ], 400);
        }
        $status = "error";
        $message = '';
        $code = 401;
        $data = null;
        $token = "";
        $user = User::where('email', $request->email)->orWhere('username', $request->email)->first();
        if ($user){
            if(Hash::check($request->password, $user->password)){
                $token = $user->createToken('Laravel Password Grant Client')->accessToken;
                $data = $user->toArray();
                $user->update([
                    'last_login' => Carbon::now()->toDateTimeString()
                ]);
                $status = "success";
                $code = 200;
            }
            else{
                $message = "Password salah";
            }
        }
        else{
            $message = "Email/Username tidak ditemukan";
        }

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'token' => $token
        ], $code);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();
        return response()->json([
            'message' => 'Logout sukses!'
        ], 200);
    }
}
