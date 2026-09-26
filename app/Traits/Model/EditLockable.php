<?php

namespace App\Traits\Model;

use App\Models\Auth\User;
use Carbon\Carbon;

/**
 * Provides edit-locking functionality for models to prevent concurrent editing.
 * When a user opens a record for editing, it's locked for 30 minutes to prevent
 * other users from simultaneously editing and saving conflicting changes.
 *
 * Usage:
 *  1. Add columns to the model's table: EditLockedBy (unsignedBigInteger), EditLockedAt (timestamp)
 *  2. Add the trait to the model: use EditLockable;
 *  3. Use in controllers: $model->lockForEdit($user) and $model->unlockForEdit()
 */
trait EditLockable
{
    /**
     * Lock the record for editing by the given user.
     * Sets EditLockedBy to the user ID and EditLockedAt to the current timestamp.
     */
    public function lockForEdit(User $user): void
    {
        $this->update([
            'EditLockedBy' => $user->Id,
            'EditLockedAt' => now(),
        ]);
    }

    /**
     * Unlock the record from editing.
     * Clears both EditLockedBy and EditLockedAt.
     */
    public function unlockForEdit(): void
    {
        $this->update([
            'EditLockedBy' => null,
            'EditLockedAt' => null,
        ]);
    }

    /**
     * Check if the record is currently locked for editing.
     * A lock is valid for 30 minutes from when it was set.
     */
    public function isEditLocked(): bool
    {
        if ($this->EditLockedBy === null || $this->EditLockedAt === null) {
            return false;
        }

        // Lock is valid for 30 minutes
        return $this->EditLockedAt instanceof Carbon
            ? $this->EditLockedAt->isAfter(now()->subMinutes(30))
            : Carbon::parse($this->EditLockedAt)->isAfter(now()->subMinutes(30));
    }

    /**
     * Check if the record is locked by a specific user.
     */
    public function isEditLockedBy(User $user): bool
    {
        return (int) $this->EditLockedBy === (int) $user->Id && $this->isEditLocked();
    }

    /**
     * Get the user who locked the record.
     * Requires a relationship to be defined by the using class.
     */
    public function editLockedBy()
    {
        return $this->belongsTo(User::class, 'EditLockedBy', 'Id');
    }

    /**
     * Force clear the lock (for admin operations or timeout cleanup).
     */
    public function clearEditLock(): void
    {
        $this->update([
            'EditLockedBy' => null,
            'EditLockedAt' => null,
        ]);
    }
}
