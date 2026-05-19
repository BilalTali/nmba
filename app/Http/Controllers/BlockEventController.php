<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Jobs\SyncEventJob;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Http\Requests\StoreEventRequest;
use Exception;

class BlockEventController extends Controller
{
    protected ImageOptimizationService $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index()
    {
        $events = Event::where('block_id', auth()->user()->block_id)
            ->latest()
            ->paginate(50);

        return Inertia::render('Block/Events/Index', [
            'events'     => $events,
            'block_name' => auth()->user()->block?->name,
        ]);
    }

    public function create()
    {
        return Inertia::render('Block/Events/Create');
    }

    public function store(StoreEventRequest $request)
    {
        // Process uploaded photos
        $photoPaths = [];
        try {
            $photoPaths = $this->imageService->optimizeBatch($request->file('photo'));
        } catch (Exception $e) {
            Log::channel('sync')->warning('Image optimization failure (block worker).', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['photo' => 'Image processing failed: ' . $e->getMessage()]);
        }

        $event = Event::create(array_merge(
            $request->validated(),
            [
                'submitted_by_user_id' => auth()->id(),
                'block_id'             => auth()->user()->block_id,
                'district_id'          => config('app.district_id'),
                'district_name'        => config('app.district_name'),
                'photo_paths'          => $photoPaths,
                'sync_status'          => 'pending',
                'unique_hash'          => Event::generateUniqueHash(
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

        $blockName      = auth()->user()->block?->name ?? 'your block';
        $successMessage = "Event submitted successfully! <br><span class='text-emerald-900 font-bold'>Recorded for Jurisdiction: {$blockName}</span>";

        return redirect()->route('block.events.index')
            ->with('success', $successMessage);
    }
}
