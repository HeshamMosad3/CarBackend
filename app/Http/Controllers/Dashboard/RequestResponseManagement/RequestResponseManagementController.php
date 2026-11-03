<?php

namespace App\Http\Controllers\Dashboard\RequestResponseManagement;

use App\Http\Controllers\Controller;
use App\Http\Services\Dashboard\ResponseManagement\ResponseManagementService;
use Illuminate\Http\Request;

class RequestResponseManagementController extends Controller
{
    public function __construct(protected ResponseManagementService $service) {}

    public function index(Request $request, $requestId)
    {
        if ($request->ajax()) {
            return $this->service->index($request, $requestId);
        }
        // The offers now live on the request page itself; keep old links working.
        return redirect()->to(route('dashboard.requests-management.show', $requestId) . '#offers');
    }
}
