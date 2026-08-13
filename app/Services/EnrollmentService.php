<?php

namespace App\Services;

use App\Models\Enrollments;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{ 
    public function checkStatus(Request $request)
    {
        $akun = $request->user();
        $user = $akun->user;

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan'], 404);
        }

        $userId = $user->id;

        $hasEmbedding  = DB::table('embedding')->where('user_id', $userId)->exists();
        $pendingExists = Enrollments::where('user_id', $userId)->where('status', 'pending')->exists();

        return response()->json([
            'success'  => true,
            'enrollment_enabled' => (bool) $user->enrollment_enable,
            'has_embedding'      => $hasEmbedding,
            'pending_enrollment' => $pendingExists,
        ]);
    }
    public function store(Request $request)
    {
        $akun = $request->user();
        $user = $akun->user; 

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $request->validate([
            'video' => 'required|file|mimetypes:video/webm,video/mp4,video/x-matroska|max:51200',
        ]);

        if (!$user->enrollment_enable) {
            return response()->json(['message' => 'Enrollment belum diizinkan admin'], 403);
        }

        $alreadyPending = Enrollments::where('user_id', $user->id)->where('status', 'pending')->exists();

        if ($alreadyPending) {
            return response()->json(['message' => 'Enrollment sedang menunggu review admin'], 409);
        }

        $path = $request->file('video')->store("enrollments/{$user->id}", 'local');

        $enrollment = Enrollments::create([
            'user_id'    => $user->id,
            'vidio_path' => $path,
            'status'     => 'pending',
        ]);

        return response()->json([
            'message'       => 'Enrollment berhasil diupload, menunggu review admin',
            'enrollment_id' => $enrollment->id,
        ], 201);
    }
 
    public function index()
    {
        $enrollments = Enrollments::with('user:id,nama_lengkap,jabatan')->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($enrollments);
    } 

    public function approve(Request $request, Enrollments $enrollment)
    {
        if ($enrollment->status !== 'pending') {
            return response()->json(['message' => 'Enrollment sudah diproses'], 409);
        }

        $videoFullPath = storage_path('app/' . $enrollment->vidio_path);  

        $videoFullPath = storage_path('app/private/' . $enrollment->vidio_path);
        if (!file_exists($videoFullPath)) {
            $videoFullPath = storage_path('app/' . $enrollment->vidio_path);
        }  

        if (!file_exists($videoFullPath)) {
        return response()->json(['message' => 'File video tidak ditemukan'], 404);
        }
        $mlServiceUrl = config('services.ml.url', env('ML_SERVICE_URL', 'http://127.0.0.1:5000'));

        try {
        $response = Http::timeout(120)
            ->asMultipart()
            ->attach('video', file_get_contents($videoFullPath), basename($videoFullPath))
            ->attach('user_id', (string) $enrollment->user_id)
            ->post("{$mlServiceUrl}/api/enrollment/process");

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'FastAPI gagal memproses video',
                    'status'  => $response->status(),
                    'detail'  => $response->json(),
                    'body'    => $response->body(),
                ], 500);
            }

            $result = $response->json();

            $enrollment->update([
                'status'        => 'setuju',
                'catatan_admin' => $request->input('catatan', null),
            ]);

            User::where('id', $enrollment->user_id)->update(['enrollment_enable' => false]);

            return response()->json([
                'message'          => 'Enrollment disetujui dan embedding berhasil dibuat',
                'embeddings_saved' => $result['embeddings_saved'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghubungi ML service',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }   
 
    public function reject(Request $request, Enrollments $enrollment)
    {
        if ($enrollment->status !== 'pending') {
            return response()->json(['message' => 'Enrollment sudah diproses'], 409);
        }

        $enrollment->update([
            'status'        => 'tolak',
            'catatan_admin' => $request->input('catatan', 'Ditolak oleh admin'),
        ]);

        Storage::disk('local')->delete($enrollment->vidio_path);

        return response()->json(['message' => 'Enrollment ditolak']);
    }
 
    public function toggleEnrollment(Request $request, User $user)
    {
        $enable = $request->boolean('enable');

        $user->update(['enrollment_enable' => $enable]);

        return response()->json([
            'message'            => $enable ? 'Enrollment dibuka' : 'Enrollment ditutup',
            'enrollment_enabled' => $enable,
        ]);
    }
}