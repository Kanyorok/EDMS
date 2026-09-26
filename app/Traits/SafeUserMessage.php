<?php

namespace App\Traits;

trait SafeUserMessage
{
    /**
     * Return a safe, user-friendly message.
     *
     * If the original message is empty or looks like a technical/database
     * error, the fallback string is returned instead — preventing internal
     * implementation details (table names, SQL fragments, etc.) from leaking
     * to end users.
     *
     * @param  string|null  $message   Raw exception / system message.
     * @param  string       $fallback  Friendly message shown when the raw one is unsafe.
     * @return string
     */
    protected function safeUserMessage(?string $message, string $fallback): string
    {
        $clean = trim((string) $message);

        if ($clean === '' || $this->looksTechnical($clean)) {
            return $fallback;
        }

        return $clean;
    }

    /**
     * Determine whether a message looks like a raw technical / database error
     * that should not be shown directly to end users.
     *
     * Matches common SQL keywords, Laravel query builder fragments,
     * internal table name prefixes (t_*), and database constraint terms.
     *
     * @param  string  $message
     * @return bool
     */
    private function looksTechnical(string $message): bool
    {
        return (bool) preg_match(
            '/(sqlstate|syntax|select\s|insert\s|update\s|delete\s|from\s|join\s|\bt_[a-z0-9_]+\b|constraint|foreign key|column|table)/i',
            $message
        );
    }
}
