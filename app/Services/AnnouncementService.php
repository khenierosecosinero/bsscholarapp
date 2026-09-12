<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\ScholarNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class AnnouncementService
{
    public function __construct(private ScholarService $scholar) {}

    public function publishedQuery(?User $user = null)
    {
        $query = Announcement::published()->orderByDesc('published_at')->orderByDesc('created_at');

        if ($user) {
            $query = $this->scholar->scopeAnnouncementsForUser($query, $user);
        }

        return $query;
    }

    public function forUser(User $user, ?int $limit = null, ?string $filter = 'all'): Collection
    {
        $readIds = AnnouncementRead::where('user_id', $user->id)->pluck('announcement_id');

        $query = $this->publishedQuery($user);

        if ($filter === 'unread') {
            $query->whereNotIn('id', $readIds);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get()->map(function (Announcement $announcement) use ($readIds) {
            $announcement->setAttribute('is_read', $readIds->contains($announcement->id));

            return $announcement;
        });
    }

    public function statsForUser(User $user): array
    {
        $readIds = AnnouncementRead::where('user_id', $user->id)->pluck('announcement_id');
        $total = $this->publishedQuery($user)->count();
        $read = $this->publishedQuery($user)->whereIn('id', $readIds)->count();
        $unread = max(0, $total - $read);

        return compact('total', 'read', 'unread');
    }

    public function markAsRead(User $user, Announcement $announcement): void
    {
        $this->scholar->assertAnnouncementVisibleToUser($announcement, $user);

        AnnouncementRead::updateOrCreate(
            ['user_id' => $user->id, 'announcement_id' => $announcement->id],
            ['read_at' => now()]
        );

        ScholarNotification::where('user_id', $user->id)
            ->where('announcement_id', $announcement->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function markAllAsRead(User $user): void
    {
        $announcements = $this->publishedQuery($user)->get();

        foreach ($announcements as $announcement) {
            $this->markAsRead($user, $announcement);
        }
    }

    public function publish(array $data, ?Announcement $announcement = null): Announcement
    {
        $payload = [
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => $data['published_at'] ?? now(),
            'scholarship_program_id' => $data['scholarship_program_id'] ?? null,
        ];

        if ($announcement) {
            $announcement->update($payload);
        } else {
            $announcement = Announcement::create($payload);
        }

        $this->notifyUsers($announcement, (bool) ($data['notify'] ?? true));

        return $announcement->fresh();
    }

    public function notifyUsers(Announcement $announcement, bool $notify = true): void
    {
        if (!$notify) {
            return;
        }

        User::query()
            ->where('role', User::ROLE_SCHOLAR)
            ->when($announcement->scholarship_program_id, function ($query) use ($announcement) {
                $query->where('scholarship_program_id', $announcement->scholarship_program_id);
            }, fn ($query) => $query->whereRaw('1 = 0'))
            ->each(function (User $user) use ($announcement) {
            $prefs = $user->notificationPreferences();
            if (!($prefs['announcements'] ?? true)) {
                return;
            }

            ScholarNotification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'announcement_id' => $announcement->id,
                ],
                [
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'category' => 'announcement',
                    'is_important' => true,
                    'is_read' => false,
                ]
            );
        });
    }
}
