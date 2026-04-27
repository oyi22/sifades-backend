<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller; 
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function __construct(protected UserService $service){ } 

    public function show (){
        $akun = Auth::user();
        $user = $akun->user->load('akun');

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function uploadFoto(Request $request) {
        $request->validate([
            'foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        $akun = Auth::user();
        $userId = $akun->user_id;

        $user = $this->service->uploadFTSelf($userId, $request->file('foto'));

        return response()->json([
            'success' => true,
            'message' => 'foto profile berhasil di update',
            'data' => [
                'foto_profile_url' => $user->foto_profile_url,
            ],
        ]);
    }

    public function deleteFoto(){
        $akun = Auth::user();
        $userId = $akun->user_id;


        $this->service->deleteFotoProfile($userId);

        return response()->json([
            'success' => true,
            'message' => 'foto profile berhasil di hapus',
        ]);
    }
}
