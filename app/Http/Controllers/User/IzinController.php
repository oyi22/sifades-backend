<?php 

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\IzinService;
use Illuminate\Http\Request;

class IzinController extends Controller
{
    public function __construct(protected IzinService $service) {}

    public function ajukan(Request $request)
    {
        $tipe = $request->input('tipe');
        $rules = [
            'tipe' => 'required|in:dinas,sakit,lainnya',
            'tanggal_mulai' => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ];

        if (in_array($tipe, ['dinas', 'sakit'])){
            $rules['file_surat'] = 'required|file|mimes:pdf,doc,docx,jpg,jpeg|max:5120';
            $rules['alasan'] = 'nullable|string|max:1000';
        } else {
            $rules['file_surat'] = 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg|max:5120';
            $rules['alasan'] = 'required|string|min:10|max:1000';
        }

        $data = $request->validate($rules);
        if($request->hasFile('file_surat')){
            $data['file_surat'] = $request->file('file_surat');
        }

        $userId  = $request->user()->id;

        try {
            $result = $this->service->ajukanIzin($userId, $data); 
        } catch (\Exception $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'izin berhasil diajukan, menunggu validasi admin',
            'data' => $result['izin'],
            'alerts' => $result['alerts'],
        ], 201);
    }

    public function riwayat(Request $request)
    {
        $userId = $request->user()->user->id;
         
        return response()->json([
            'success' => true, 
            'data' => $this->service->getRiwayatUser($userId),
        ]);
    }

    public function sisaSlot (Request $request){
        $user = $request->user();
        $userId = $user->user->id ?? $user->id;

        return response()->json([
            'success' => true,
            'sisa_slot' => $this->service->getSisaSlot($userId),
        ]);
    }

    public function izinAktif(Request $request){
        $user = $request->user();
        $userId = $request->user()->user->id;
        return response()->json([
            'success' => true,
            'data' => $this->service->getIzinAktif($userId),
        ]);
    }
}