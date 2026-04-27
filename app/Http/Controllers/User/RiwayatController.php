<?php 

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\RiwayatService;
use Illuminate\Http\Request;

class RiwayatController extends Controller
{
    public function __construct(protected RiwayatService $service) {}

    public function index(Request $request)
    {
        $userId  = $request->user()->user->id;
        $filters = $request->only(['tipe', 'status', 'bulan', 'tahun']);

        return response()->json([
            'success' => true,
            'data'    => $this->service->getRiwayat($userId, $filters),
        ]);
    }

    public function ringkasan(Request $request)
    {
        $userId = $request->user()->user->id;

        return response()->json([
            'success' => true,
            'data'    => $this->service->getRingkasan($userId),
        ]);
    }

    public function hapusSatu(Request $request, int $id)
    {
        $userId = $request->user()->user->id;
        $this->service->hapusSatu($userId, $id);

        return response()->json(['success' => true, 'message' => 'Riwayat dihapus.']);
    }

    public function hapusSemua(Request $request)
    {
        $userId = $request->user()->user->id;
        $tipe   = $request->query('tipe');  

        $this->service->hapusSemua($userId, $tipe ?: null);

        return response()->json(['success' => true, 'message' => 'Semua riwayat dihapus.']);
    }
}