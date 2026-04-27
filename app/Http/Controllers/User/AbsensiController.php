<?php
// app/Http/Controllers/User/AbsensiController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\AbsensiService;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(protected AbsensiService $service) {}

    public function scan(Request $request)
    {
        $validate =  $request->validate([
            'foto_absensi' => 'required|string',    
            'skor_kepercayaan' => 'nullable|numeric',
            'lokasi' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $userId = $request->user()->user->id;
        $absensi = $this->service->simpanAbsensi($userId, $$validate);

        return response()->json([
            'success' => true,
            'message' => 'Absensi berhasil dicatat.',
            'data' => $absensi,
        ]);
    }

    public function cekStatus(Request $request)
    {
        $userId  = $request->user()->user->id;
        $sudah   = $this->service->sudahAbsenHariIni($userId);

        return response()->json([
            'success' => true,
            'data'    => ['sudah_absen' => $sudah],
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
}