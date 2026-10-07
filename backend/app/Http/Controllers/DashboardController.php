<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'title' => '保護されたページ',
            'message' => 'このデータは Authorization: Bearer の JWT が有効な場合だけ返します。',
            'user' => Auth::guard('api')->user(),
        ]);
    }
}
