<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\HRM\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'string', 'email']]);
        $email = strtolower(trim($data['email']));

        // Check if employee exists and app is activated
        $employee = Employee::where('Email', $email)->first();
        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        if (! $employee->IsMobileAppActivated) {
            return response()->json(['message' => 'Mobile App access is not activated for this employee.'], 403);
        }

        // Check if user account exists
        $user = User::where('Email', $email)->first();
        if (! $user) {
            return response()->json(['message' => 'User account not found for this employee.'], 404);
        }

        $code = (string) random_int(100000, 999999);
        $ttl = 5; // 5 minutes

        DB::table('email_otp_codes')->updateOrInsert(
            ['email' => $email],
            [
                'code' => $code,
                'expires_at' => Carbon::now()->addMinutes($ttl),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );

        try {
            if (config('mail.default')) {
                Mail::raw("Your mobile app login verification code is {$code}. It will expire in 5 minutes.", function ($m) use ($email) {
                    $m->to($email)->subject('Mobile App Verification Code');
                });
            } else {
                Log::info("Email OTP for {$email}: {$code}");
            }
        } catch (\Throwable $e) {
            Log::error('Email send failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to send code'], 502);
        }

        return response()->json(['ok' => true, 'message' => 'Verification code sent to your email.']);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'digits:6'],
        ]);
        $email = strtolower(trim($data['email']));
        $code = $data['code'];

        $row = DB::table('email_otp_codes')->where('email', $email)->first();
        if (! $row || $row->code !== $code || Carbon::parse($row->expires_at)->isPast()) {
            return response()->json(['message' => 'Your code is either invalid or expired.'], 422);
        }

        DB::table('email_otp_codes')->where('email', $email)->delete();

        $user = User::where('Email', $email)->first();
        if (! $user) {
            return response()->json(['message' => 'User account not found.'], 404);
        }

        // Issue token
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->Id,
                'name' => $user->Name,
                'email' => $user->Email,
            ],
        ]);
    }
}
