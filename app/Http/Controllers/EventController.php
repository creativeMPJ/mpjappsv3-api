<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReport;
use App\Models\Profile;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    private function assertRegional()
    {
        $user    = auth()->user();
        $profile = Profile::find($user->id);
        if (!$profile || $profile->role !== 'admin_regional' || !$profile->region_id) {
            abort(403, 'Forbidden');
        }
        return $profile->region_id;
    }

    public function index(Request $request)
    {
        $events = Event::orderBy('date', 'desc')->get();
        return response()->json($events);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'date'        => 'required|date',
            'location'    => 'nullable|string',
            'status'      => 'nullable|string',
        ]);

        $event = Event::create(array_merge(['id' => Str::uuid()], $data, [
            'status' => $data['status'] ?? 'upcoming',
        ]));

        return response()->json($event);
    }

    public function reports(Request $request, string $id)
    {
        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'Event not found'], 404);

        $regions = Region::with(['cities'])->orderBy('name')->get();
        $reports = EventReport::where('event_id', $id)->get()->keyBy('region_id');

        $result = $regions->map(fn($r) => [
            'regionId'   => $r->id,
            'regionName' => $r->name,
            'status'     => isset($reports[$r->id]) ? 'Submitted' : 'Pending',
            'report'     => $reports[$r->id] ?? null,
        ]);

        return response()->json(['event' => $event, 'reports' => $result]);
    }

    public function submitReport(Request $request, string $id)
    {
        $data = $request->validate([
            'regionId'          => 'required|string',
            'participationCount' => 'required|integer|min:0',
            'notes'             => 'nullable|string',
            'photoUrl'          => 'nullable|string',
        ]);

        $existing = EventReport::where('event_id', $id)->where('region_id', $data['regionId'])->first();

        if ($existing) {
            $existing->update([
                'participation_count' => $data['participationCount'],
                'notes'               => $data['notes'] ?? null,
                'photo_url'           => $data['photoUrl'] ?? null,
                'submitted_at'        => now(),
            ]);
            return response()->json($existing);
        }

        $report = EventReport::create([
            'id'                  => Str::uuid(),
            'event_id'            => $id,
            'region_id'           => $data['regionId'],
            'participation_count' => $data['participationCount'],
            'notes'               => $data['notes'] ?? null,
            'photo_url'           => $data['photoUrl'] ?? null,
        ]);

        return response()->json($report);
    }

    // ── Regional endpoints ──

    public function regionalIndex(Request $request)
    {
        $regionId = $this->assertRegional();

        $events = Event::orderBy('date', 'desc')->get();

        $myReports = EventReport::where('region_id', $regionId)
            ->get(['id', 'event_id', 'participation_count', 'notes', 'submitted_at'])
            ->keyBy('event_id');

        $reportCounts = EventReport::selectRaw('event_id, count(*) as cnt')
            ->groupBy('event_id')
            ->pluck('cnt', 'event_id');

        return response()->json([
            'events' => $events->map(fn($e) => [
                'id'           => $e->id,
                'name'         => $e->name,
                'description'  => $e->description,
                'date'         => $e->date,
                'location'     => $e->location,
                'status'       => $e->status,
                'created_at'   => $e->created_at,
                'report_count' => $reportCounts[$e->id] ?? 0,
                'my_report'    => $myReports[$e->id] ?? null,
            ]),
        ]);
    }

    public function regionalStore(Request $request)
    {
        $this->assertRegional();

        $data = $request->validate([
            'name'        => 'required|string',
            'description' => 'nullable|string',
            'date'        => 'required|date',
            'location'    => 'nullable|string',
        ]);

        $event = Event::create(array_merge(['id' => Str::uuid(), 'status' => 'upcoming'], $data));

        return response()->json(['success' => true, 'event' => $event]);
    }

    public function regionalUpdate(Request $request, string $id)
    {
        $this->assertRegional();

        $data = $request->validate([
            'name'        => 'nullable|string',
            'description' => 'nullable|string',
            'date'        => 'nullable|date',
            'location'    => 'nullable|string',
            'status'      => 'nullable|string',
        ]);

        $event = Event::find($id);
        if (!$event) return response()->json(['message' => 'ID tidak valid'], 400);

        $event->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'event' => $event]);
    }

    public function regionalSubmitReport(Request $request, string $id)
    {
        $regionId = $this->assertRegional();

        $data = $request->validate([
            'participationCount' => 'required|integer|min:0',
            'notes'              => 'nullable|string',
        ]);

        $existing = EventReport::where('event_id', $id)->where('region_id', $regionId)->first();

        if ($existing) {
            $existing->update([
                'participation_count' => $data['participationCount'],
                'notes'               => $data['notes'] ?? null,
                'submitted_at'        => now(),
            ]);
            return response()->json(['success' => true, 'report' => $existing]);
        }

        $report = EventReport::create([
            'id'                  => Str::uuid(),
            'event_id'            => $id,
            'region_id'           => $regionId,
            'participation_count' => $data['participationCount'],
            'notes'               => $data['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'report' => $report]);
    }
}
