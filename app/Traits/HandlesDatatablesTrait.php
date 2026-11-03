<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesDatatablesTrait
{
    /**
     * For Fetch records or data with paginate For Datatables
     */
    public function paginateRecordsForDatatables(Request $request, $query)
    {
        $this->applyOrderBy($request, $query);

        return $this->applyPaginate($request, $query);
    }

    private function applyOrderBy(Request $request, $query)
    {
        $orderColumnIndex = $request->input('order.0.column', 0);
        $getColumn = $request->input("columns.{$orderColumnIndex}");
        // A column's "name" wins over its "data" key, so joined tables can sort by a qualified
        // column (e.g. name: 'complaints.id') while still reading the plain key from each row.
        $orderColumnName = !empty($getColumn['name']) ? $getColumn['name'] : ($getColumn['data'] ?? null);
        $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (!empty($getColumn['orderable']) && $getColumn['orderable'] !== 'false' && is_string($orderColumnName)
            && preg_match('/^[A-Za-z0-9_.]+$/', $orderColumnName)) {
            $query->orderBy($orderColumnName, $orderDirection);
        }
    }

    private function applyPaginate(Request $request, $query)
    {
        // $rowperpage = $request->input("length"); // Rows display per page
        return $query->skip($request->input('start'))
            ->take($request->input('length'))
            ->get();
    }

    /**
     * For get total records Count For Datatables
     *
     * Ex: getTotalRecordsCount(User::class)
     */
    public function getTotalRecordsCount(string $modelClass)
    {
        return $modelClass::select('count(*) as allcount')->count();
    }

    public function formatResponseDataTables($draw, $recordsCount, $recordsCountwithFilter, $records)
    {
        return response()->json([
            "draw" => intval($draw),
            "iTotalRecords" => $recordsCount,
            "iTotalDisplayRecords" => $recordsCountwithFilter,
            "aaData" =>  $records
        ]);
    }
}
