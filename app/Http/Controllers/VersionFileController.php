<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertVersionFileDxf;
use App\Models\AuditLog;
use App\Models\Version;
use App\Models\VersionFile;
use App\Services\CosFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VersionFileController extends Controller
{
    public function __construct(protected CosFileService $files) {}

    public function store(Request $request, Version $version, string $language): RedirectResponse
    {
        abort_unless(in_array($language, ['zh', 'fr', 'en'], true), 404);
        $this->authorize('create', [VersionFile::class, $version]);

        $dwgRule = ['file', 'mimes:'.implode(',', config('uploads.dwg_extensions')), 'max:'.config('uploads.dwg_max_kb')];
        $docRule = ['file', 'mimes:'.implode(',', config('uploads.doc_extensions')), 'max:'.config('uploads.doc_max_kb')];

        $data = $request->validate([
            'dwg' => array_merge(['nullable'], $dwgRule),
            'doc' => array_merge(['nullable'], $docRule),
        ]);

        $subcategory = $version->subcategory;
        $dir = "projects/{$subcategory->project_id}/subcategories/{$subcategory->id}/versions/{$version->id}/{$language}";

        $existing = $version->fileFor($language);
        $isReplace = $existing !== null;

        $dwgPath = $existing?->dwg_path;
        $dwgSize = $existing?->dwg_size;
        $dxfPath = $existing?->dxf_path;
        $dxfStatus = $existing?->dxf_status;
        $docPath = $existing?->doc_path;
        $docSize = $existing?->doc_size;

        if ($dwg = $request->file('dwg')) {
            $result = $this->files->replace($existing?->dwg_path, $dwg, $dir);
            $dwgPath = $result['path'];
            $dwgSize = $result['size'];

            $this->files->delete($existing?->dxf_path);
            $dxfPath = null;
            $dxfStatus = VersionFile::DXF_PENDING;
        }

        if ($doc = $request->file('doc')) {
            $result = $this->files->replace($existing?->doc_path, $doc, $dir);
            $docPath = $result['path'];
            $docSize = $result['size'];
        }

        $versionFile = VersionFile::updateOrCreate(
            ['version_id' => $version->id, 'language' => $language],
            [
                'dwg_path' => $dwgPath,
                'dwg_size' => $dwgSize,
                'dxf_path' => $dxfPath,
                'dxf_status' => $dxfStatus,
                'doc_path' => $docPath,
                'doc_size' => $docSize,
                'uploaded_by' => Auth::id(),
            ]
        );

        if ($dwg) {
            ConvertVersionFileDxf::dispatch($versionFile->id);
        }

        $label = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'][$language];
        $action = $isReplace ? 'replace_file' : 'create';
        AuditLog::record(Auth::id(), $action, 'version_file', $version->id, ($isReplace ? "替换" : "补充")."「{$subcategory->name} · {$version->version_no}」的{$label}版本");

        return back()->with('toast', $isReplace ? "已替换{$label}版本文件" : "已补充{$label}版本");
    }

    public function destroy(Version $version, string $language): RedirectResponse
    {
        $versionFile = $version->fileFor($language);
        abort_if(! $versionFile, 404);

        $this->authorize('delete', $versionFile);

        $this->files->delete($versionFile->dwg_path);
        $this->files->delete($versionFile->dxf_path);
        $this->files->delete($versionFile->doc_path);
        $versionFile->delete();

        $label = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'][$language];
        AuditLog::record(Auth::id(), 'delete', 'version_file', $version->id, "移除「{$version->subcategory->name} · {$version->version_no}」的{$label}版本");

        return back()->with('toast', "已移除{$label}版本");
    }

    public function download(Version $version, string $language, string $kind): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $version);

        $versionFile = $version->fileFor($language);
        abort_if(! $versionFile, 404);

        abort_unless(in_array($kind, ['dwg', 'doc'], true), 404);

        $path = $kind === 'dwg' ? $versionFile->dwg_path : $versionFile->doc_path;
        abort_if(! $path, 404);

        $filename = basename($path);

        return redirect()->away($this->files->signedDownloadUrl($path, $filename));
    }

    /**
     * 给浏览器端 dxf-viewer 用的原始 DXF 文本，走我们自己的鉴权路由中转，
     * 不直接暴露 COS 签名 URL（DXF 内容对 dxf-viewer 来说需要能被 fetch() 直接读取文本）
     */
    public function dxf(Version $version, string $language)
    {
        $this->authorize('view', $version);

        $versionFile = $version->fileFor($language);
        abort_if(! $versionFile || ! $versionFile->dxf_path, 404);

        return Response::make($this->files->getContents($versionFile->dxf_path), 200, [
            'Content-Type' => 'application/dxf',
        ]);
    }
}
