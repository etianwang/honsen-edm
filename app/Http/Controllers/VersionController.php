<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Subcategory;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionDrawing;
use App\Models\VersionFile;
use App\Notifications\NewVersionPublished;
use App\Services\CosFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $version = DB::transaction(function () use ($data, $subcategory, $request) {
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
                    $this->storeDrawing($dwg, $dir, $lang, VersionDrawing::KIND_DWG, $version->id);
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
                        'original_name' => $docResult['original_name'],
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            return $version;
        });

        AuditLog::record(Auth::id(), 'create', 'version', $version->id, "上传变更「{$subcategory->name} · {$version->version_no}」");

        $this->notifyRelevantUsers($version, $subcategory);

        return redirect()
            ->route('subcategory.show', [$subcategory->project_id, $subcategory])
            ->with('toast', __('变更已发布'));
    }

    /**
     * "发布变更"上传失败后（尤其是网络中断/超时这类客户端根本不知道服务器有没有
     * 真正处理完的情况），前端在用户点"重试"之前先调这个接口确认一下：这个
     * version_no 是不是已经真的发布成功了。避免用户对着一个其实已经成功、只是
     * 响应没送达浏览器的请求盲目重试，凭空多出一条重复的版本记录。
     */
    public function checkExists(Request $request, Subcategory $subcategory): JsonResponse
    {
        $this->authorize('create', [Version::class, $subcategory]);

        $versionNo = (string) $request->query('version_no', '');

        $exists = $versionNo !== '' && Version::where('subcategory_id', $subcategory->id)
            ->where('version_no', $versionNo)
            ->exists();

        return response()->json(['exists' => $exists]);
    }

    private function storeDrawing(\Illuminate\Http\UploadedFile $file, string $dir, string $lang, string $kind, int $versionId): VersionDrawing
    {
        $result = $this->files->store($file, "{$dir}/{$lang}/{$kind}", inline: $kind === VersionDrawing::KIND_PDF);

        return VersionDrawing::create([
            'version_id' => $versionId,
            'language' => $lang,
            'kind' => $kind,
            'file_path' => $result['path'],
            'file_size' => $result['size'],
            'original_name' => $result['original_name'],
            // DWG→DXF 交互式预览功能已停用（预览改走 PDF），这里不再触发转换，dxf_status 恒为 null
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

    public function destroy(Request $request, Version $version): RedirectResponse
    {
        $this->authorize('delete', $version);

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), Auth::user()->password)) {
            return back()->withErrors(['password' => __('密码错误，请重试')]);
        }

        $subcategory = $version->subcategory;

        $this->purgeFiles($version);
        $version->delete();

        AuditLog::record(Auth::id(), 'delete', 'version', $version->id, "删除版本「{$subcategory->name} · {$version->version_no}」");

        return back()->with('toast', __('已删除该版本'));
    }

    /**
     * 删除版本时，把它名下所有 DWG/PDF/说明文件从 COS 上一并物理删除（不然只删数据库记录，
     * COS 空间不会释放）。版本记录本身走软删除保留审计痕迹，但文件不需要再留着占地方。
     */
    private function purgeFiles(Version $version): void
    {
        foreach ($version->drawings as $drawing) {
            $this->files->delete($drawing->file_path);
            $this->files->delete($drawing->dxf_path);
        }
        VersionDrawing::where('version_id', $version->id)->delete();

        foreach ($version->files as $file) {
            $this->files->delete($file->doc_path);
        }
        VersionFile::where('version_id', $version->id)->delete();
    }
}
