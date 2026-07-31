<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Models\Team;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- 国家 / 项目 ----------
        $ci = Country::create(['name' => '科特迪瓦']);
        $cm = Country::create(['name' => '喀麦隆']);

        $bethel = Project::create(['country_id' => $ci->id, 'name' => 'Bethel办公楼']);
        $kerene = Project::create(['country_id' => $ci->id, 'name' => 'Kerene别墅']);
        Project::create(['country_id' => $ci->id, 'name' => 'Loyan公寓']);
        Project::create(['country_id' => $cm->id, 'name' => 'Minrex数据中心']);

        // ---------- 团队 / 专业（全公司共享标准分类） ----------
        $civil = Team::create(['name' => '土建', 'sort_order' => 1]);
        $structure = $civil->specialties()->create(['name' => '结构', 'code' => 'STR', 'sort_order' => 1]);
        $architecture = $civil->specialties()->create(['name' => '建筑', 'code' => 'ARC', 'sort_order' => 2]);

        $mep = Team::create(['name' => '机电', 'sort_order' => 2]);
        $strong = $mep->specialties()->create(['name' => '强电', 'code' => 'SE', 'sort_order' => 1]);
        $mep->specialties()->create(['name' => '弱电', 'code' => 'WE', 'sort_order' => 2]);
        $mep->specialties()->create(['name' => '暖通', 'code' => 'HV', 'sort_order' => 3]);
        $water = $mep->specialties()->create(['name' => '给排水', 'code' => 'PS', 'sort_order' => 4]);

        $decor = Team::create(['name' => '精装', 'sort_order' => 3]);
        $decor->specialties()->create(['name' => '硬装', 'code' => 'HD', 'sort_order' => 1]);
        $decor->specialties()->create(['name' => '软装', 'code' => 'SF', 'sort_order' => 2]);
        $decor->specialties()->create(['name' => '门窗', 'code' => 'DW', 'sort_order' => 3]);

        // ---------- 账号 ----------
        $superAdmin = User::create([
            'name' => '超级管理员', 'login_id' => 'S00001',
            'password' => Hash::make('honsen2026'), 'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $admin = User::create([
            'name' => '系统管理员', 'login_id' => 'A00001',
            'password' => Hash::make('honsen2026'), 'role' => User::ROLE_ADMIN,
        ]);
        $designer = User::create([
            'name' => '陈工', 'login_id' => 'D00001',
            'password' => Hash::make('honsen2026'), 'role' => User::ROLE_DESIGNER,
        ]);
        $construction = User::create([
            'name' => '马工', 'login_id' => 'C00001',
            'password' => Hash::make('honsen2026'), 'role' => User::ROLE_CONSTRUCTION,
        ]);

        $designer->projects()->attach([$bethel->id, $kerene->id]);
        $construction->projects()->attach([$bethel->id]);
        // admin / super_admin 不受 user_project、user_team 限制，始终可见全部

        // 陈工只负责土建 + 机电，看不到精装；马工只负责机电，演示团队级隔离
        $designer->teams()->attach([$civil->id, $mep->id]);
        $construction->teams()->attach([$mep->id]);

        // ---------- Bethel 项目的细分类 + 演示版本 ----------
        $beam = Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $structure->id, 'name' => '梁柱配筋', 'code' => 'BM', 'created_by' => $designer->id]);
        Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $architecture->id, 'name' => '平面布置', 'code' => 'PL', 'created_by' => $designer->id]);
        Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $strong->id, 'name' => '照明', 'code' => 'LT', 'created_by' => $designer->id]);
        $supply = Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $water->id, 'name' => '给水', 'code' => 'SP', 'created_by' => $designer->id]);
        $drain = Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $water->id, 'name' => '排水', 'code' => 'DR', 'created_by' => $designer->id]);
        Subcategory::create(['project_id' => $bethel->id, 'specialty_id' => $water->id, 'name' => '消防水', 'code' => 'FR', 'created_by' => $designer->id]);

        // 排水 V2 的中文版本带一份真实可渲染的演示 DXF，方便在没装 ODA File Converter 之前先验证 dxf-viewer 前端集成是否正常
        $this->seedDemoVersion($drain, 'DR', 'V2', '2026-07-20', '根据现场核实情况修改排水做法，补充详图', $designer->id, ['zh', 'fr'], withDemoDxf: true);
        $this->seedDemoVersion($drain, 'DR', 'V1', '2026-06-15', '排水系统首版发布', $designer->id, ['zh']);
        $this->seedDemoVersion($supply, 'SP', 'V1', '2026-07-10', '给水系统首版发布，统一接口尺寸', $designer->id, ['zh', 'fr', 'en']);
        $this->seedDemoVersion($beam, 'BM', 'V1', '2026-07-05', '梁柱配筋首版发布', $designer->id, ['zh']);
    }

    private function seedDemoVersion(Subcategory $sub, string $code, string $versionNo, string $date, string $desc, int $uploaderId, array $languages, bool $withDemoDxf = false): void
    {
        $version = Version::create([
            'subcategory_id' => $sub->id,
            'version_no' => $versionNo,
            'description' => $desc,
            'publish_date' => $date,
            'uploaded_by' => $uploaderId,
        ]);

        $dir = "projects/{$sub->project_id}/subcategories/{$sub->id}/versions/{$version->id}";

        foreach ($languages as $lang) {
            $dwgContent = "DEMO DWG PLACEHOLDER\n{$sub->name} {$versionNo} ({$lang})\n";
            $docContent = "变更说明（演示占位文件）\n{$desc}\n";

            $dwgPath = "{$dir}/{$lang}/{$code}-{$versionNo}-".strtoupper($lang).'.dwg';
            $docPath = "{$dir}/{$lang}/{$code}-变更说明-{$versionNo}-".strtoupper($lang).'.docx';

            Storage::disk('local')->put($dwgPath, $dwgContent);
            Storage::disk('local')->put($docPath, $docContent);

            $dxfPath = null;
            if ($withDemoDxf && $lang === 'zh') {
                $dxfPath = "{$dir}/{$lang}/{$code}-{$versionNo}-".strtoupper($lang).'.dxf';
                Storage::disk('local')->put($dxfPath, $this->demoDxf());
            }

            VersionFile::create([
                'version_id' => $version->id,
                'language' => $lang,
                'dwg_path' => $dwgPath,
                'dwg_size' => strlen($dwgContent),
                'dxf_path' => $dxfPath,
                'doc_path' => $docPath,
                'doc_size' => strlen($docContent),
                'uploaded_by' => $uploaderId,
            ]);
        }
    }

    /**
     * 真实可被 dxf-viewer 渲染的最小 DXF 演示内容（矩形房间轮廓 + 一个圆），
     * 在没装 ODA File Converter 之前，用来验证前端看图组件本身是否接得对
     */
    private function demoDxf(): string
    {
        return <<<DXF
0
SECTION
2
ENTITIES
0
LWPOLYLINE
8
0
90
4
70
1
10
0.0
20
0.0
10
400.0
20
0.0
10
400.0
20
300.0
10
0.0
20
300.0
0
CIRCLE
8
0
10
200.0
20
150.0
40
40.0
0
LINE
8
0
10
0.0
20
150.0
11
400.0
21
150.0
0
ENDSEC
0
EOF
DXF;
    }
}
