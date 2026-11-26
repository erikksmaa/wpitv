<?php

namespace App\Http\Controllers;

use App\Services\Equipment\EquipmentReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EquipmentReportExportController extends Controller
{
    public function __invoke(Request $request, string $section, EquipmentReports $reports)
    {
        Gate::authorize('equipment.report.view');
        Gate::authorize('equipment.report.export');
        try {
            $filters = $reports->filters($section, $request->query());
        } catch (ValidationException $error) {
            abort(422, $error->validator->errors()->first());
        }
        $inventory = $section === 'inventory';
        $columns = EquipmentReports::columns($section, $filters);
        $rows = $inventory ? $reports->inventory($filters) : $reports->loans($filters);

        return response()->streamDownload(static function () use ($columns, $rows) {
            $file = fopen('php://output', 'wb');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, array_values($columns), ',', '"', '');
            foreach ($rows->cursor() as $row) {
                $cells = [];
                foreach ($columns as $key => $label) {
                    $value = EquipmentReports::display($key, $row->$key);
                    $cells[] = preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $value) ? "'".$value : $value;
                }
                fputcsv($file, $cells, ',', '"', '');
            }
            fclose($file);
        }, 'equipment-'.$section.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
