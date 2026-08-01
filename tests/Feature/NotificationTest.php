<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NewVersionPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetsUpDomainFixtures;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase, SetsUpDomainFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function uploadVersion($designer, $subcategory)
    {
        return $this->actingAs($designer)->post("/subcategories/{$subcategory->id}/versions", [
            'version_no' => 'V1',
            'publish_date' => now()->toDateString(),
            'description' => '测试变更说明',
            'zh_dwg' => [UploadedFile::fake()->create('a.dwg', 100)],
            'zh_doc' => UploadedFile::fake()->create('a.docx', 50),
        ]);
    }

    public function test_uploading_a_new_version_notifies_users_with_matching_project_and_team_access(): void
    {
        Notification::fake();

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $uploader = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);
        $construction = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        $this->uploadVersion($uploader, $subcategory)->assertRedirect();

        Notification::assertSentTo($construction, NewVersionPublished::class);
    }

    public function test_uploader_does_not_notify_themselves(): void
    {
        Notification::fake();

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $uploader = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);

        $this->uploadVersion($uploader, $subcategory)->assertRedirect();

        Notification::assertNotSentTo($uploader, NewVersionPublished::class);
    }

    public function test_users_without_matching_team_access_are_not_notified(): void
    {
        Notification::fake();

        $project = $this->makeProject();
        $specialty = $this->makeSpecialty('机电', '给排水');
        $subcategory = $this->makeSubcategory($project, $specialty);
        $uploader = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);
        // 这个施工方在同一个项目里，但没有机电团队权限
        $outsider = $this->makeUser(User::ROLE_CONSTRUCTION, [$project]);

        $this->uploadVersion($uploader, $subcategory)->assertRedirect();

        Notification::assertNotSentTo($outsider, NewVersionPublished::class);
    }

    public function test_a_user_can_mark_a_notification_as_read(): void
    {
        $project = $this->makeProject();
        $specialty = $this->makeSpecialty();
        $subcategory = $this->makeSubcategory($project, $specialty);
        $uploader = $this->makeUser(User::ROLE_DESIGNER, [$project], teams: [$specialty->team]);
        $recipient = $this->makeUser(User::ROLE_CONSTRUCTION, [$project], teams: [$specialty->team]);

        $this->uploadVersion($uploader, $subcategory);

        $notification = $recipient->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $this->actingAs($recipient)->post("/notifications/{$notification->id}/read")->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
