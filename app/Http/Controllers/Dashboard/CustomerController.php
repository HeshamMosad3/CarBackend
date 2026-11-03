<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\StatusUserEnum;
use App\Enums\user\UserRoleEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\HandlesDatatablesTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use HandlesDatatablesTrait;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value');

            $recordsCount = User::select('count(*) as allcount')->role(UserRoleEnum::User->value)->count();
            $recordsCountwithFilter = User::select('count(*) as allcount')
                ->searchValueFilter($searchValue)
                ->role(UserRoleEnum::User->value)
                ->count();

            $query = User::role(UserRoleEnum::User->value)
                ->searchValueFilter($searchValue)
                ->select(
                    'users.id',
                    'users.name',
                    'users.phone',
                    'users.logo',
                    'users.status',
                    'users.created_at',
                );
            $records = $this->paginateRecordsForDatatables($request, $query);

            return $this->formatResponseDataTables($request->input('draw'), $recordsCount, $recordsCountwithFilter, $records);
        }

        return view('dashboard.customers.index');
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:users,id',
            'status' => ['required', Rule::enum(StatusUserEnum::class)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::find($request->id);
        $user->status = $request->status;

        return $user->save()
            ? buildApiResponseHelper(true, $request->status == 'Active' ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم')
            : buildApiResponseHelper(false, 'لم يتم التعديل بنجاح');
    }

    public function delete(Request $request, $id)
    {
        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        DB::beginTransaction();
        try {
            $user = User::find($id);
            $user->delete();
            DB::commit();
            return buildApiResponseHelper(true, 'تم حذف المستخدم بنجاح');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return buildApiResponseHelper(false, $e->getMessage());
        }
    }
}
