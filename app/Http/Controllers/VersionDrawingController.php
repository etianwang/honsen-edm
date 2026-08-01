<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Version;
use App\Models\VersionDrawing;
use App\Services\CosFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DWG 原图 / PDF 图纸：同一语言下可以有多份（比如每层一张），随时追加、单独删除，互不影响。
 */
class VersionDrawingController extends Controller
{
    private const LABELS = ['zh' => '中文', 'fr' => '法语', 'en' => '英语'];

    public function __construct(protected CosFileService $files) {}

    public function store(Request $request, Version $version, string $language, string $kind): RedirectResponse
    {
        abort_unless(in_array($language, ['zh', 'fr', 'en'], true), 404);
        abort_unless(in_array($kind, [VersionDrawing::KIND_DWG, VersionDrawing::KIND_PDF], true), 404);
        $this->authorize('create', [VersionDrawing::class, $version]);

        $rule = $kind === VersionDrawing::KIND_DWG
            ? ['file', 'mimes:'.implode(',', config('uploads.dwg_extensions')), 'max:'.config('uploads.dwg_max_kb')]
            : ['file', 'mimes:'.implode(',', config('uploads.pdf_extensions')), 'max:'.config('uploads.pdf_max_kb')];

        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => $rule,
        ]);

        $subcategory = $version->subcategory;
        $dir = "projects/{$subcategory->project_id}/subcategories/{$subcategory->id}/versions/{$version->id}/{$language}/{$kind}";

        foreach ($request->file('files') as $file) {
            $result = $this->files->store($file, $dir);

            VersionDrawing::create([
                'version_id' => $version->id,
                'language' => $language,
                'kind' => $kind,
                'file_path' => $result['path'],
                'file_size' => $result['size'],
                'original_name' => $result['original_name'],
                'uploaded_by' => Auth::id(),
            ]);
        }

        $label = self::LABELS[$language];
        $kindLabel = $kind === VersionDrawing::KIND_DWG ? 'DWG' : 'PDF';
        $count = count($request->file('files'));
        AuditLog::record(Auth::id(), 'create', 'version_drawing', $version->id, "为「{$subcategory->name} · {$version->version_no}」{$label}追加 {$count} 份 {$kindLabel} 文件");

        return back()->with('toast', "已追加 {$count} 份{$kindLabel}文件");
    }

    public function destroy(VersionDrawing $drawing): RedirectResponse
    {
        $this->authorize('delete', $drawing);

        if ($drawing->isDwg() && $drawing->language === 'zh') {
            $hasOtherDwg = VersionDrawing::where('version_id', $drawing->version_id)
                ->where('language', 'zh')
                ->where('kind', VersionDrawing::KIND_DWG)
                ->where('id', '!=', $drawing->id)
                ->exists();

            if (! $hasOtherDwg) {
                return back()->withErrors(['drawing' => '中文至少需要保留一份 DWG 图纸，不能全部删除']);
            }
        }

        $version = $drawing->version;
        $subcategory = $version->subcategory;
        $label = self::LABELS[$drawing->language];
        $kindLabel = $drawing->isDwg() ? 'DWG' : 'PDF';
        $filename = $drawing->original_name ?: basename($drawing->file_path);

        $this->files->delete($drawing->file_path);
        $this->files->delete($drawing->dxf_path);
        $drawing->delete();

        AuditLog::record(Auth::id(), 'delete', 'version_drawing', $version->id, "删除「{$subcategory->name} · {$version->version_no}」{$label}的{$kindLabel}文件「{$filename}」");

        return back()->with('toast', "已删除 {$filename}");
    }

    public function download(VersionDrawing $drawing): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $drawing->version);

        return redirect()->away($this->files->signedDownloadUrl(
            $drawing->file_path,
            $drawing->original_name ?: basename($drawing->file_path)
        ));
    }

    /**
     * PDF 图纸的在线预览：浏览器自带 PDF 阅读器（iframe 内嵌），走签名 URL 但不强制下载（inline）
     */
    public function preview(VersionDrawing $drawing): RedirectResponse
    {
        $this->authorize('view', $drawing->version);
        abort_unless($drawing->kind === VersionDrawing::KIND_PDF, 404);

        return redirect()->away($this->files->signedViewUrl($drawing->file_path));
    }

    /**
     * 按语言打包下载：该语言下所有 DWG + PDF + 说明文件一起打成一个 zip
     */
    public function downloadZip(Version $version, string $language): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $this->authorize('view', $version);
        abort_unless(in_array($language, ['zh', 'fr', 'en'], true), 404);

        $drawings = $version->drawingsFor($language);
        $docFile = $version->fileFor($language);

        if ($drawings->isEmpty() && ! $docFile?->doc_path) {
            abort(404);
        }

        $tmpDir = storage_path('app/tmp');
        @mkdir($tmpDir, 0755, true);
        $tmpZip = $tmpDir.'/'.Str::uuid().'.zip';

        $zip = new \ZipArchive;
        $zip->open($tmpZip, \ZipArchive::CREATE);

        $usedNames = [];
        $addToZip = function (string $path, ?string $originalName) use ($zip, &$usedNames) {
            $name = $originalName ?: basename($path);
            $i = 1;
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $stem = pathinfo($name, PATHINFO_FILENAME);
            while (in_array($name, $usedNames, true)) {
                $name = $extension ? "{$stem}({$i}).{$extension}" : "{$stem}({$i})";
                $i++;
            }
            $usedNames[] = $name;
            $zip->addFromString($name, $this->files->getContents($path));
        };

        foreach ($drawings as $drawing) {
            $addToZip($drawing->file_path, $drawing->original_name);
        }
        if ($docFile?->doc_path) {
            $addToZip($docFile->doc_path, basename($docFile->doc_path));
        }

        $zip->close();

        $subcategory = $version->subcategory;
        $label = self::LABELS[$language];
        $downloadName = "{$subcategory->name}-{$version->version_no}-{$label}.zip";

        return response()->download($tmpZip, $downloadName)->deleteFileAfterSend();
    }
}
