<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Models\{Announcement, User};
use App\Services\CfoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminAnnouncementDraftsTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = ['title' => 'Retreat reminders', 'preview' => 'Bring your ID.', 'body' => 'Full details for the retreat.'];

    private function account(string $role = 'Student', string $status = 'Approved'): User
    {
        return User::factory()->create(['role' => $role, 'status' => $status]);
    }

    private function versions(): array
    {
        return DB::table('cfo_resource_versions')->whereIn('resource', ['announcements', 'notifications', 'activity_logs'])->pluck('version', 'resource')->all();
    }

    public function test_drafts_are_private_editable_and_published_once(): void
    {
        $student = $this->account();
        $admin = $this->account('Admin');
        $before = $this->versions();

        $this->actingAs($student)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertForbidden();
        $id = $this->actingAs($admin)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $this->putJson("/api/admin/announcements/$id", [...self::FIELDS, 'title' => 'Updated title'])->assertOk();
        $this->assertNull(Announcement::findOrFail($id)->published_at);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertSame(2, DB::table('activity_logs')->where('action', 'announcement.draft_saved')->count());
        $this->assertGreaterThan($before['announcements'], $this->versions()['announcements']);
        $this->assertSame($before['notifications'], $this->versions()['notifications']);
        $this->getJson('/api/admin/state?resources=announcements')->assertJsonPath('announcements.0.id', $id)->assertJsonPath('announcements.0.published_at', null);
        $this->actingAs($student)->getJson('/api/student?resources=announcements')->assertJsonCount(0, 'announcements');

        $this->actingAs($admin)->postJson("/api/admin/announcements/$id/publish", [...self::FIELDS, 'title' => 'Final title'])->assertOk();
        $this->postJson("/api/admin/announcements/$id/publish", self::FIELDS)->assertUnprocessable();
        $this->putJson("/api/admin/announcements/$id", self::FIELDS)->assertUnprocessable();
        $this->assertSame('Final title', Announcement::findOrFail($id)->title);
        $this->assertSame(1, DB::table('cfo_notifications')->where('related_type', 'announcement')->where('related_id', $id)->count());
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'announcement.published')->count());
        $this->actingAs($student)->getJson('/api/student?resources=announcements')->assertJsonPath('announcements.0.title', 'Final title');
    }

    public function test_deleting_removes_the_announcement_and_only_its_student_notifications(): void
    {
        $student = $this->account();
        $admin = $this->account('Admin');
        $draft = $this->actingAs($admin)->postJson('/api/admin/announcements/drafts', self::FIELDS)->json('announcement.id');
        $published = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->json('announcement.id');
        $this->postJson("/api/admin/announcements/$published/publish", self::FIELDS)->assertOk();
        CfoService::notifyStudent($student->id, 'announcement', 'Other announcement', 'announcement', $published + 100);
        CfoService::notifyStudent($student->id, 'certificate_ready', 'Same numeric ID, other record', 'registration', $published);
        $this->assertDatabaseCount('cfo_notifications', 3);

        $this->actingAs($student)->deleteJson("/api/admin/announcements/$published")->assertForbidden();
        $beforeDraftDelete = $this->versions();
        $this->actingAs($admin)->deleteJson("/api/admin/announcements/$draft")->assertOk();
        $this->assertGreaterThan($beforeDraftDelete['announcements'], $this->versions()['announcements']);
        $this->assertSame($beforeDraftDelete['notifications'], $this->versions()['notifications']);
        $beforePublishedDelete = $this->versions();
        $this->deleteJson("/api/admin/announcements/$published")->assertOk();

        $this->assertDatabaseCount('cfo_announcements', 0);
        $this->assertDatabaseCount('cfo_notifications', 2);
        $this->assertDatabaseHas('cfo_notifications', ['related_type' => 'announcement', 'related_id' => $published + 100]);
        $this->assertDatabaseHas('cfo_notifications', ['related_type' => 'registration', 'related_id' => $published]);
        $this->assertGreaterThan($beforePublishedDelete['notifications'], $this->versions()['notifications']);
        $this->assertSame(2, DB::table('activity_logs')->where('action', 'announcement.deleted')->count());
        $this->deleteJson("/api/admin/announcements/$published")->assertNotFound();
        $this->actingAs($student)->getJson('/api/student?resources=announcements')->assertJsonCount(0, 'announcements');
    }

    public function test_draft_content_is_validated_for_create_edit_and_publish(): void
    {
        $this->actingAs($this->account('Admin'));
        $invalid = ['title' => '', 'preview' => '', 'body' => ''];
        $this->postJson('/api/admin/announcements/drafts', $invalid)->assertUnprocessable()->assertJsonValidationErrors(['title', 'preview', 'body']);
        $id = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $before = $this->versions();
        foreach (["/api/admin/announcements/$id", "/api/admin/announcements/$id/publish"] as $url) {
            $method = str_ends_with($url, '/publish') ? 'postJson' : 'putJson';
            $this->$method($url, $invalid)->assertUnprocessable()->assertJsonValidationErrors(['title', 'preview', 'body']);
            $this->$method($url, ['title' => str_repeat('T', 191), 'preview' => str_repeat('P', 281), 'body' => str_repeat('B', 10001)])
                ->assertUnprocessable()->assertJsonValidationErrors(['title', 'preview', 'body']);
        }
        $this->assertSame($before, $this->versions());
        $this->assertNull(Announcement::findOrFail($id)->published_at);
        $this->assertSame(self::FIELDS['title'], Announcement::findOrFail($id)->title);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->putJson("/api/admin/announcements/$id", ['title' => str_repeat('T', 190), 'preview' => str_repeat('P', 280), 'body' => str_repeat('B', 10000)])->assertOk();
    }

    public function test_admins_share_drafts_without_replacing_the_original_author(): void
    {
        $author = $this->account('Admin');
        $editor = $this->account('Admin');
        $id = $this->actingAs($author)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $this->actingAs($editor)->getJson('/api/admin/state?resources=announcements')->assertJsonPath('announcements.0.id', $id);
        $this->putJson("/api/admin/announcements/$id", [...self::FIELDS, 'title' => 'Shared draft'])
            ->assertOk()->assertJsonPath('announcement.author.id', $author->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'announcement.draft_saved', 'record_id' => $id, 'actor_id' => $editor->id]);
        $this->postJson("/api/admin/announcements/$id/publish", [...self::FIELDS, 'title' => 'Shared draft'])
            ->assertOk()->assertJsonPath('announcement.created_by', $author->id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'announcement.published', 'record_id' => $id, 'actor_id' => $editor->id]);
        $this->actingAs($author)->deleteJson("/api/admin/announcements/$id")->assertOk();
    }

    public function test_all_draft_mutations_require_an_approved_admin(): void
    {
        $admin = $this->account('Admin');
        $id = $this->actingAs($admin)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        foreach ([$this->account(), $this->account('Admin', 'Pending'), $this->account('Admin', 'Inactive')] as $unauthorized) {
            $this->actingAs($unauthorized);
            $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertForbidden();
            $this->putJson("/api/admin/announcements/$id", self::FIELDS)->assertForbidden();
            $this->postJson("/api/admin/announcements/$id/publish", self::FIELDS)->assertForbidden();
            $this->deleteJson("/api/admin/announcements/$id")->assertForbidden();
        }
        $this->assertDatabaseCount('cfo_announcements', 1);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertNull(Announcement::findOrFail($id)->published_at);
    }

    public function test_publishing_notifies_only_approved_students_once_and_keeps_full_body_separate(): void
    {
        $approved = $this->account();
        $otherApproved = $this->account();
        $pending = $this->account('Student', 'Pending');
        $inactive = $this->account('Student', 'Inactive');
        $rejected = $this->account('Student', 'Rejected');
        $admin = $this->account('Admin');
        $id = $this->actingAs($admin)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->postJson("/api/admin/announcements/$id/publish", self::FIELDS)->assertOk();
        $this->postJson("/api/admin/announcements/$id/publish", self::FIELDS)->assertUnprocessable();
        $this->assertDatabaseCount('cfo_notifications', 2);
        foreach ([$approved, $otherApproved] as $student) {
            $this->assertDatabaseHas('cfo_notifications', ['user_id' => $student->id, 'type' => 'announcement', 'related_type' => 'announcement', 'related_id' => $id, 'message' => self::FIELDS['title'].' — '.self::FIELDS['preview']]);
        }
        foreach ([$pending, $inactive, $rejected, $admin] as $account) {
            $this->assertDatabaseMissing('cfo_notifications', ['user_id' => $account->id]);
        }
        $response = $this->actingAs($approved)->getJson('/api/student?resources=notifications')->assertOk()
            ->assertJsonPath('notifications.0.target', '#announcements?announcement='.$id);
        $this->assertStringNotContainsString(self::FIELDS['body'], $response->getContent());
    }

    public function test_original_publish_endpoint_cannot_treat_a_draft_key_as_published(): void
    {
        $this->actingAs($this->account('Admin'));
        $draft = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement');
        $this->postJson('/api/admin/announcements', [...self::FIELDS, 'publication_key' => $draft['publication_key']])->assertUnprocessable();
        $this->assertNull(Announcement::findOrFail($draft['id'])->published_at);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'announcement.published')->count());
    }

    public function test_draft_create_retries_with_the_same_composer_key_are_idempotent(): void
    {
        $author = $this->account('Admin');
        $otherAdmin = $this->account('Admin');
        $this->actingAs($author);
        $payload = [...self::FIELDS, 'publication_key' => (string) Str::uuid()];
        $draft = $this->postJson('/api/admin/announcements/drafts', $payload)->assertCreated()->json('announcement');
        $before = $this->versions();
        $this->postJson('/api/admin/announcements/drafts', $payload)->assertOk()->assertJsonPath('announcement.id', $draft['id']);
        $this->postJson('/api/admin/announcements/drafts', [...$payload, 'title' => 'Different draft'])->assertUnprocessable();
        $this->actingAs($otherAdmin)->postJson('/api/admin/announcements/drafts', $payload)->assertUnprocessable();
        $this->assertSame($before, $this->versions());
        $this->assertDatabaseCount('cfo_announcements', 1);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'announcement.draft_saved')->count());
        $this->actingAs($author)->postJson('/api/admin/announcements/'.$draft['id'].'/publish', self::FIELDS)->assertOk();
        $this->postJson('/api/admin/announcements/drafts', $payload)->assertUnprocessable();
    }

    public function test_revision_guard_prevents_stale_shared_admin_edits_and_publication_in_the_same_second(): void
    {
        $this->travelTo(now()->startOfSecond());
        $author = $this->account('Admin');
        $editor = $this->account('Admin');
        $draft = $this->actingAs($author)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement');
        $this->assertSame(64, strlen($draft['draft_revision']));
        $newContent = [...self::FIELDS, 'title' => 'Another admin changed the title'];
        $edited = $this->actingAs($editor)->putJson('/api/admin/announcements/'.$draft['id'], [...$newContent, 'draft_revision' => $draft['draft_revision']])
            ->assertOk()->json('announcement');
        $this->assertSame($draft['updated_at'], $edited['updated_at']);
        $this->assertNotSame($draft['draft_revision'], $edited['draft_revision']);
        $before = $this->versions();
        $this->actingAs($author)->putJson('/api/admin/announcements/'.$draft['id'], [...self::FIELDS, 'draft_revision' => $draft['draft_revision']])->assertUnprocessable();
        $this->postJson('/api/admin/announcements/'.$draft['id'].'/publish', [...self::FIELDS, 'draft_revision' => $draft['draft_revision']])->assertUnprocessable();
        $this->assertSame($before, $this->versions());
        $this->assertSame($newContent['title'], Announcement::findOrFail($draft['id'])->title);
        $this->assertNull(Announcement::findOrFail($draft['id'])->published_at);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->postJson('/api/admin/announcements/'.$draft['id'].'/publish', [...$newContent, 'draft_revision' => $edited['draft_revision']])
            ->assertOk()->assertJsonPath('announcement.draft_revision', null);
    }

    public function test_optional_retry_keys_and_revisions_are_validated_without_persisting_them(): void
    {
        $this->actingAs($this->account('Admin'));
        $this->postJson('/api/admin/announcements/drafts', [...self::FIELDS, 'publication_key' => 'not-a-uuid'])
            ->assertUnprocessable()->assertJsonValidationErrors('publication_key');
        $draft = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement');
        foreach (['', 'short', str_repeat('x', 65)] as $revision) {
            $this->putJson('/api/admin/announcements/'.$draft['id'], [...self::FIELDS, 'draft_revision' => $revision])
                ->assertUnprocessable()->assertJsonValidationErrors('draft_revision');
            $this->postJson('/api/admin/announcements/'.$draft['id'].'/publish', [...self::FIELDS, 'draft_revision' => $revision])
                ->assertUnprocessable()->assertJsonValidationErrors('draft_revision');
        }
        $this->assertFalse(Schema::hasColumn('cfo_announcements', 'draft_revision'));
        $this->assertNull(Announcement::findOrFail($draft['id'])->published_at);
    }

    public function test_stale_bound_draft_models_cannot_overwrite_a_publication(): void
    {
        $admin = $this->account('Admin');
        $id = $this->actingAs($admin)->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $stale = Announcement::findOrFail($id);
        $this->postJson("/api/admin/announcements/$id/publish", [...self::FIELDS, 'title' => 'Published final'])->assertOk();
        $before = $this->versions();
        $request = Request::create('/api/admin/announcements/'.$id, 'PUT', self::FIELDS);
        $request->setUserResolver(fn () => $admin);
        foreach (['saveAnnouncementDraft', 'publishAnnouncementDraft'] as $method) {
            try {
                app(AdminController::class)->$method($request, $stale);
                $this->fail('A stale bound model must be rechecked under the row lock.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('request', $exception->errors());
            }
        }
        $this->assertSame($before, $this->versions());
        $this->assertSame('Published final', Announcement::findOrFail($id)->title);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'announcement.published')->count());
    }

    public function test_publish_failure_rolls_back_the_draft_audit_and_live_versions(): void
    {
        $this->account();
        $this->actingAs($this->account('Admin'));
        $id = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $before = $this->versions();
        DB::unprepared("CREATE TRIGGER fail_announcement_notice BEFORE INSERT ON cfo_notifications BEGIN SELECT RAISE(ABORT, 'simulated notification failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->postJson("/api/admin/announcements/$id/publish", [...self::FIELDS, 'title' => 'Must roll back']);
            $this->fail('A failed notification write should abort publication.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated notification failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_announcement_notice');
        }
        $this->assertSame($before, $this->versions());
        $this->assertNull(Announcement::findOrFail($id)->published_at);
        $this->assertSame(self::FIELDS['title'], Announcement::findOrFail($id)->title);
        $this->assertDatabaseCount('cfo_notifications', 0);
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'announcement.published')->count());
    }

    public function test_migration_rollback_refuses_to_discard_or_force_publish_drafts(): void
    {
        $this->actingAs($this->account('Admin'));
        $id = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $migration = require database_path('migrations/2026_10_11_000001_allow_announcement_drafts.php');
        try {
            $migration->down();
            $this->fail('Rollback must refuse to make active drafts non-nullable.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Publish or delete draft announcements before rolling this migration back.', $exception->getMessage());
        }
        $publishedAt = collect(Schema::getColumns('cfo_announcements'))->firstWhere('name', 'published_at');
        $this->assertTrue($publishedAt['nullable']);
        $this->assertNull(Announcement::findOrFail($id)->published_at);
    }

    public function test_delete_failure_restores_published_announcements_notifications_and_audit(): void
    {
        $this->account();
        $this->actingAs($this->account('Admin'));
        $id = $this->postJson('/api/admin/announcements/drafts', self::FIELDS)->assertCreated()->json('announcement.id');
        $this->postJson("/api/admin/announcements/$id/publish", self::FIELDS)->assertOk();
        $before = $this->versions();
        DB::unprepared("CREATE TRIGGER fail_announcement_delete BEFORE DELETE ON cfo_announcements BEGIN SELECT RAISE(ABORT, 'simulated delete failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->deleteJson("/api/admin/announcements/$id");
            $this->fail('A failed delete should restore notifications and audit.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('simulated delete failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER fail_announcement_delete');
        }
        $this->assertSame($before, $this->versions());
        $this->assertNotNull(Announcement::findOrFail($id)->published_at);
        $this->assertDatabaseCount('cfo_notifications', 1);
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'announcement.deleted')->count());
    }
}
