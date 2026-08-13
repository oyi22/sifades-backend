<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AbsensiService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
            $request->only(['tanggal', 'bulan', 'tahun', 'status', 'user_id', 'tipe']));
        } catch (\Throwable $e) {
        return response()->json([
            'err' => $e->getMessage(), 
            'line' => $e->getLine(), 'file' => $e->getFile()
        ], 500);
    }

    return response()->json([
        'success' => true, 
        'data' => $data
    ]);
    }

    public function rekap(Request $request)
    { 
        try {
        $tanggal = $request->input('tanggal', today()->toDateString());
        $tipe    = $request->input('tipe', 'masuk');
        $userId = $request->input('user_id') ? (int) $request->input('user_id') : null;

        $data    = $this->absensiService->getRekapHarian($tanggal, $tipe, $userId);
        

        return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json([
                'err' => $e->getMessage(), 
                'line' => $e->getLine(), 
                'file' => $e->getFile()
            ], 500);
        }
    }
    

    public function export(Request $request)
    {
        $request->validate([
            'tahun'   => 'required|integer|min:2020|max:2100',
            'bulan'   => 'nullable|integer|min:1|max:12',
            'tanggal' => 'nullable|date_format:Y-m-d',
            'user_id' => 'nullable|integer|exists:users,id',
            'format'  => 'nullable|in:xlsx,csv',
        ]);

        try {
            $bulan   = $request->input('bulan') ? (int) $request->bulan : null;
            $tanggal = $request->input('tanggal');
            $userId  = $request->input('user_id') ? (int) $request->user_id : null;
            $format  = $request->input('format', 'xlsx');

            $path = $format === 'csv'
                ? $this->exportService->exportCsv($request->tahun, $bulan, $tanggal, $userId)
                : $this->exportService->exportXlsx($request->tahun, $bulan, $tanggal, $userId);

            return response()->download($path)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('EXPORT ABSENSI ERR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'err'  => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $absensi = \App\Models\Absensi::findOrFail($id);
        $absensi->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi berhasil dihapus',
        ]);
    }
}