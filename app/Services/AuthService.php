<?php
namespace App\Services;

use App\Models\Admin;
use App\Models\Akun;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function loginAdmin(array $data): array
    {
        $admin = Admin::where('username', $data['username'])->first();

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        $token = $admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        return [
            'token' => $token,
            'admin' => [
                'id'  => $admin->id,
                'nama'   => $admin->nama,
                'username' => $admin->username,
                'role' => 'admin',
            ],
        ];
    }

    public function loginUser(array $data): array
    {
        $akun = Akun::with('user')->where('username', $data['username'])->first();

        if (!$akun || !Hash::check($data['password'], $akun->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        if (!$akun->is_active) {
            throw ValidationException::withMessages(['username' => ['Akun Anda tidak aktif. Hubungi admin.'],]);
        }

        $akun->update(['last_login' => now()]);
        $token = $akun->createToken('user-token', ['role:user'])->plainTextToken;

        $user = $akun->user;

        return [
            'token' => $token,
            'user'  => [
                'id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'nik' => $user->nik,
                'jabatan' => $user->jabatan,
                'alamat'  => $user->alamat,
                'sudah_enrollment' => $user->sudahEnrollment(),
                'gagal_liveness' => $user->gagalLiveness(),
                'role'  => 'user',
            ],
        ];
    }

    public function logout($model): void
    {
        $model->currentAccessToken()->delete();
    }
}