<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;
use App\Models\DMS\DocumentReview;

class DocumentReviewPolicy
{
    /**
     * Determine whether the user can view any models (the review inbox).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentReview $documentReview): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return $this->participate($user, $documentReview);
    }

    /**
     * Requester and reviewer can both add annotations - the review is a back-and-forth
     * between the two of them, not a broadcast to everyone who can see the document.
     */
    public function participate(User $user, DocumentReview $documentReview): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return $documentReview->isReviewer($user) || $user->Id === $documentReview->CreatedBy;
    }

    /**
     * Determine whether the user can mark the review as complete.
     */
    public function update(User $user, DocumentReview $documentReview): bool
    {
        return $documentReview->isReviewer($user);
    }
}
