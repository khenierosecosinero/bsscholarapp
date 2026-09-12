<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SCHOLAR = 'scholar';

    public const ROLE_SCHOLAR_STAFF = 'scholar_staff';

    public const ROLE_ADMIN = 'admin';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

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
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin || $this->role === self::ROLE_ADMIN;
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

    public function hasScholarPortalAccess(): bool
    {
        return $this->isScholar() && $this->status === self::STATUS_APPROVED;
    }

    public function hasStaffPortalAccess(): bool
    {
        return $this->isScholarStaff() && $this->status === self::STATUS_APPROVED;
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

    /**
     * Create a new scholar account with immutable login credentials.
     * Associated records are provisioned separately; nothing is auto-deleted afterward.
     */
    public static function register(array $attributes): self
    {
        foreach (['email', 'scholar_id', 'password', 'full_name'] as $required) {
            if (empty($attributes[$required])) {
                throw new InvalidArgumentException("Missing required registration field: {$required}");
            }
        }

        $user = new static();
        $user->fill(collect($attributes)->only([
            'full_name',
            'school_university',
            'course_year_level',
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

        if (in_array($user->role, [self::ROLE_SCHOLAR, self::ROLE_SCHOLAR_STAFF], true) && ! $user->scholarship_program_id) {
            throw new InvalidArgumentException('Scholars and scholar staff must be linked to a scholarship program.');
        }

        $user->save();

        return $user->fresh(['scholarshipProgram']);
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
