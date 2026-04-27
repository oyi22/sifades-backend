<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AbsensiService;
use App\Services\ExportService;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    public function __construct(
        protected AbsensiService $absensiService,
        protected ExportService  $exportService,
    ) {}

    public function index(Request $request)
    {

        try {
            $data = $this->absensiService->getAllForAdmin(
            $request->only(['tanggal', 'bulan', 'tahun', 'status', 'user_id'])
        );
        }catch(\Throwable $e){
            return response()->json([
                'err' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function rekap(Request $request)
    {
        // $tanggal = $request->input('tanggal', today()->toDateString());
        // $data = $this->absensiService->getRekapHarian($tanggal);

        // return response()->json([
        //     'success' => true, 
        //     'data' => $data
        // ]);

        // debug 
        // try{
        //     $tanggal = $request->input('tanggal', today()->toDateString());
        //     $data = $this->absensiService->getRekapHarian($tanggal);

        //     return response()->json([
        //         'success' => true,
        //         'data' => $data,
        //     ]);
        // } catch(\Throwable $e){
        //     return response()->json([
        //         'err' => $e->getMessage(),
        //         'line' => $e->getLine(),
        //         'file' => $e->getFile(),
        //     ], 500);
        // }

        try {
            $dari = $request->input('dari');
            $sampai = $request->input('sampai');

            if ($dari && $sampai){
                return response()->json([
                    'success' => true,
                    'data' => [
                        'dari' => $dari,
                        'sampai' => $sampai,
                    ]
                ]);
            }

            $tanggal = $request->input('tanggal', today()->toDateString());
            $data = $this->absensiService->getRekapHarian($tanggal);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }catch(\Throwable $e){
        return response()->json([
            'err' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile(),
        ], 500);
    }
    }

    public function export(Request $request)
    {
        $request->validate(['tahun' => 'required|integer|min:2020|max:2100']);
        $path = $this->exportService->exportCsv($request->tahun);

        return response()->download($path)->deleteFileAfterSend(true);
    }
}