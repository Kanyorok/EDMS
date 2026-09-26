<?php

namespace App\Http\Controllers\HRM;

use App\Enums\EmailPriorityEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Base\MailToRequest;
use App\Models\HRM\Employee;
use App\Services\CRMEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeEmailController extends Controller
{
    public function store(MailToRequest $request, Employee $employee): JsonResponse
    {
        $email = $request->getEmail();
        if (strcasecmp((string) $employee->Email, $email) !== 0) {
            throw ValidationException::withMessages([
                'mail_to' => 'The email address does not belong to this employee.',
            ]);
        }

        try {
            DB::transaction(static function () use ($request, $employee, $email): void {
                CRMEmailService::createRaw(
                    actor: $request->user(),
                    subject: $request->validated('mail_subject'),
                    body: $request->validated('mail_content'),
                    to: [[$employee->full_name => $email]],
                    Party: Employee::getPrimaryKey(),
                    PartyID: $employee->EmployeeID,
                    cc: $request->getCarbonCopyEmails(),
                    priorityEnum: EmailPriorityEnum::Normal,
                )->send();
            });
        } catch (Throwable $e) {
            Log::error('Error sending email to employee.', [
                'employee_id' => $employee->EmployeeID,
                'error' => $e->getMessage(),
            ]);

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('email sent successfully');
    }
}
