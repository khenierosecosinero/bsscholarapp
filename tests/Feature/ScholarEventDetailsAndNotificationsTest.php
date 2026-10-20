<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ScholarNotification;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScholarEventDetailsAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scholar_event_details_do_not_show_the_back_to_events_link(): void
    {
        $program = $this->makeProgram();
        $scholar = $this->makeScholar($program);

        Event::create([
            'title' => 'Campus Outreach',
            'location' => 'City Hall',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'service_hours' => 3,
            'status' => Event::STATUS_PENDING,
            'scholarship_program_id' => $program->id,
            'attendance_is_open' => false,
        ]);

        $this->actingAs($scholar)
            ->get(route('user.events'))
            ->assertOk()
            ->assertSee('Campus Outreach')
            ->assertSee('Pending')
            ->assertDontSee('Back to Events', false);
    }

    public function test_see_more_returns_only_the_scholars_older_notifications_without_changing_them(): void
    {
        $program = $this->makeProgram();
        $scholar = $this->makeScholar($program);
        $other = $this->makeScholar($program, 'Other Scholar');

        foreach (range(1, 7) as $number) {
            $notification = ScholarNotification::create([
                'user_id' => $scholar->id,
                'title' => "Notice {$number}",
                'body' => "Body {$number}",
                'category' => 'system',
                'is_read' => $number !== 7,
                'is_important' => false,
            ]);
            $notification->forceFill([
                'created_at' => now()->subMinutes(8 - $number),
                'updated_at' => now()->subMinutes(8 - $number),
            ])->save();
        }

        ScholarNotification::create([
            'user_id' => $other->id,
            'title' => 'Private notice',
            'body' => 'Belongs to another scholar.',
            'category' => 'system',
            'is_read' => false,
            'is_important' => true,
        ]);

        $page = $this->actingAs($scholar)
            ->get(route('user.notifications'))
            ->assertOk()
            ->assertSee('Notice 7')
            ->assertSee('Notice 3')
            ->assertDontSee('Notice 2')
            ->assertDontSee('Notice 1')
            ->assertDontSee('Private notice')
            ->assertSee('See More Notifications')
            ->assertSee('data-notifications-toggle', false)
            ->assertDontSee('Show Less Notifications');

        $previewIds = ScholarNotification::query()
            ->where('user_id', $scholar->id)
            ->latest()
            ->orderByDesc('id')
            ->limit(5)
            ->pluck('id');

        $more = $this->actingAs($scholar)
            ->getJson(route('user.notifications.more', [
                'filter' => 'all',
                'except' => $previewIds->implode(','),
            ]))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('total', 7);

        $html = $more->json('html');
        $this->assertStringContainsString('Notice 2', $html);
        $this->assertStringContainsString('Notice 1', $html);
        $this->assertStringContainsString('data-notification-extra', $html);
        $this->assertStringNotContainsString('Notice 7', $html);
        $this->assertStringNotContainsString('Private notice', $html);

        $this->assertFalse(ScholarNotification::query()->where('user_id', $scholar->id)->where('title', 'Notice 7')->first()->is_read);
        $this->assertSame(8, ScholarNotification::query()->count());
        $this->assertStringContainsString(
            'See Less Notifications',
            file_get_contents(public_path('js/notifications-toggle.js'))
        );

        $page->assertSee('Showing 5 of 7 notification(s)');
    }

    public function test_notifications_page_hides_the_toggle_when_there_is_nothing_more_to_show(): void
    {
        $program = $this->makeProgram();
        $empty = $this->makeScholar($program, 'Empty Scholar');

        $this->actingAs($empty)
            ->get(route('user.notifications'))
            ->assertOk()
            ->assertSee('No notifications found.')
            ->assertDontSee('See More Notifications');

        $scholar = $this->makeScholar($program);
        foreach (range(1, 5) as $number) {
            ScholarNotification::create([
                'user_id' => $scholar->id,
                'title' => "Short {$number}",
                'body' => "Body {$number}",
                'category' => 'system',
                'is_read' => true,
                'is_important' => false,
            ]);
        }

        $this->actingAs($scholar)
            ->get(route('user.notifications'))
            ->assertOk()
            ->assertSee('Short 1')
            ->assertDontSee('See More Notifications')
            ->assertDontSee('id="notifications-toggle"', false);
    }

    private function makeProgram(): ScholarshipProgram
    {
        return ScholarshipProgram::create([
            'location_name' => 'Surigao del Norte',
            'location_type' => 'province',
            'name' => 'Surigao del Norte Scholarship Program',
            'display_name' => 'Surigao del Norte (Province)',
            'slug' => 'surigao-del-norte-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeScholar(ScholarshipProgram $program, string $name = 'Dashboard Scholar'): User
    {
        return User::register([
            'full_name' => $name,
            'scholar_id' => 'SCH-'.uniqid(),
            'email' => 'scholar-'.uniqid().'@example.com',
            'password' => 'password123',
            'role' => User::ROLE_SCHOLAR,
            'status' => User::STATUS_APPROVED,
            'scholarship_program_id' => $program->id,
            'province' => 'Surigao del Norte',
        ]);
    }
}
