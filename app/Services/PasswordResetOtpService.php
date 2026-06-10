<?php

namespace App\Services;

use App\Enums\PasswordResetContext;
use App\Mail\PasswordResetOtpMail;
use App\Models\PasswordResetOtp;
use App\Support\DynamicMailConfigurator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetOtpService
{
    public const int OTP_LENGTH = 6;

    public const int EXPIRY_MINUTES = 10;

    public function send(PasswordResetContext $context, string $email, string $recipientName): void
    {
        $normalizedEmail = Str::lower(trim($email));
        $otp = $this->generateOtp();

        PasswordResetOtp::query()
            ->where('context', $context)
            ->where('email', $normalizedEmail)
            ->delete();

        PasswordResetOtp::query()->create([
            'context' => $context,
            'email' => $normalizedEmail,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        DynamicMailConfigurator::apply();

        Mail::to($normalizedEmail)->send(new PasswordResetOtpMail(
            recipientName: $recipientName,
            otp: $otp,
            expiryMinutes: self::EXPIRY_MINUTES,
        ));
    }

    public function verify(PasswordResetContext $context, string $email, string $otp): bool
    {
        $normalizedEmail = Str::lower(trim($email));
        $record = PasswordResetOtp::query()
            ->where('context', $context)
            ->where('email', $normalizedEmail)
            ->latest('id')
            ->first();

        if ($record === null || $record->expires_at->isPast()) {
            return false;
        }

        if (! Hash::check($otp, $record->otp_hash)) {
            return false;
        }

        $record->delete();

        return true;
    }

    protected function generateOtp(): string
    {
        return str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }
}
