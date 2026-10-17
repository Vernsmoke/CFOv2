<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class CfoMaintenance
{
    /**
     * Repair work is a safety net for data created outside normal application
     * mutations. Keep it out of the hot path used by live polling.
     */
    public static function runOncePerSessionDay(Request $request, User $user): void
    {
        $date = today()->toDateString();
        $scope = $user->role === 'Admin' ? 'all' : 'user-'.$user->id;
        $marker = $date.'|'.$scope;

        if ($request->session()->get('cfo_maintenance') === $marker) {
            return;
        }

        $cacheKey = 'cfo:maintenance:'.$marker;
        if (! Cache::has($cacheKey)) {
            Cache::lock($cacheKey.':lock', 30)->block(10, function () use ($cacheKey, $user): void {
                Cache::remember($cacheKey, now()->addDays(2), function () use ($user): bool {
                    Registration::archivePastYears();
                    AutomaticCertificate::syncEligible($user->role === 'Admin' ? null : $user->id);

                    return true;
                });
            });
        }

        $request->session()->put('cfo_maintenance', $marker);
    }
}
