<?php

namespace App\Http\Repositories\Dashboard\ComplaintManagemnt;

use App\Enums\ComplaintStatusEnum;
use App\Models\Complaint;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;

class ComplaintManagemntRepository
{
    use HandlesDatatablesTrait;

    public function index(Request $request, $searchValue)
    {
        $query = $this->filteredQuery($request, $searchValue)
            ->select(
                'complaints.id',
                'complaints.user_id',
                'complaints.user_type',
                'complaints.request_id',
                'users.name',
                'users.phone',
                'complaints.subject',
                'complaints.title',
                'complaints.description',
                'complaints.status',
                'complaints.created_at as date_complaint',
            );

        return $this->paginateRecordsForDatatables($request, $query);
    }

    public function countWithFilter(Request $request, $searchValue): int
    {
        return $this->filteredQuery($request, $searchValue)->count('complaints.id');
    }

    public function statusCounts(): array
    {
        return Complaint::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }

    private function filteredQuery(Request $request, $searchValue)
    {
        $status = ComplaintStatusEnum::tryFrom((string) $request->input('status'));

        return Complaint::query()
            ->leftJoin('users', 'complaints.user_id', '=', 'users.id')
            ->when($status, fn ($q) => $q->where('complaints.status', $status->value))
            ->when($searchValue, function ($q, $value) {
                $q->whereAny([
                    'complaints.id',
                    'complaints.request_id',
                    'users.name',
                    'users.phone',
                    'complaints.title',
                    'complaints.description',
                ], 'like', '%' . $value . '%');
            });
    }
}
