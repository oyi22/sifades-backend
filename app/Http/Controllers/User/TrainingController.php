<?php
// app/Http/Controllers/User/TrainingController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\TrainingService;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function __construct(protected TrainingService $service) {}

    public function status(Request $request)
    {
        $userId = $request->user()->user->id;
        $data   = $this->service->getPoseStatus($userId);

        return response()->json([
            'success' => true, 
            'data' => $data
        ]);
    }

    public function simpanFrame(Request $request)
    {
        $data = $request->validate([
            'pose'  => 'required|in:netral,senyum,kedip,kanan,kiri',
            'frame' => 'required|string',  
        ]);

        $userId = $request->user()->user->id;
        $result = $this->service->simpanFrameTraining($userId, $data['pose'], $data['frame']);

        return response()->json([
            'success' => true, 
            'data' => $result
        ]);
    }

    public function selesai(Request $request)
    {
        $userId = $request->user()->user->id;
        $user = $this->service->selesaikanTraining($userId);

        return response()->json([
            'success' => true,
            'message' => 'Training selesai! Anda sekarang bisa melakukan absensi.',
            'data'  => $user,
        ]);
    }

    public function reset(Request $request)
    {
        $userId = $request->user()->user->id;
        $this->service->resetTraining($userId);

        return response()->json([
            'success' => true,
            'message' => 'Data training direset. Silakan ulangi training.',
        ]);
    }
}