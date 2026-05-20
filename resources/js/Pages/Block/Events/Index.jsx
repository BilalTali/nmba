import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ events, block_name, filters = {}, uploadedToday = 0, totalUploaded = 0 }) {
    const user = usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('block.events.index'), {
            start_date: startDate,
            end_date: endDate,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setStartDate('');
        setEndDate('');
        router.get(route('block.events.index'), {}, {
            preserveState: true,
            replace: true,
        });
    };
    const getStatusBadge = (status) => {
        switch (status) {
            case 'synced':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Synced
                    </span>
                );
            case 'syncing':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-spin"></span>
                        Syncing
                    </span>
                );
            case 'failed_permanently':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                        Failed
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        Pending
                    </span>
                );
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-black text-slate-800 tracking-tight">My Events: {block_name}</h2>
                        <p className="text-sm font-medium text-slate-500 mt-1">
                            Browse your recently logged events for this block.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('block.events.create')}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-500 transition-all"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            Log New Event
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`My Events - ${block_name} | Nasha Mukt J&K`} />

            <div className="py-8 bg-slate-50 min-h-screen">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* Stat Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center justify-between">
                            <div>
                                <p className="text-sm font-black uppercase tracking-widest text-slate-500">Events Uploaded Today</p>
                                <h3 className="text-3xl font-black text-slate-800 mt-1">{uploadedToday || 0}</h3>
                            </div>
                            <div className="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center">
                                <svg className="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                                </svg>
                            </div>
                        </div>
                        <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex items-center justify-between">
                            <div>
                                <p className="text-sm font-black uppercase tracking-widest text-slate-500">Total Events Uploaded</p>
                                <h3 className="text-3xl font-black text-slate-800 mt-1">{totalUploaded || 0}</h3>
                            </div>
                            <div className="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center">
                                <svg className="w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    {/* Beautiful Filter Panel */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label className="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">
                                    Start Date
                                </label>
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">
                                    End Date
                                </label>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                />
                            </div>

                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    className="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white px-4 py-2.5 text-sm font-extrabold shadow-sm transition-all"
                                >
                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    Filter
                                </button>
                                <button
                                    type="button"
                                    onClick={handleReset}
                                    className="inline-flex items-center justify-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2.5 text-sm font-extrabold transition-all"
                                >
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>

                    <div className="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[800px] text-left border-collapse">
                                <thead>
                                    <tr className="bg-slate-50 border-b border-slate-200">
                                        <th className="py-4 px-6 text-xs font-black text-slate-500 uppercase tracking-wider">Event & Location</th>
                                        <th className="py-4 px-6 text-xs font-black text-slate-500 uppercase tracking-wider">Date & Time Logged</th>
                                        <th className="py-4 px-6 text-xs font-black text-slate-500 uppercase tracking-wider">Attendance</th>
                                        <th className="py-4 px-6 text-xs font-black text-slate-500 uppercase tracking-wider">Sync Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {events.data.length > 0 ? (
                                        events.data.map((event) => (
                                            <tr key={event.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="py-4 px-6 align-top">
                                                    <div className="font-bold text-slate-800 text-sm mb-0.5">{event.event_name}</div>
                                                    <div className="text-xs text-slate-500 flex items-center gap-1.5 mt-1.5">
                                                        <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        {event.event_venue}
                                                    </div>

                                                    <div className="flex flex-wrap gap-1.5 mt-2.5 items-center text-[10px] font-bold tracking-tight text-slate-500">
                                                        {/* Upload Datetime */}
                                                        <span className="inline-flex items-center gap-1 bg-slate-50 text-slate-600 border border-slate-200/60 px-2 py-0.5 rounded-md hover:bg-slate-100 transition-colors" title="Upload Datetime">
                                                            <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span>Log: {new Date(event.created_at).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' })}, {new Date(event.created_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}</span>
                                                        </span>

                                                        {/* Device ID */}
                                                        {isAdmin && (
                                                            <span className="inline-flex items-center gap-1 bg-slate-50 text-slate-600 border border-slate-200/60 px-2 py-0.5 rounded-md font-mono" title={`Device ID: ${event.device_id || 'Legacy'}`}>
                                                                <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                                </svg>
                                                                <span>Dev: {event.device_id ? event.device_id.substring(0, 8) : 'Legacy'}</span>
                                                            </span>
                                                        )}

                                                        {/* Uploader IP */}
                                                        {isAdmin && (
                                                            <span className="inline-flex items-center gap-1 bg-slate-50 text-slate-600 border border-slate-200/60 px-2 py-0.5 rounded-md font-mono" title={`Uploader IP: ${event.uploader_ip || 'Legacy'}`}>
                                                                <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                                                </svg>
                                                                <span>IP: {event.uploader_ip || 'Legacy'}</span>
                                                            </span>
                                                        )}

                                                        {/* Sync Timeline Badges */}
                                                        {isAdmin && (
                                                            <>
                                                                {event.sync_status === 'synced' && event.synced_at ? (
                                                                    <span className="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200/60 px-2 py-0.5 rounded-md" title="Synchronization Timestamp">
                                                                        <svg className="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                                                        </svg>
                                                                        <span>Sync: {new Date(event.synced_at).toLocaleDateString('en-IN', { month: 'short', day: 'numeric' })}, {new Date(event.synced_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}</span>
                                                                    </span>
                                                                ) : event.sync_status === 'syncing' ? (
                                                                    <span className="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200/60 px-2 py-0.5 rounded-md" title="Sync Status">
                                                                        <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-spin"></span>
                                                                        <span>Syncing...</span>
                                                                    </span>
                                                                ) : event.sync_status === 'failed_permanently' ? (
                                                                    <span className="inline-flex items-center gap-1 bg-rose-50 text-rose-700 border border-rose-200/60 px-2 py-0.5 rounded-md" title="Sync Status">
                                                                        <span className="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                                        <span>Sync Failed</span>
                                                                    </span>
                                                                ) : (
                                                                    <span className="inline-flex items-center gap-1 bg-amber-50 text-amber-700 border border-amber-200/60 px-2 py-0.5 rounded-md" title="Sync Status">
                                                                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                                        <span>Sync Pending</span>
                                                                    </span>
                                                                )}
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-4 px-6 align-top">
                                                    <div className="text-sm font-semibold text-slate-700">{event.event_date}</div>
                                                    <div className="text-xs text-slate-500 mt-1">Logged: {new Date(event.created_at).toLocaleDateString()}</div>
                                                </td>
                                                <td className="py-4 px-6 align-top">
                                                    <div className="text-sm font-bold text-slate-700">{event.actual_attendance}</div>
                                                    <div className="text-xs text-slate-500 mt-1">people</div>
                                                </td>
                                                <td className="py-4 px-6 align-top">
                                                    {getStatusBadge(event.sync_status)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="4" className="py-12 px-6 text-center">
                                                <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4">
                                                    <svg className="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                    </svg>
                                                </div>
                                                <p className="text-slate-500 font-medium">No events found for this block.</p>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {events.links && events.links.length > 3 && (
                            <div className="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-center">
                                <div className="flex gap-1">
                                    {events.links.map((link, i) => (
                                        <Link
                                            key={i}
                                            href={link.url || '#'}
                                            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                                                link.active ? 'bg-emerald-600 text-white shadow-sm' : 
                                                link.url ? 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200' : 'bg-transparent text-slate-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
