<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SCHOLAR = 'scholar';

    public const ROLE_SCHOLAR_STAFF = 'scholar_staff';

    public const ROLE_ADMIN = 'admin';

    public const PERMANENT_ADMIN_EMAIL = 'bssa_admin@gmail.com';

    public const PERMANENT_ADMIN_SCHOLAR_ID = 'ADMIN-001';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_INACTIVE = 'inactive';

    public const PRESENCE_SECONDS = 90;

    /**
     * Login username (email), scholar ID, and password are intentionally excluded
     * from mass assignment. They may only be set at registration or through
     * dedicated credential methods.
     */
    protected $fillable = [
        'full_name',
        'school_university',
        'course_year_level',
        'year_level',
        'cellphone_number',
        'city',
        'province',
        'date_of_birth',
        'guardian_name',
        'guardian_relationship',
        'guardian_cellphone',
        'avatar_path',
        'status',
        'notification_preferences',
        'is_admin',
        'role',
        'scholarship_program_id',
        'scholarship_club_id',
        'scholarship_club_school_id',
        'academic_year_start',
        'semester',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'notification_preferences' => 'array',
            'is_admin' => 'boolean',
            'is_permanent' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'events_visible_from' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin || $this->role === self::ROLE_ADMIN;
    }

    public function isPermanentAdmin(): bool
    {
        if ((bool) $this->is_permanent) {
            return true;
        }

        return strcasecmp((string) $this->email, self::PERMANENT_ADMIN_EMAIL) === 0
            || strcasecmp((string) $this->scholar_id, self::PERMANENT_ADMIN_SCHOLAR_ID) === 0;
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (! $user->wasOriginallyPermanentAdmin() && ! $user->isPermanentAdmin()) {
                return;
            }

            $user->is_permanent = true;
            $user->is_admin = true;
            $user->role = self::ROLE_ADMIN;
        });

        static::deleting(function (User $user) {
            if ($user->isPermanentAdmin() || $user->wasOriginallyPermanentAdmin()) {
                return false;
            }
        });
    }

    private function wasOriginallyPermanentAdmin(): bool
    {
        if ((bool) $this->getOriginal('is_permanent')) {
            return true;
        }

        return strcasecmp((string) $this->getOriginal('email'), self::PERMANENT_ADMIN_EMAIL) === 0
            || strcasecmp((string) $this->getOriginal('scholar_id'), self::PERMANENT_ADMIN_SCHOLAR_ID) === 0;
    }

    public function isScholar(): bool
    {
        return $this->role === self::ROLE_SCHOLAR;
    }

    public function isScholarStaff(): bool
    {
        return $this->role === self::ROLE_SCHOLAR_STAFF;
    }

    public function isPendingApproval(): bool
    {
        return $this->isScholar() && $this->status === self::STATUS_PENDING;
    }

    public function isStaffPendingApproval(): bool
    {
        return $this->isScholarStaff() && $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->isScholar() && $this->status === self::STATUS_REJECTED;
    }

    public function isStaffRejected(): bool
    {
        return $this->isScholarStaff() && $this->status === self::STATUS_REJECTED;
    }

    public function isStaffInactive(): bool
    {
        return $this->isScholarStaff() && $this->status === self::STATUS_INACTIVE;
    }

    public function staffStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_INACTIVE => 'Inactive',
            default => ucfirst((string) $this->status),
        };
    }

    public function staffStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'green',
            self::STATUS_PENDING => 'orange',
            self::STATUS_REJECTED => 'red',
            self::STATUS_INACTIVE => 'gray',
            default => 'gray',
        };
    }

    public function hasScholarPortalAccess(): bool
    {
        return $this->isScholar() && $this->status === self::STATUS_APPROVED;
    }

    public function hasStaffPortalAccess(): bool
    {
        return $this->isScholarStaff() && $this->status === self::STATUS_APPROVED;
    }

    /**
     * Newly approved/created scholar or staff get a start timestamp so they
     * only see events created after they became active. Pending accounts use
     * the approval time, not the registration time.
     */
    public function activateFreshEventList(): void
    {
        if ((! $this->isScholar() && ! $this->isScholarStaff()) || $this->events_visible_from !== null) {
            return;
        }

        $this->forceFill(['events_visible_from' => now()])->save();
    }

    public function activateFreshStaffEventList(): void
    {
        $this->activateFreshEventList();
    }

    public function eventsVisibleFrom(): ?Carbon
    {
        if (! $this->isScholar() && ! $this->isScholarStaff()) {
            return null;
        }

        return $this->events_visible_from ?? $this->created_at;
    }

    public function staffEventsVisibleFrom(): ?Carbon
    {
        return $this->isScholarStaff() ? $this->eventsVisibleFrom() : null;
    }

    public function hasFreshEventCalendar(): bool
    {
        return $this->isScholar() && $this->events_visible_from !== null;
    }

    public function canViewStaffManagedEvent(Event $event): bool
    {
        if (! $this->isScholarStaff()) {
            return false;
        }

        $programOk = $event->scholarship_program_id
            && in_array((int) $event->scholarship_program_id, array_map('intval', $this->managedLocationIds()), true);

        if (! $programOk) {
            return false;
        }

        $from = $this->staffEventsVisibleFrom();
        if ($from === null) {
            return true;
        }

        return $event->created_at !== null && $event->created_at->gte($from);
    }

    public function canLogin(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isScholarStaff()) {
            return $this->status === self::STATUS_APPROVED;
        }

        if ($this->isScholar()) {
            return $this->status !== self::STATUS_REJECTED;
        }

        return true;
    }

    /**
     * Accounts are permanent: inactivity never blocks login.
     */
    public function markLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }

    public function markPresence(): void
    {
        $this->forceFill(['last_seen_at' => now()])->save();
    }

    public function clearPresence(): void
    {
        if ($this->last_seen_at === null) {
            return;
        }

        $this->forceFill(['last_seen_at' => null])->save();
    }

    public function isPresentNow(): bool
    {
        return $this->isScholar()
            && $this->last_seen_at !== null
            && $this->last_seen_at->gte(now()->subSeconds(self::PRESENCE_SECONDS));
    }

    public function presenceLabel(): string
    {
        return $this->isPresentNow() ? 'Active Now' : 'Offline';
    }

    public function municipalityName(): ?string
    {
        if ($this->city) {
            return $this->city;
        }

        $program = $this->scholarshipProgram;

        if (! $program) {
            return null;
        }

        return $program->isCityOrMunicipality() ? $program->location_name : null;
    }

    public function provinceName(): ?string
    {
        if ($this->province) {
            return $this->province;
        }

        $program = $this->scholarshipProgram;

        if (! $program) {
            return null;
        }

        return $program->isProvince()
            ? $program->location_name
            : $program->province_name;
    }

    public function scholarshipClubName(): string
    {
        return $this->scholarshipClub?->name
            ?? $this->scholarshipProgram?->clubName()
            ?? 'Unassigned';
    }

    /**
     * Staff contact shown in Settings and Admin Staff: cellphone only.
     */
    public function contactNumber(): string
    {
        return filled($this->cellphone_number) ? (string) $this->cellphone_number : '';
    }

    public function scholarshipClubCity(): ?string
    {
        if (filled($this->scholarshipClub?->city)) {
            return $this->scholarshipClub->city;
        }

        $location = $this->scholarshipClub?->program?->registrationLocation();

        return $location['city'] ?? null;
    }

    public function scholarshipClubProvince(): ?string
    {
        if (filled($this->scholarshipClub?->province)) {
            return $this->scholarshipClub->province;
        }

        $location = $this->scholarshipClub?->program?->registrationLocation();

        return $location['province'] ?? null;
    }

    public function scholarshipClubAddress(): string
    {
        $city = $this->scholarshipClubCity();
        $province = $this->scholarshipClubProvince();

        if ($city && $province) {
            return $city.', '.$province;
        }

        return $city ?: $province ?: '';
    }

    public function scholarshipClubLabel(): string
    {
        $name = $this->scholarshipClubName();
        $address = $this->scholarshipClubAddress();

        return $address !== '' ? $name.' — '.$address : $name;
    }

    public function locationLabel(): string
    {
        if ($this->scholarshipProgram) {
            return $this->scholarshipProgram->programLabel();
        }

        $municipality = $this->municipalityName();
        $province = $this->provinceName();

        if ($municipality && $province) {
            return "{$municipality}, {$province}";
        }

        return $municipality ?: $province ?: 'Unassigned';
    }

    /**
     * Scholarship program IDs this staff member may manage (their assigned program only).
     */
    public function managedLocationIds(): array
    {
        if (! $this->scholarshipProgram) {
            return [];
        }

        return $this->scholarshipProgram->coveredLocationIds();
    }

    /**
     * Scholarship program IDs this user may view content for (their assigned program only).
     */
    public function visibleLocationIds(): array
    {
        if (! $this->scholarshipProgram) {
            return [];
        }

        return $this->scholarshipProgram->visibleLocationIds();
    }

    public function canManageScholar(User $scholar): bool
    {
        if (! $scholar->isScholar() || $scholar->isScholarStaff() || $scholar->isStaffPendingApproval()) {
            return false;
        }

        if ($this->scholarship_club_id && $scholar->scholarship_club_id) {
            return (int) $this->scholarship_club_id === (int) $scholar->scholarship_club_id;
        }

        $managed = $this->managedLocationIds();

        return $scholar->scholarship_program_id
            && in_array((int) $scholar->scholarship_program_id, array_map('intval', $managed), true);
    }

    /**
     * Pending scholar staff accounts are visible only to administrators.
     * Approved staff may see other approved staff in the same program scope.
     */
    public function canViewStaffAccount(User $staffMember): bool
    {
        if (! $staffMember->isScholarStaff()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isScholarStaff() || ! $this->hasStaffPortalAccess()) {
            return false;
        }

        if (! $staffMember->hasStaffPortalAccess()) {
            return false;
        }

        $managed = $this->managedLocationIds();

        return $staffMember->scholarship_program_id
            && in_array((int) $staffMember->scholarship_program_id, array_map('intval', $managed), true);
    }

    public function scholarshipProgram(): BelongsTo
    {
        return $this->belongsTo(ScholarshipProgram::class);
    }

    public function scholarshipClub(): BelongsTo
    {
        return $this->belongsTo(ScholarshipClub::class);
    }

    public function scholarshipClubSchool(): BelongsTo
    {
        return $this->belongsTo(ScholarshipClubSchool::class, 'scholarship_club_school_id');
    }

    /**
     * Internal unique identity for scholar staff. Not shown as Scholar Staff Number.
     */
    public static function nextStaffIdentity(): string
    {
        do {
            $identity = 'STAFF-'.strtoupper((string) Str::ulid());
        } while (static::query()->where('scholar_id', $identity)->exists());

        return $identity;
    }

    /**
     * Create a new scholar account with immutable login credentials.
     * Associated records are provisioned separately; nothing is auto-deleted afterward.
     */
    public static function register(array $attributes): self
    {
        $role = $attributes['role'] ?? self::ROLE_SCHOLAR;

        if (empty($attributes['scholar_id']) && $role === self::ROLE_SCHOLAR_STAFF) {
            $attributes['scholar_id'] = static::nextStaffIdentity();
        }

        foreach (['email', 'scholar_id', 'password', 'full_name'] as $required) {
            if (empty($attributes[$required])) {
                throw new InvalidArgumentException("Missing required registration field: {$required}");
            }
        }

        $user = new static;
        $user->fill(collect($attributes)->only([
            'full_name',
            'school_university',
            'course_year_level',
            'year_level',
            'cellphone_number',
            'city',
            'province',
        ])->all());

        $user->email = strtolower(trim($attributes['email']));
        $user->scholar_id = trim($attributes['scholar_id']);
        $user->password = $attributes['password'];
        $user->status = $attributes['status'] ?? 'approved';
        $user->role = $attributes['role'] ?? self::ROLE_SCHOLAR;
        $user->scholarship_program_id = $attributes['scholarship_program_id'] ?? null;
        $user->scholarship_club_id = $attributes['scholarship_club_id'] ?? null;
        $user->scholarship_club_school_id = $attributes['scholarship_club_school_id'] ?? null;

        if (($user->isScholar() || $user->isScholarStaff()) && $user->status === self::STATUS_APPROVED) {
            $user->events_visible_from = now();
        }

        if (in_array($user->role, [self::ROLE_SCHOLAR, self::ROLE_SCHOLAR_STAFF], true) && ! $user->scholarship_program_id) {
            throw new InvalidArgumentException('Scholars and scholar staff must be linked to a scholarship program.');
        }

        $user->save();

        return $user->fresh(['scholarshipProgram', 'scholarshipClub']);
    }

    /**
     * Update the account password (hashed automatically via cast).
     */
    public function updatePassword(string $plainPassword): void
    {
        $this->password = $plainPassword;
        $this->save();
    }

    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function scholarNotifications(): HasMany
    {
        return $this->hasMany(ScholarNotification::class);
    }

    public function unreadNotificationCount(): int
    {
        return $this->scholarNotifications()->where('is_read', false)->count();
    }

    public function initials(): string
    {
        return strtoupper(substr($this->full_name ?: 'U', 0, 1));
    }

    public function hasAvatar(): bool
    {
        return filled($this->avatar_path)
            && Storage::disk('public')->exists($this->avatar_path);
    }

    public function avatarUrl(): ?string
    {
        if (! $this->hasAvatar()) {
            return null;
        }

        $url = '/storage/'.ltrim((string) $this->avatar_path, '/');
        $version = $this->updated_at?->timestamp ?? time();

        return $url.'?v='.$version;
    }

    public function announcementReads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function defaultNotificationPreferences(): array
    {
        return [
            'event_reminders' => true,
            'attendance_updates' => true,
            'service_hours' => true,
            'document_updates' => true,
            'announcements' => true,
        ];
    }

    public function notificationPreferences(): array
    {
        return array_merge(
            $this->defaultNotificationPreferences(),
            $this->notification_preferences ?? []
        );
    }
}
