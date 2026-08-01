<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVersionFileDxf;
use App\Models\AuditLog;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\Version;
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
        $docRule = ['file', 'mimes:'.implode(',', config('uploads.doc_extensions')), 'max:'.config('uploads.doc_max_kb')];

        $data = $request->validate([
            'version_no' => ['required', 'string', 'max:20'],
            'publish_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'zh_dwg' => array_merge(['required'], $dwgRule),
            'zh_doc' => array_merge(['required'], $docRule),
            'fr_dwg' => array_merge(['nullable'], $dwgRule),
            'fr_doc' => array_merge(['nullable'], $docRule),
            'en_dwg' => array_merge(['nullable'], $dwgRule),
            'en_doc' => array_merge(['nullable'], $docRule),
        ]);

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
                $dwg = $request->file("{$lang}_dwg");
                $doc = $request->file("{$lang}_doc");

                if (! $dwg && ! $doc) {
                    continue;
                }

                $dwgResult = $dwg ? $this->files->store($dwg, "{$dir}/{$lang}") : null;
                $docResult = $doc ? $this->files->store($doc, "{$dir}/{$lang}") : null;

                $versionFile = VersionFile::create([
                    'version_id' => $version->id,
                    'language' => $lang,
                    'dwg_path' => $dwgResult['path'] ?? null,
                    'dwg_size' => $dwgResult['size'] ?? null,
                    'dxf_status' => $dwg ? VersionFile::DXF_PENDING : null,
                    'doc_path' => $docResult['path'] ?? null,
                    'doc_size' => $docResult['size'] ?? null,
                    'uploaded_by' => Auth::id(),
                ]);

                if ($dwg) {
                    $pendingDxfConversions[] = $versionFile->id;
                }
            }

            return $version;
        });

        // DWG→DXF 转换放到后台异步跑（单个文件最多可能耗时 60 秒），变更记录本身立即可见，
        // 交互式预览转换完之前前端显示"转换中"，不阻塞发布流程
        foreach ($pendingDxfConversions as $versionFileId) {
            ConvertVersionFileDxf::dispatch($versionFileId);
        }

        AuditLog::record(Auth::id(), 'create', 'version', $version->id, "上传变更「{$subcategory->name} · {$version->version_no}」");

        $this->notifyRelevantUsers($version, $subcategory);

        return redirect()
            ->route('subcategory.show', [$subcategory->project_id, $subcategory])
            ->with('toast', '变更已发布');
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
