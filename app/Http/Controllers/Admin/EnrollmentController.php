<?php 

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollments;
use App\Models\User;
use App\Services\EnrollmentService; 
use Illuminate\Http\Request; 

class EnrollmentController extends Controller
{
    protected EnrollmentService $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }
 
    public function index()
    {
        return $this->enrollmentService->index();
    }
 
    public function approve(Request $request, Enrollments $enrollment)
    {
        return $this->enrollmentService->approve($request, $enrollment);
    }
 
    public function reject(Request $request, Enrollments $enrollment)
    {
        return $this->enrollmentService->reject($request, $enrollment);
    }
 
    public function toggleEnrollment(Request $request, User $user)
    {
        return $this->enrollmentService->toggleEnrollment($request, $user);
    } 

    public function streamVideo(Enrollments $enrollment)
    {
        $path = storage_path('app/private/' . $enrollment->vidio_path);
        if (!file_exists($path)) {
            $path = storage_path('app/' . $enrollment->vidio_path);
        }
        if (!file_exists($path)) {
            return response()->json(['message' => 'Video tidak ditemukan'], 404);
        }

        $size  = filesize($path);
        $start = 0;
        $end   = $size - 1;

        $headers = [
            'Content-Type'  => 'video/webm',
            'Accept-Ranges' => 'bytes',
            'Content-Length'=> $size,
        ];

        if (request()->hasHeader('Range')) {
            $range = request()->header('Range');
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
            $start = (int) $matches[1];
            $end   = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : $size - 1;

            $headers['Content-Range']  = "bytes {$start}-{$end}/{$size}";
            $headers['Content-Length'] = $end - $start + 1;

            $stream = fopen($path, 'rb');
            fseek($stream, $start);
            $chunk = fread($stream, $end - $start + 1);
            fclose($stream);

            return response($chunk, 206, $headers);
        }

        return response()->stream(function () use ($path) {
            $stream = fopen($path, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 8192);
                ob_flush();
                flush();
            }
            fclose($stream);
        }, 200, $headers);
    }

    public function downloadVideo(Enrollments $enrollment)
    {
        $path = storage_path('app/private/' . $enrollment->vidio_path);
        if (!file_exists($path)) {
            $path = storage_path('app/' . $enrollment->vidio_path);
        }
        if (!file_exists($path)) {
            return response()->json(['message' => 'Video tidak ditemukan'], 404);
        }

        $filename = "enrollment-{$enrollment->user_id}-{$enrollment->id}.webm";

        return response()->download($path, $filename, [
            'Content-Type' => 'video/webm',
        ]);
    }
}