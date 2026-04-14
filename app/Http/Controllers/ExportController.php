<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExportTask;
use App\Jobs\ProcessExportJob;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    /**
     * Ortak parametre paketleme foknsiyonu
     */
    private function buildParams(Request $request)
    {
        return [
            'bank_id'           => $request->bank_id,
            'search'            => $request->search,
            'currency'          => $request->currency,
            'account_id'        => $request->account_id,
            'separate_banks'    => $request->has('separate_banks'),
            'separate_accounts' => $request->has('separate_accounts')
        ];
    }

    public function exportExcel(Request $request)
    {
        $task = ExportTask::create([
            'user_id' => auth()->id(),
            'type'    => 'excel',
            'status'  => 'pending',
            'params'  => $this->buildParams($request),
        ]);

        ProcessExportJob::dispatch($task);

        return response()->json([
            'success' => true,
            'message' => 'Excel dışa aktarma işlemi arka planda başlatıldı.',
            'task_id' => $task->id
        ]);
    }

    public function exportPdf(Request $request)
    {
        $task = ExportTask::create([
            'user_id' => auth()->id(),
            'type'    => 'pdf',
            'status'  => 'pending',
            'params'  => $this->buildParams($request),
        ]);

        ProcessExportJob::dispatch($task);

        return response()->json([
            'success' => true,
            'message' => 'PDF dışa aktarma işlemi arka planda başlatıldı.',
            'task_id' => $task->id
        ]);
    }

    /**
     * Belirli bir kullanıcının devam eden bitmiş işlemlerini kontrol eder (AJAX Poll)
     */
    public function checkStatus(Request $request)
    {
        $tasks = ExportTask::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->take(5) // Sadece son 5 işlemi ön yüze gönder
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    /**
     * Biten dosyanın güvenli indirilmesi
     */
    public function downloadFile($id)
    {
        $task = ExportTask::findOrFail($id);
        
        // Güvenlik kontrolü (başkasının dosyasını indirmesin)
        if ($task->user_id !== auth()->id() || $task->status !== 'completed' || !$task->file_path) {
            abort(404, 'Dosya bulunamadı veya henüz hazır değil.');
        }

        if (!Storage::disk('public')->exists($task->file_path)) {
            abort(404, 'Fiziksel dosya sunucuda bulunamadı.');
        }

        return Storage::disk('public')->download($task->file_path);
    }
}