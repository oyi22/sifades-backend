<?php 
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\IzinService;
use App\Models\Izin;
use App\Models\NotifikasiLog;
use Illuminate\Http\Request; 

class IzinController extends Controller
{
    public function __construct(protected IzinService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->getAllForAdmin($request->only(['status', 'tipe','tanggal']));

        return response()->json([
            'success' => true, 
            'data' => $data
        ]);
    }

    public function show (int $id){
        $izin = Izin::with(['user', 'validator'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $izin
        ]);
    }

    public function validasi(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:disetujui,ditolak',
            'catatan_admin' => 'nullable|string|max:500',
        ]);

        try{
            $izin = $this->service->validasi(
                $id, $request->user()->id,
                $data['status'],
                $data['catatan_admin'] ?? null,
            );
        } catch (\Exception $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],422);
        }

        return response()->json([
            'success' => true,
            'message' => 'izin berhasil divalidasi',
            'data' => $izin, 
        ]);
    }

    public function perpanjang (Request $request, int $id){
        $data = $request->validate([
            'tanggal_selesai_baru' => 'required|date|after:today',
        ]);

        try {
            $izin = $this->service->perpanjang(
                $id, 
                $request->user()->id, $data['tanggal_selesai_baru']
            );
        } catch (\Exception $e){
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'izin berhasil diperpanjang',
            'data' => $izin,
        ]);
    }

    public function destroy(int $id){
         $izin = Izin::findOrFail($id);
         $izin->delete();
         return response()->json([
            'success' => true,
            'message' => 'data izin berhasil dihapus',
         ]);
    }

    public function riwayatNotif(int $id){
        $logs = NotifikasiLog::with('user')->where('izin_id', $id)
                ->orderBy('created_at', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function semuaNotif(Request $request){
        $logs = NotifikasiLog::with(['user', 'izin'])->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}