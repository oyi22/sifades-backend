<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AbsensiService;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(protected AbsensiService $service) {}
    public function scan(Request $request)
    {
        $request->validate([
            'user_key' => 'required|string',
            'recognized_key' => 'required|string',  
            'foto_absensi' => 'required|string',
            'confidence_score' => 'nullable|numeric',
            'lokasi'  => 'nullable|string',
            'latitude'  => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'tipe' => 'required|in:masuk,pulang',
        ]);
 
        $user = User::whereRaw("LOWER(REPLACE(nama_lengkap, ' ', '_')) = ?",
                [strtolower($request->user_key)])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }
 
        $recognized = User::whereRaw("LOWER(REPLACE(nama_lengkap, ' ', '_')) = ?",
                [strtolower($request->recognized_key)])->first();
 
        if (!$recognized || $recognized->id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Wajah tidak sesuai dengan akun yang sedang login.',
            ], 403);
        }

        try {
            $absensi = $this->service->simpanAbsensi($user->id, [
                'tipe' => $request->tipe,
                'foto_absensi' => $request->foto_absensi,
                'skor_kepercayaan'=> $request->confidence_score,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'alamat_lokasi'   => $request->lokasi,
            ]);
        } catch (\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Absensi berhasil dicatat.',
            'data'    => $absensi,
        ]);
    }

    public function cekStatus(Request $request)
    {
        $userId  = $request->user()->user->id;

        $absensiHariIni = \App\Models\Absensi::where('user_id', $userId)->whereDate('tanggal', today())->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'sudah_absen_masuk'  => $this->service->sudahAbsenMasuk($userId),
                'sudah_absen_pulang' => $this->service->sudahAbsenPulang($userId),
                'status_masuk' => $absensiHariIni?->status,
                'jam_masuk' => $absensiHariIni?->jam_masuk,
            ],
        ]);
    }

    // public function riwayat(Request $request)
    // {
    //     $userId = $request->user()->user->id;
    //     $data   = $this->service->getRiwayatUser($userId);

    //     return response()->json([
    //         'success' => true, 
    //         'data' => $data
    //     ]);
    // }

    public function validasiLokasi (Request $request){
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $result = $this->service->validasiLokasi(
            (float) $request->latitude,
            (float) $request->longitude
        );

        if(!$result['valid']){
            return response()->json([
                'success' => false,
                'message' => $result['pesan'] ?? 'Lokasi Anda Berada di Luar jangkauan',
                'data' => $result
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['pesan'],
            'data' => $result,
        ]);
    }

}