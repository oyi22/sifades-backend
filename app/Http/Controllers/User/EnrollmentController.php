<?php 

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\EnrollmentService;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    protected EnrollmentService $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }
 
    public function checkStatus(Request $request)
    {
        return $this->enrollmentService->checkStatus($request);
    }
 
    public function store(Request $request)
    {
        return $this->enrollmentService->store($request);
    }
}