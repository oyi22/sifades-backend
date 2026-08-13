<?php 

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class UserAuthController extends Controller
{
    public function __construct(protected AuthService $auth) {}

    public function login(Request $request)
    {
        $key = $this->rateLimit($request);

        if(RateLimiter::tooManyAttempts($key, 5)){
            return response()->json([
                'message' => 'terlalu banyak percobaan login, coba lagi setelah 1 menit'
            ], 429);
        }

        $data = $request->validate([
            'username' => 'required|string|max:50|',
            'password' => 'required|string|max:100',
        ]);

        try {
             $result = $this->auth->loginUser($data);
             RateLimiter::clear($key);
             
            return response()->json([
                'success' => true,
                'message' => 'Login berhasil.',
                'data' => $result,
            ]);
        }catch(\Throwable $e){
            RateLimiter::hit($key, 60);
            usleep(random_int(100000, 300000));

            return response()->json([
                'message' => 'username atau password salah',
            ], 422);
        }
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

    public function rateLimit(Request $request): string{
        return Str::lower($request->input('username')).'|'.$request->ip();
    }
}