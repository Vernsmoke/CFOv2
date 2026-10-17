<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiveController extends Controller
{
    public function versions(Request $request)
    {
        $versions = $this->readVersions();

        if ($request->user()->role !== 'Admin') {
            $versions = array_intersect_key($versions, array_flip([
                'year', 'users', 'schedules', 'registrations', 'attendance', 'schedule_changes', 'notifications', 'announcements', 'academic_options', 'settings',
            ]));
        }

        return response()->json($versions)->header('Cache-Control', 'no-store');
    }

    public function adminEvents()
    {
        return response()->stream(function () {
            @set_time_limit(35);
            $versions = $this->readVersions();
            $lastHeartbeat = microtime(true);
            $startedAt = microtime(true);

            echo "retry: 1500\n\n";
            echo "event: versions\ndata: ".json_encode($versions, JSON_THROW_ON_ERROR)."\n\n";
            @ob_flush();
            flush();

            while (! connection_aborted() && microtime(true) - $startedAt < 27) {
                usleep(1_000_000);
                $next = $this->readVersions();

                if ($next !== $versions) {
                    echo "event: versions\ndata: ".json_encode($next, JSON_THROW_ON_ERROR)."\n\n";
                    $versions = $next;
                    $lastHeartbeat = microtime(true);
                    @ob_flush();
                    flush();
                } elseif (microtime(true) - $lastHeartbeat >= 10) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeat = microtime(true);
                    @ob_flush();
                    flush();
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function readVersions(): array
    {
        return [...DB::table('cfo_resource_versions')->orderBy('resource')->pluck('version', 'resource')
            ->map(fn ($version) => (int) $version)->all(), 'year' => now()->year];
    }
}
