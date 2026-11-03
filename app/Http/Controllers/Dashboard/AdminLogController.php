<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;


class AdminLogController extends Controller
{
    public function index()
    {
        $path = storage_path('logs/laravel.log');

        $exists = File::exists($path);
        $logs = $exists ? collect(file($path))->take(-1000)->implode('') : '';
        $size = $exists ? File::size($path) : 0;

        return view('dashboard.admin-logs.index', compact('logs', 'size', 'exists'));
    }

    public function clearLogs()
    {
        File::put(storage_path('logs/laravel.log'), '');
        return back()->with('success', 'تم حذف السجلات');
    }

    //download logs file
    public function downloadLogs()
    {
        $path = storage_path('logs/laravel.log');

        if (!File::exists($path)) {
            return back()->with('error', 'لا يوجد سجلات');
        }

        return response()->download($path);
    }
}
