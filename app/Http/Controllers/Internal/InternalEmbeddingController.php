<?php 

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InternalEmbeddingController extends Controller
{
    public function store(Request $request)
    {
        $secret = $request->header('X-Internal-Secret');
        if ($secret !== env('INTERNAL_SECRET', 'rahasia123')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'embeddings'   => 'required|array|min:1',
            'embeddings.*' => 'required|array',
        ]);

        $userId = $request->input('user_id');
        $embeddings = $request->input('embeddings');
        $now  = Carbon::now();

        $rows = array_map(fn($emb) => [
            'user_id'        => $userId,
            'face_embeddings'=> json_encode($emb),
            'created_at'     => $now,
            'updated_at'     => $now,
        ], $embeddings);

        DB::table('embedding')->insert($rows);

        return response()->json([
            'message' => 'Embedding berhasil disimpan',
            'count'   => count($rows),
        ]);
    }
}