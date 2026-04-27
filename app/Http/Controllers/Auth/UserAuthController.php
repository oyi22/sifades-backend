<?php 

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class UserAuthController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->auth->loginUser($data);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => $result,
        ]);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }

    public function me(Request $request)
    {
        $akun = $request->user()->load('user');
        return response()->json([
            'success' => true,
            'data' => $akun->user,
        ]);
    }
}