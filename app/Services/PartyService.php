<?php

namespace App\Services;

use App\Helpers\SystemHelper;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Services\HRM\UserService;
use Illuminate\Database\Eloquent\Model;

/** Standalone DMS party helper: only internal users and teams are supported. */
class PartyService
{
    public function __construct(public ?Model $party)
    {
    }

    public function getImage(string $attr = 'class="img-thumbnail me-2" width="40" height="40"'): string
    {
        if ($this->party instanceof User) {
            return $this->party->getImage($attr);
        }

        $text = $this->party instanceof Team ? 'TEAM' : 'N/A';

        return '<img src="https://placehold.co/200x200?font=roboto&text=' . $text . '" ' . $attr . '/>';
    }

    public function getDTRow(string $unknown = ''): string
    {
        if ($this->party instanceof User) {
            return '<span>' . $this->getImage() . ' ' . e($this->party->Name) . ' (Employee)</span>';
        }
        if ($this->party instanceof Team) {
            return '<span>' . e($this->party->Name) . ' (Team)</span>';
        }

        return $this->getImage() . ' Unknown ' . e($unknown);
    }

    public function getName(bool $simpleRole = false): string
    {
        if ($this->party instanceof User || $this->party instanceof Team) {
            return trim($this->party->Name . ' ' . $this->getRole($simpleRole));
        }

        return 'Unknown';
    }

    public function getRole(bool $simpleRole = false): string
    {
        return match (true) {
            $this->party instanceof User => $simpleRole ? '(E)' : '(Employee)',
            $this->party instanceof Team => $simpleRole ? '(T)' : '(Team)',
            default => '',
        };
    }

    public function simplified(bool $simpleRole = false, bool $summary = false, string $unknown = 'unknown'): string
    {
        if ($this->party instanceof User) {
            return '<span>' . e($this->getName($simpleRole)) . '</span>';
        }
        if ($this->party instanceof Team) {
            return $summary
                ? '<span>' . e($this->getName($simpleRole)) . '</span>'
                : '<a href="' . route('teams.show', [$this->party->TeamID]) . '">' . e($this->getName($simpleRole)) . '</a>';
        }

        return e($unknown);
    }

    public function sendEmail(string $subject, string $body): void
    {
        if ($this->party instanceof User) {
            (new UserService($this->party))->sendEmail($subject, $body);
        } elseif ($this->party instanceof Team) {
            CRMEmailService::createTeam($this->party, $subject, $body, SystemHelper::user())->send();
        }
    }
}
