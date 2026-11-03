<?php

namespace App\Http\Services\Dashboard\RequestsManagement;

use App\Http\Repositories\Dashboard\RequestsManagement\RequestsManagementRepository;
use App\Http\Services\BaseService;
use App\Models\RequestCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestsManagementService extends BaseService
{
    public function __construct(protected RequestsManagementRepository $repo) {}

    public function index(Request $request)
    {
        $searchValue = $request->input('search.value');

        $recordsCount =  $this->repo->getTotalRecordsCount(RequestCustomer::class);
        $recordsCountwithFilter = $this->repo->recordsCountRequestsCustomerWithFilter($searchValue, $request);
        $records = $this->repo->index($request, $searchValue);

        return $this->repo->formatResponseDataTables(
            draw: $request->input('draw'),
            recordsCount: $recordsCount,
            recordsCountwithFilter: $recordsCountwithFilter,
            records: $records
        );
    }

    public function statusCounts(): array
    {
        return $this->repo->statusCounts();
    }

    public function show($id)
    {
        $this->validate(['id' => $id], ['id' => 'required|integer|exists:request_customers,id'], [
            'requestId.required' => 'معرف الطلب مطلوب',
            'requestId.integer' => 'رقم الطلب يجب أن يكون رقم صحيح',
            'requestId.exists' => 'رقم الطلب غير موجود',
        ]);

        $result = $this->repo->getRequestById($id);

        $result['brandsNames'] = $this->repo->getRequestBrandNamesScope($id);
        $result['cities'] = $this->repo->getRequestCitiesNamesScope($result->cities);
        $result['requestImages'] = $this->repo->getRequestImages($id);
        $result['customFields'] = $this->repo->getRequestCustomFields($id);
        $result['responses'] = $this->repo->getRequestResponses($id);
        $result['shipments'] = $this->repo->getRequestShipments($id);

        return $result;
    }
}
