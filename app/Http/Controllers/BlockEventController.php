<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Jobs\SyncEventJob;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\StoreEventRequest;

class BlockEventController extends Controller
{
    public function index()
    {
        $events = Event::where('block_id', auth()->user()->block_id)
            ->latest()
            ->paginate(50);

        return Inertia::render('Block/Events/Index', [
            'events' => $events,
            'block_name' => auth()->user()->block?->name,
        ]);
    }

    public function create()
    {
        return Inertia::render('Block/Events/Create');
    }

    public function store(StoreEventRequest $request)
    {
        $event = Event::create(array_merge(
            $request->validated(),
            [
                'submitted_by_user_id' => auth()->id(),
                'block_id' => auth()->user()->block_id,
                'district_id' => config('app.district_id'),
                'district_name' => config('app.district_name'),
                'unique_hash' => Event::generateUniqueHash(
                    $request->validated('event_name'),
                    $request->validated('event_date'),
                    $request->validated('event_venue'),
                    (int) $request->validated('actual_attendance'),
                    auth()->user()->block_id,
                    $request->validated('event_coordinator_name') ?? ''
                ),
            ]
        ));

        dispatch(new SyncEventJob($event));

        return redirect()->route('block.events.index')
            ->with('success', 'Event submitted successfully.');
    }
}
