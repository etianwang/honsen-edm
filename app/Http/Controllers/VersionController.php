<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVersionDrawingDxf;
use App\Models\AuditLog;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionDrawing;
use App\Models\VersionFile;
use App\Notifications\NewVersionPublished;
use App\Services\CosFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VersionController extends Controller
{
    public function __construct(protected CosFileService $files) {}

    public function store(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $this->authorize('create', [Version::class, $subcategory]);

        $dwgRule = ['file', 'mimes:'.implode(',', config('uploads.dwg_extensions')), 'max:'.config('uploads.dwg_max_kb')];
        $pdfRule = ['file', 'mimes:'.implode(',', config('uploads.pdf_extensions')), 'max:'.config('uploads.pdf_max_kb')];
        $docRule = ['file', 'mimes:'.implode(',', config('uploads.doc_extensions')), 'max:'.config('uploads.doc_max_kb')];

        $rules = [
            'version_no' => ['required', 'string', 'max:20'],
            'publish_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            // 唯一的强制项：中文至少要有 1 份 DWG；其余语言、PDF、说明文件全部可选，可以随时后补
            'zh_dwg' => ['required', 'array', 'min:1'],
        ];

        foreach (['zh', 'fr', 'en'] as $lang) {
            $rules["{$lang}_dwg"] = $rules["{$lang}_dwg"] ?? ['nullable', 'array'];
            $rules["{$lang}_dwg.*"] = $dwgRule;
            $rules["{$lang}_pdf"] = ['nullable', 'array'];
            $rules["{$lang}_pdf.*"] = $pdfRule;
            $rules["{$lang}_doc"] = array_merge(['nullable'], $docRule);
        }

        $data = $request->validate($rules);

        $pendingDxfConversions = [];

        $version = DB::transaction(function () use ($data, $subcategory, $request, &$pendingDxfConversions) {
            $version = Version::create([
                'subcategory_id' => $subcategory->id,
                'version_no' => $data['version_no'],
                'description' => $data['description'],
                'publish_date' => $data['publish_date'],
                'uploaded_by' => Auth::id(),
            ]);

            $dir = "projects/{$subcategory->project_id}/subcategories/{$subcategory->id}/versions/{$version->id}";

            foreach (['zh', 'fr', 'en'] as $lang) {
                foreach ($request->file("{$lang}_dwg", []) as $dwg) {
                    $drawing = $this->storeDrawing($dwg, $dir, $lang, VersionDrawing::KIND_DWG, $version->id);
                    $pendingDxfConversions[] = $drawing->id;
                }

                foreach ($request->file("{$lang}_pdf", []) as $pdf) {
                    $this->storeDrawing($pdf, $dir, $lang, VersionDrawing::KIND_PDF, $version->id);
                }

                if ($doc = $request->file("{$lang}_doc")) {
                    $docResult = $this->files->store($doc, "{$dir}/{$lang}");
                    VersionFile::create([
                        'version_id' => $version->id,
                        'language' => $lang,
                        'doc_path' => $docResult['path'],
                        'doc_size' => $docResult['size'],
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            return $version;
        });

        // DWG→DXF 转换放到后台异步跑（单个文件最多可能耗时 60 秒），变更记录本身立即可见，
        // 交互式预览转换完之前前端显示"转换中"，不阻塞发布流程
        foreach ($pendingDxfConversions as $versionDrawingId) {
            ConvertVersionDrawingDxf::dispatch($versionDrawingId);
        }

        AuditLog::record(Auth::id(), 'create', 'version', $version->id, "上传变更「{$subcategory->name} · {$version->version_no}」");

        $this->notifyRelevantUsers($version, $subcategory);

        return redirect()
            ->route('subcategory.show', [$subcategory->project_id, $subcategory])
            ->with('toast', '变更已发布');
    }

    private function storeDrawing(\Illuminate\Http\UploadedFile $file, string $dir, string $lang, string $kind, int $versionId): VersionDrawing
    {
        $result = $this->files->store($file, "{$dir}/{$lang}/{$kind}");

        return VersionDrawing::create([
            'version_id' => $versionId,
            'language' => $lang,
            'kind' => $kind,
            'file_path' => $result['path'],
            'file_size' => $result['size'],
            'original_name' => $result['original_name'],
            'dxf_status' => $kind === VersionDrawing::KIND_DWG ? VersionDrawing::DXF_PENDING : null,
            'uploaded_by' => Auth::id(),
        ]);
    }

    /**
     * 通知同时拥有该项目和该团队权限的设计师 / 施工方账号（不含上传者本人），管理层已经能看到全部内容，不需要额外通知
     */
    private function notifyRelevantUsers(Version $version, Subcategory $subcategory): void
    {
        $teamId = $subcategory->specialty->team_id;

        $recipients = User::query()
            ->whereIn('role', [User::ROLE_DESIGNER, User::ROLE_CONSTRUCTION])
            ->where('id', '!=', Auth::id())
            ->whereHas('projects', fn ($q) => $q->whereKey($subcategory->project_id))
            ->whereHas('teams', fn ($q) => $q->whereKey($teamId))
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewVersionPublished($version));
        }
    }

    public function destroy(Version $version): RedirectResponse
    {
        $this->authorize('delete', $version);

        $subcategory = $version->subcategory;
        $version->delete();

        AuditLog::record(Auth::id(), 'delete', 'version', $version->id, "删除版本「{$subcategory->name} · {$version->version_no}」");

        return back()->with('toast', '已删除该版本');
    }
}
