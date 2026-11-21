<?php

namespace App\Http\Controllers;

use App\Services\EquipmentReports;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentReportExportController extends Controller
{
    public function __invoke(Request $request, EquipmentReports $reports, string $type): StreamedResponse
    {
        return $reports->download($request->user(), $request->query(), $type);
    }
}
