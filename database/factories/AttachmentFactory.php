<?php

namespace Database\Factories;

use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use App\Models\StorageProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storage_profile_id' => StorageProfile::factory(),
            'object_key' => 'attachments/conversation_file/'.fake()->uuid().'.txt',
            'original_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'byte_size' => 12,
            'purpose' => AttachmentPurpose::ConversationFile,
            'status' => AttachmentStatus::Uploaded,
            'metadata' => [],
            'uploaded_at' => now(),
        ];
    }
}
