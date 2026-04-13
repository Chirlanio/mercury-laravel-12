<?php

namespace Database\Factories;

use App\Models\HdAttachment;
use App\Models\HdTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HdAttachment>
 */
class HdAttachmentFactory extends Factory
{
    protected $model = HdAttachment::class;

    public function definition(): array
    {
        $name = fake()->uuid().'.pdf';

        return [
            'ticket_id' => HdTicket::factory(),
            'interaction_id' => null,
            'original_filename' => 'document.pdf',
            'stored_filename' => $name,
            'file_path' => 'helpdesk-tickets/1/'.$name,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1024, 524288),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
