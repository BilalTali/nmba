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
        $validated = $request->validated();
        $coordinatorName = $validated['event_coordinator_name'] ?? '';

        // 1. Concurrency Lock: Prevents double-submission from rapid duplicate clicks by block workers.
        $lockHash = md5(
            strtolower(trim($validated['event_name'])) . '|' .
            strtolower(trim($validated['event_date'])) . '|' .
            strtolower(trim($validated['event_venue'])) . '|' .
            $validated['actual_attendance'] . '|' .
            (auth()->user()->block_id) . '|' .
            strtolower(trim($coordinatorName))
        );

        $lockKey = "event_submit_lock_" . $lockHash;
        if (\Illuminate\Support\Facades\Cache::has($lockKey)) {
            return redirect()->back()->withInput()
                ->withErrors(['duplicate' => 'A submission for this event is already in progress. Please wait a moment.']);
        }
        \Illuminate\Support\Facades\Cache::put($lockKey, true, 10); // 10-second lock to absorb double clicks

        // Process uploaded photos
        $photoPaths = [];
        try {
            $photoPaths = $this->imageService->optimizeBatch($request->file('photo'));
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Cache::forget($lockKey); // Release lock immediately on exception
            Log::channel('sync')->warning('Image optimization failure (block worker).', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['photo' => 'Image processing failed: ' . $e->getMessage()]);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $event = Event::create(array_merge(
                $validated,
                [
                    'submitted_by_user_id' => auth()->id(),
                    'block_id'             => auth()->user()->block_id,
                    'district_id'          => config('app.district_id'),
                    'district_name'        => config('app.district_name'),
                    'photo_paths'          => $photoPaths,
                    'sync_status'          => 'pending',
                    'unique_hash'          => Event::generateUniqueHash(
                        $validated['event_name'],
                        $validated['event_date'],
                        $validated['event_venue'],
                        (int) $validated['actual_attendance'],
                        auth()->user()->block_id,
                        $coordinatorName
                    ),
                ]
            ));

            \Illuminate\Support\Facades\DB::commit();

            // Evict dashboard metrics cache on new event submission.
            \Illuminate\Support\Facades\Cache::forget('dashboard_metrics_counts');

        } catch (Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Cache::forget($lockKey);
            foreach ($photoPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            Log::channel('sync')->error('Transaction abort during block worker store.', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['photo' => 'Submission failed: ' . $e->getMessage()]);
        }

        dispatch(new SyncEventJob($event));

        $blockName      = auth()->user()->block?->name ?? 'your block';
        $successMessage = "Event submitted successfully! <br><span class='text-emerald-900 font-bold'>Recorded for Jurisdiction: {$blockName}</span>";

        return redirect()->route('block.events.index')
            ->with('success', $successMessage);
    }
}
