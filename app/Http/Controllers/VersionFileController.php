<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Version;
use App\Models\VersionFile;
use App\Services\CosFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 只管"变更说明文件"（每个版本每个语言最多一份，可选，可替换/移除）。
 * DWG 原图和 PDF 图纸走 VersionDrawingController，不在这里。
 */
class VersionFileController extends Controller
{
    public function __construct(protected CosFileService $files) {}

    public function store(Request $request, Version $version, string $language): RedirectResponse
    {
        abort_unless(in_array($language, ['zh', 'fr', 'en'], true), 404);
        $this->authorize('create', [VersionFile::class, $version]);

        $docRule = ['file', 'mimes:'.implode(',', config('uploads.doc_extensions')), 'max:'.config('uploads.doc_max_kb')];

        $data = $request->validate([
            'doc' => array_merge(['required'], $docRule),
        ]);

        $subcategory = $version->subcategory;
        $dir = "projects/{$subcategory->project_id}/subcategories/{$subcategory->id}/versions/{$version->id}/{$language}";

        $existing = $version->fileFor($language);
        $isReplace = $existing !== null;

        $result = $this->files->replace($existing?->doc_path, $request->file('doc'), $dir);

        VersionFile::updateOrCreate(
            ['version_id' => $version->id, 'language' => $language],
            [
                'doc_path' => $result['path'],
                'doc_size' => $result['size'],
                'uploaded_by' => Auth::id(),
            ]
        );

        $label = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'][$language];
        $action = $isReplace ? 'replace_file' : 'create';
        AuditLog::record(Auth::id(), $action, 'version_file', $version->id, ($isReplace ? '替换' : '上传')."「{$subcategory->name} · {$version->version_no}」的{$label}说明文件");

        return back()->with('toast', $isReplace ? "已替换{$label}说明文件" : "已上传{$label}说明文件");
    }

    public function destroy(Version $version, string $language): RedirectResponse
    {
        $versionFile = $version->fileFor($language);
        abort_if(! $versionFile || ! $versionFile->doc_path, 404);

        $this->authorize('delete', $versionFile);

        $this->files->delete($versionFile->doc_path);
        $versionFile->delete();

        $label = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'][$language];
        AuditLog::record(Auth::id(), 'delete', 'version_file', $version->id, "移除「{$version->subcategory->name} · {$version->version_no}」的{$label}说明文件");

        return back()->with('toast', "已移除{$label}说明文件");
    }

    public function download(Version $version, string $language): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $version);

        $versionFile = $version->fileFor($language);
        abort_if(! $versionFile || ! $versionFile->doc_path, 404);

        return redirect()->away($this->files->signedDownloadUrl($versionFile->doc_path, basename($versionFile->doc_path)));
    }
}
