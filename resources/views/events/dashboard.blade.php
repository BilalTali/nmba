<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

        .premium-dashboard-container {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 10% 20%, rgba(240, 243, 255, 0.5) 0%, rgba(255, 255, 255, 0.7) 90%);
        }

        .premium-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .premium-stat-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 20px;
            box-shadow: 0 10px 30px -10px rgba(102, 126, 234, 0.08);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px -8px rgba(102, 126, 234, 0.15);
        }

        .premium-table-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(102, 126, 234, 0.1);
        }

        .premium-button-secondary {
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-button-secondary:hover {
            background-color: rgba(99, 102, 241, 0.08);
            transform: translateY(-1px);
        }

        .premium-button-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .premium-button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .anomaly-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(244, 63, 94, 0.15);
            border-radius: 16px;
            box-shadow: 0 10px 25px -10px rgba(244, 63, 94, 0.05);
            transition: all 0.3s ease;
        }

        .anomaly-card:hover {
            box-shadow: 0 15px 30px -8px rgba(244, 63, 94, 0.12);
        }

        .glow-green { border-left: 4px solid #10b981; }
        .glow-yellow { border-left: 4px solid #f59e0b; }
        .glow-blue { border-left: 4px solid #3b82f6; }
        .glow-indigo { border-left: 4px solid #6366f1; }
        .glow-orange { border-left: 4px solid #f97316; }
        .glow-rose { border-left: 4px solid #f43f5e; }
    </style>

    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 py-2">
            <div>
                <h2 class="font-extrabold text-2xl text-slate-800 tracking-tight leading-tight">
                    NMBA Portal Sync Dashboard
                </h2>
                <p class="text-sm font-medium text-slate-500 mt-1">Real-time status updates of localized events enqueued for NashaMukt J&K Portal sync.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto mt-4 sm:mt-0">
                <!-- Toggle Auto-Sync Button -->
                <form action="{{ route('events.toggle-auto-sync') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    @if($autoSyncPaused)
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 border border-emerald-200 text-sm font-bold text-emerald-700 bg-emerald-50/50 hover:bg-emerald-100 premium-button-secondary shadow-sm transition" title="Resume Automatic Background Synchronization">
                            <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Resume Auto-Sync
                        </button>
                    @else
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 border border-amber-200 text-sm font-bold text-amber-700 bg-amber-50/50 hover:bg-amber-100 premium-button-secondary shadow-sm transition" title="Pause Automatic Background Synchronization">
                            <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Pause Auto-Sync
                        </button>
                    @endif
                </form>

                <!-- Force Sync Button -->
                <form action="{{ route('events.force-sync') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-3 border border-indigo-200 text-sm font-bold text-indigo-700 bg-indigo-50/50 hover:bg-indigo-100 premium-button-secondary shadow-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5 animate-spin-hover" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Force Sync Queue
                    </button>
                </form>

                <!-- Log Event Button -->
                <a href="{{ route('events.create') }}" class="w-full sm:w-auto inline-flex justify-center items-center px-5 py-3 text-sm font-bold text-white bg-indigo-600 premium-button-primary shadow-md transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Log New Event
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12 premium-dashboard-container min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-8 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl shadow-sm flex items-center gap-3 animate-bounce">
                    <div class="p-1 bg-emerald-500 text-white rounded-full">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-emerald-800">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            <!-- Dynamic Portal Health & Sync Orchestration Banner -->
            <div id="portal-health-container" class="mb-8 p-4 rounded-2xl border transition-all duration-500 ease-in-out hidden shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50 border-slate-200">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3" id="portal-health-dot-wrapper">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-slate-400" id="portal-health-dot-ping"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-slate-500" id="portal-health-dot"></span>
                    </span>
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Portal Connection Status</span>
                        <h4 class="text-sm font-extrabold text-slate-700 leading-tight mt-0.5" id="portal-health-status-text">Probing NashaMukt J&K Portal...</h4>
                    </div>
                </div>
                <div class="flex items-center gap-2" id="portal-health-sync-indicator" style="display: none;">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-100 animate-pulse">
                        <svg class="animate-spin -ml-1 mr-1.5 h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Auto-Syncing Pending Events
                    </span>
                </div>
            </div>

            <!-- Top Level Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                <div class="premium-stat-card p-5 glow-indigo">
                    <div class="text-xs font-bold text-indigo-500 uppercase tracking-wider">Total Records</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['total'] }}</div>
                </div>
                
                <div class="premium-stat-card p-5 glow-yellow">
                    <div class="text-xs font-bold text-amber-500 uppercase tracking-wider">Pending Queue</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['pending'] }}</div>
                </div>
                
                <div class="premium-stat-card p-5 glow-blue">
                    <div class="text-xs font-bold text-blue-500 uppercase tracking-wider">Syncing Now</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['syncing'] }}</div>
                </div>
                
                <div class="premium-stat-card p-5 glow-green">
                    <div class="text-xs font-bold text-emerald-500 uppercase tracking-wider">Successfully Synced</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['synced'] }}</div>
                </div>
                
                <div class="premium-stat-card p-5 glow-orange">
                    <div class="text-xs font-bold text-orange-500 uppercase tracking-wider">Retrying</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['transient'] }}</div>
                </div>
                
                <div class="premium-stat-card p-5 glow-rose">
                    <div class="text-xs font-bold text-rose-500 uppercase tracking-wider">Permanent Errors</div>
                    <div class="mt-2 text-4xl font-extrabold text-slate-800">{{ $metrics['failed_perm'] }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <!-- Recent Records Table Card -->
                <div class="premium-table-card overflow-hidden">
                    <div class="p-6 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <span class="p-1 bg-indigo-50 text-indigo-600 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg></span>
                            Recent Submissions
                        </h3>
                        <span class="text-xs text-slate-400 font-semibold">Showing up to 20 logs</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50/60">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">ID / Date</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Event Details</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Sync Status</th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Attempts</th>
                                    <th scope="col" class="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white/40">
                                @forelse($recentEvents as $event)
                                    <tr class="hover:bg-slate-50/30 transition">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                            <div class="font-extrabold text-slate-800">#{{ $event->id }}</div>
                                            <div class="text-xs text-slate-400 mt-0.5">{{ $event->created_at->format('Y-m-d H:i') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="font-bold text-slate-800 truncate w-48" title="{{ $event->event_name }}">{{ $event->event_name }}</div>
                                            <div class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                                {{ $event->district_name }} ({{ $event->block_id }})
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($event->sync_status === 'synced')
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">Synced</span>
                                            @elseif($event->sync_status === 'syncing')
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-100 animate-pulse">Syncing</span>
                                            @elseif($event->sync_status === 'failed_permanently')
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-rose-50 text-rose-700 border border-rose-100">Permanent Failure</span>
                                            @else
                                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-amber-50 text-amber-700 border border-amber-100">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 text-center font-bold">
                                            @if($event->sync_attempts === -1)
                                                <span class="inline-flex items-center gap-1 text-xs text-amber-600 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100" title="Manually Locked from Auto-Sync">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                    Locked
                                                </span>
                                            @else
                                                {{ $event->sync_attempts }}
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold">
                                            <form action="{{ route('events.toggle', $event) }}" method="POST" class="inline-block relative z-10" onsubmit="return confirm('Are you sure you want to toggle the status of Event #{{ $event->id }}?');">
                                                @csrf
                                                @if($event->sync_status === 'synced')
                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-rose-50 text-emerald-700 hover:text-rose-700 rounded-xl border border-emerald-200 hover:border-rose-200 shadow-sm transition-all duration-300 font-bold text-xs cursor-pointer relative z-10 select-none" title="Click to reset to Pending">
                                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                                        Synced (Toggle)
                                                    </button>
                                                @elseif($event->sync_status === 'failed_permanently')
                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 hover:bg-amber-50 text-rose-700 hover:text-amber-700 rounded-xl border border-rose-200 hover:border-amber-200 shadow-sm transition-all duration-300 font-bold text-xs cursor-pointer relative z-10 select-none" title="Click to reset to Pending">
                                                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                                        Failed (Toggle)
                                                    </button>
                                                @else
                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-50 hover:bg-emerald-50 text-amber-700 hover:text-emerald-700 rounded-xl border border-amber-200 hover:border-emerald-200 shadow-sm transition-all duration-300 font-bold text-xs cursor-pointer relative z-10 select-none" title="Click to mark as Synced">
                                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                                        Pending (Toggle)
                                                    </button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 whitespace-nowrap text-sm text-slate-400 text-center italic font-medium">
                                            No records found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Failures Tracking Card -->
                <div class="premium-table-card overflow-hidden">
                    <div class="p-6 bg-rose-50/50 border-b border-rose-100/50 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-rose-800 flex items-center gap-2">
                            <span class="p-1 bg-rose-100 text-rose-700 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg></span>
                            Sync Failures & Anomalies
                        </h3>
                        <span class="text-xs text-rose-500 font-bold">Dead-Letter Queue Logs</span>
                    </div>
                    
                    <div class="p-6 space-y-4 max-h-[600px] overflow-y-auto">
                        @forelse($recentFailures as $failure)
                            <div class="anomaly-card p-5 border-l-4 {{ $failure->sync_status === 'failed_permanently' ? 'border-rose-500' : 'border-amber-400' }}">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-xs font-bold text-slate-400">EVENT ID</span>
                                        <h4 class="text-sm font-extrabold text-slate-800 mt-0.5">#{{ $failure->id }} — {{ $failure->event_name }}</h4>
                                    </div>
                                    <span class="text-xs font-semibold text-slate-400">{{ $failure->last_attempt_at?->diffForHumans() ?? 'Unknown' }}</span>
                                </div>
                                <div class="mt-3 text-xs text-rose-700 bg-rose-50/30 border border-rose-100/30 p-3 rounded-xl font-mono overflow-x-auto whitespace-pre-wrap leading-relaxed shadow-inner">
                                    {{ $failure->last_error_log }}
                                </div>
                                <div class="mt-3 flex items-center justify-between text-xs font-bold text-slate-500">
                                    <span class="flex items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full {{ $failure->sync_status === 'failed_permanently' ? 'bg-rose-500' : 'bg-amber-400' }}"></span>
                                        <span class="uppercase {{ $failure->sync_status === 'failed_permanently' ? 'text-rose-600' : 'text-amber-500' }}">{{ $failure->sync_status }}</span>
                                    </span>
                                    <div class="flex items-center gap-3">
                                        <span>Attempts: <span class="text-slate-800">{{ $failure->sync_attempts }} / 10</span></span>
                                        <form action="{{ route('events.retry', $failure) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to retry Event #{{ $failure->id }}?');">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-700 rounded-md transition font-bold" title="Retry Synchronizing">
                                                Retry Sync
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="p-4 bg-emerald-50 text-emerald-600 rounded-full mb-3">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <h4 class="text-md font-bold text-slate-700">All Systems Operational</h4>
                                <p class="text-xs text-slate-400 mt-1 max-w-xs">No recent validation failures or sync engine connectivity errors logged.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const portalHealthContainer = document.getElementById('portal-health-container');
            const portalHealthDot = document.getElementById('portal-health-dot');
            const portalHealthDotPing = document.getElementById('portal-health-dot-ping');
            const portalHealthStatusText = document.getElementById('portal-health-status-text');
            const portalHealthSyncIndicator = document.getElementById('portal-health-sync-indicator');

            // Tracking the last status to prevent redundant DOM updates and bounce effects
            let lastStatus = null;

            async function checkPortalHealth() {
                try {
                    const response = await fetch("{{ route('events.check-portal') }}");
                    if (!response.ok) throw new Error('Network error');
                    
                    const data = await response.json();
                    
                    portalHealthContainer.classList.remove('hidden');

                    if (data.status === 'online') {
                        if (data.auto_sync_paused) {
                            // Online but Paused State
                            portalHealthContainer.className = 'mb-8 p-4 rounded-2xl border transition-all duration-500 ease-in-out shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-amber-50/50 border-amber-200';
                            portalHealthDot.className = 'relative inline-flex rounded-full h-3 w-3 bg-amber-500';
                            portalHealthDotPing.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-amber-400';
                            portalHealthStatusText.innerHTML = `Portal Server is <strong class="text-amber-700">Online</strong> — Auto-Sync is <strong class="text-amber-700">PAUSED</strong> (<span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded-md font-bold text-xs">${data.pending_count} pending event(s)</span> are waiting in queue).`;
                            portalHealthSyncIndicator.style.display = 'none';
                            lastStatus = 'online_paused';
                        } else {
                            // Online State
                            portalHealthContainer.className = 'mb-8 p-4 rounded-2xl border transition-all duration-500 ease-in-out shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-emerald-50/50 border-emerald-100';
                            portalHealthDot.className = 'relative inline-flex rounded-full h-3 w-3 bg-emerald-500';
                            portalHealthDotPing.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-emerald-400';
                            
                            if (data.triggered_sync) {
                                portalHealthStatusText.innerHTML = `Portal Server is <strong class="text-emerald-700">Online</strong> — Successfully initiated auto-sync of <span class="bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-md font-bold text-xs">${data.pending_count} pending event(s)</span>!`;
                                portalHealthSyncIndicator.style.display = 'flex';
                                
                                // If sync was triggered, reload dashboard in 5 seconds to show fresh status
                                if (lastStatus !== 'online_sync') {
                                    lastStatus = 'online_sync';
                                    setTimeout(() => window.location.reload(), 5000);
                                }
                            } else {
                                portalHealthStatusText.innerHTML = `Portal Server is <strong class="text-emerald-700">Online</strong> — All local events are fully up to date.`;
                                portalHealthSyncIndicator.style.display = 'none';
                                lastStatus = 'online_idle';
                            }
                        }
                    } else {
                        // Offline State
                        portalHealthContainer.className = 'mb-8 p-4 rounded-2xl border transition-all duration-500 ease-in-out shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-rose-50/40 border-rose-100';
                        portalHealthDot.className = 'relative inline-flex rounded-full h-3 w-3 bg-rose-500';
                        portalHealthDotPing.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-rose-400';
                        portalHealthStatusText.innerHTML = `Portal Server is <strong class="text-rose-700">Offline</strong> (Timeout or 522 Error). Waiting for it to come online to auto-sync...`;
                        portalHealthSyncIndicator.style.display = 'none';
                        lastStatus = 'offline';
                    }
                } catch (error) {
                    console.error('Portal health check failed:', error);
                    // Connection Error state
                    portalHealthContainer.classList.remove('hidden');
                    portalHealthContainer.className = 'mb-8 p-4 rounded-2xl border transition-all duration-500 ease-in-out shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-slate-50 border-slate-200';
                    portalHealthDot.className = 'relative inline-flex rounded-full h-3 w-3 bg-slate-500';
                    portalHealthDotPing.className = 'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-slate-400';
                    portalHealthStatusText.innerHTML = `Local connection issue — unable to reach sync agent backend.`;
                    portalHealthSyncIndicator.style.display = 'none';
                    lastStatus = 'connection_error';
                }
            }

            // Run check immediately on load
            checkPortalHealth();

            // Run check every 15 seconds to catch online status without delay
            setInterval(checkPortalHealth, 15000);
        });
    </script>
</x-app-layout>
