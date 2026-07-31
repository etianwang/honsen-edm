<?php

namespace Tests\Concerns;

use App\Models\Country;
use App\Models\Project;
use App\Models\Specialty;
use App\Models\Subcategory;
use App\Models\Team;
use App\Models\User;
use App\Models\Version;
use Illuminate\Support\Facades\Hash;

trait SetsUpDomainFixtures
{
    protected function makeProject(string $countryName = '科特迪瓦', string $projectName = 'Bethel办公楼'): Project
    {
        $country = Country::create(['name' => $countryName]);

        return Project::create(['country_id' => $country->id, 'name' => $projectName]);
    }

    protected function makeSpecialty(string $teamName = '机电', string $specialtyName = '给排水'): Specialty
    {
        $team = Team::create(['name' => $teamName, 'sort_order' => 1]);

        return $team->specialties()->create(['name' => $specialtyName, 'code' => 'PS', 'sort_order' => 1]);
    }

    protected function makeSubcategory(Project $project, Specialty $specialty, string $name = '排水'): Subcategory
    {
        return Subcategory::create([
            'project_id' => $project->id,
            'specialty_id' => $specialty->id,
            'name' => $name,
        ]);
    }

    protected function makeVersion(Subcategory $subcategory, string $versionNo = 'V1'): Version
    {
        return Version::create([
            'subcategory_id' => $subcategory->id,
            'version_no' => $versionNo,
            'description' => '测试版本',
            'publish_date' => now()->toDateString(),
        ]);
    }

    protected function makeUser(string $role, array $projects = [], string $password = 'password', array $teams = []): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'name' => "测试用户{$seq}",
            'login_id' => "T{$seq}",
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        if ($projects) {
            $user->projects()->attach(collect($projects)->pluck('id'));
        }

        if ($teams) {
            $user->teams()->attach(collect($teams)->pluck('id'));
        }

        return $user;
    }
}
