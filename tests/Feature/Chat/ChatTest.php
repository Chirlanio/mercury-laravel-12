<?php

namespace Tests\Feature\Chat;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TestHelpers;

class ChatTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestData();

        $this->otherUser = User::factory()->create([
            'role' => Role::USER->value,
            'access_level_id' => 4,
        ]);
    }

    // -----------------------------
    // Helpers
    // -----------------------------

    private function createDirectConversation(User $a, User $b): Conversation
    {
        $conversation = Conversation::create(['type' => 'direct', 'title' => null]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $a->id]);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $b->id]);

        return $conversation->load('participants');
    }

    private function createMessage(Conversation $conversation, User $sender, array $overrides = []): Message
    {
        return Message::create(array_merge([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Mensagem de teste',
            'message_type' => 'text',
        ], $overrides));
    }

    // -----------------------------
    // Index / Show
    // -----------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('chat.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_chat_index(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('chat.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Chat/Index'));
    }

    public function test_user_can_view_conversation_they_participate_in(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $this->createMessage($conversation, $this->regularUser, ['content' => 'Olá']);

        $response = $this->actingAs($this->regularUser)
            ->get(route('chat.show', $conversation->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->where('activeConversationId', $conversation->id)
            ->has('messages', 1));
    }

    public function test_user_cannot_view_conversation_they_do_not_participate_in(): void
    {
        $conversation = $this->createDirectConversation($this->adminUser, $this->otherUser);

        $response = $this->actingAs($this->regularUser)
            ->get(route('chat.show', $conversation->id));

        $response->assertStatus(403);
    }

    // -----------------------------
    // Direct conversation creation
    // -----------------------------

    public function test_user_can_create_a_direct_conversation(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->post(route('chat.create-direct'), ['user_id' => $this->otherUser->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('conversations', ['type' => 'direct']);
        $this->assertEquals(1, Conversation::count());

        $conversation = Conversation::first();
        $this->assertEquals(
            [$this->regularUser->id, $this->otherUser->id],
            $conversation->participants->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_creating_a_direct_conversation_twice_returns_the_same_one(): void
    {
        $this->actingAs($this->regularUser)
            ->post(route('chat.create-direct'), ['user_id' => $this->otherUser->id]);

        $this->actingAs($this->regularUser)
            ->post(route('chat.create-direct'), ['user_id' => $this->otherUser->id]);

        $this->assertEquals(1, Conversation::count());
    }

    // -----------------------------
    // Sending messages
    // -----------------------------

    public function test_user_can_send_a_text_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);

        $response = $this->actingAs($this->regularUser)
            ->postJson(route('chat.send-message', $conversation->id), [
                'content' => 'Olá mundo',
                'message_type' => 'text',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'Olá mundo');
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->regularUser->id,
            'content' => 'Olá mundo',
        ]);
    }

    public function test_sending_a_message_requires_content_or_file(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);

        $response = $this->actingAs($this->regularUser)
            ->postJson(route('chat.send-message', $conversation->id), []);

        $response->assertStatus(422);
    }

    // -----------------------------
    // Load / pagination
    // -----------------------------

    public function test_user_can_load_messages_for_their_conversation(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $this->createMessage($conversation, $this->regularUser, ['content' => 'primeira']);
        $this->createMessage($conversation, $this->otherUser, ['content' => 'segunda']);

        $response = $this->actingAs($this->regularUser)
            ->getJson(route('chat.load-messages', $conversation->id));

        $response->assertOk();
        $response->assertJsonCount(2, 'messages');
    }

    // -----------------------------
    // Mark as read + unread counts
    // -----------------------------

    public function test_user_can_mark_conversation_as_read(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $this->createMessage($conversation, $this->otherUser, ['content' => 'unread']);

        $response = $this->actingAs($this->regularUser)
            ->postJson(route('chat.mark-read', $conversation->id));

        $response->assertOk();
        $this->assertNotNull(
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $this->regularUser->id)
                ->value('last_read_at'),
        );
    }

    public function test_unread_counts_endpoint_returns_totals(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $this->createMessage($conversation, $this->otherUser, ['content' => 'para ler']);

        $response = $this->actingAs($this->regularUser)
            ->getJson(route('chat.unread-counts'));

        $response->assertOk();
        $response->assertJsonStructure(['conversations', 'broadcasts', 'total']);
    }

    // -----------------------------
    // Delete message
    // -----------------------------

    public function test_user_can_delete_their_own_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->regularUser, ['content' => 'para apagar']);

        $response = $this->actingAs($this->regularUser)
            ->deleteJson(route('chat.delete-message', $message->id));

        $response->assertOk();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_user_cannot_delete_someone_elses_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->otherUser, ['content' => 'mensagem do outro']);

        $response = $this->actingAs($this->regularUser)
            ->deleteJson(route('chat.delete-message', $message->id));

        $response->assertStatus(403);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_deleting_a_message_nullifies_references_from_replies(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $original = $this->createMessage($conversation, $this->regularUser, ['content' => 'original']);
        $reply = $this->createMessage($conversation, $this->otherUser, [
            'content' => 'resposta',
            'reply_to_message_id' => $original->id,
        ]);

        $this->actingAs($this->regularUser)
            ->deleteJson(route('chat.delete-message', $original->id));

        $this->assertDatabaseHas('messages', [
            'id' => $reply->id,
            'reply_to_message_id' => null,
        ]);
    }

    // -----------------------------
    // Edit message
    // -----------------------------

    public function test_user_can_edit_their_own_text_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->regularUser, ['content' => 'original']);

        $response = $this->actingAs($this->regularUser)
            ->patchJson(route('chat.edit-message', $message->id), [
                'content' => 'editada',
            ]);

        $response->assertOk();
        $response->assertJsonPath('message.content', 'editada');
        $response->assertJsonPath('message.is_edited', true);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'editada',
        ]);
        $this->assertNotNull($message->fresh()->edited_at);
    }

    public function test_user_cannot_edit_someone_elses_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->otherUser, ['content' => 'deles']);

        $response = $this->actingAs($this->regularUser)
            ->patchJson(route('chat.edit-message', $message->id), [
                'content' => 'hackeado',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'content' => 'deles',
        ]);
    }

    public function test_cannot_edit_a_file_message(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->regularUser, [
            'content' => null,
            'message_type' => 'file',
            'file_path' => 'chat-attachments/dummy.pdf',
            'file_name' => 'dummy.pdf',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->patchJson(route('chat.edit-message', $message->id), [
                'content' => 'tentando editar arquivo',
            ]);

        $response->assertStatus(422);
    }

    public function test_editing_requires_content(): void
    {
        $conversation = $this->createDirectConversation($this->regularUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->regularUser, ['content' => 'algo']);

        $response = $this->actingAs($this->regularUser)
            ->patchJson(route('chat.edit-message', $message->id), [
                'content' => '',
            ]);

        $response->assertStatus(422);
    }

    // -----------------------------
    // Attachment download
    // -----------------------------

    public function test_user_cannot_download_attachment_from_conversation_they_are_not_in(): void
    {
        $conversation = $this->createDirectConversation($this->adminUser, $this->otherUser);
        $message = $this->createMessage($conversation, $this->adminUser, [
            'content' => null,
            'message_type' => 'file',
            'file_path' => 'chat-attachments/secret.pdf',
            'file_name' => 'secret.pdf',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->get(route('chat.download-attachment', $message->id));

        $response->assertStatus(403);
    }
}
