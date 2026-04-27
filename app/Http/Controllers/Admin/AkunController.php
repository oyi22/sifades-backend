<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Akun;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AkunController extends Controller
{
    public function index(Request $request){

        $query = Akun::with('user');

        if ($request->search){
            $query->where('username', 'like', '%' . $request->search . '%');
        }

        $data = $query->latest()->paginate(10);

        return  response()->json([
            'success' => true,
            'data' => [
                'data' => $data->items(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total()
            ]
        ]);
    }

    public function toggle($id){

        $akun = Akun::findOrFail($id);
        $akun->update(['is_active' => !$akun->is_active]);

        return response()->json([
            'success' => true,
            'data' => $akun,
        ]);
    }

    public function generatePassword($user){
        $tanggal = Carbon::parse($user->tanggal_lahir);
        $ttl = strtolower($user->tempat_lahir) . $tanggal->format('dmY');

        $random = Str::upper(Str::random(2));

        return $ttl . $random;
    }

    public function resetPassword ($id){
        $akun = Akun::with('user')->findOrFail($id);

        if(!$akun->user){
            return response()->json([
                'success' => false,
                'message' => 'user tidak ditemukan',
            ], 400);
        }

        $passBaru = $this->generatePassword($akun->user);

        $akun->update([
            'password'=>bcrypt($passBaru),
            'must_change_password' => true
        ]);

        return response()->json([
            'success' => true,
            'message' => 'password berhasil direset',
            'password' => $passBaru
        ]);
    }

    public function destroy($id){
        $akun = Akun::findOrFail($id);
        $akun->delete();

        return response()->json([
            'success' => true,
            'message' => 'akun berhasil dihapus'
        ]);
    }
}
