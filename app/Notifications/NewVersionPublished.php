<?php

namespace App\Notifications;

use App\Models\Version;
use Illuminate\Notifications\Notification;

class NewVersionPublished extends Notification
{
    public function __construct(protected Version $version) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $subcategory = $this->version->subcategory;
        $specialty = $subcategory->specialty;

        return [
            'project_id' => $subcategory->project_id,
            'subcategory_id' => $subcategory->id,
            'subcategory_name' => $subcategory->name,
            'team_name' => $specialty->team->name,
            'specialty_name' => $specialty->name,
            'version_id' => $this->version->id,
            'version_no' => $this->version->version_no,
            'description' => $this->version->description,
            'uploader_name' => $this->version->uploader?->name,
        ];
    }
}
