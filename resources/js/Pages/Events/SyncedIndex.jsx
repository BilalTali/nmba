import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function SyncedIndex({ events, blocks, filters, totalSynced }) {
    const user = usePage().props.auth.user;
    const isAdmin = user.role === 'admin';
    const [blockId, setBlockId] = useState(filters.block_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.synced-events'), {
            block_id: blockId,
            start_date: startDate,
            end_date: endDate,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleReset = () => {
        setBlockId('');
        setStartDate('');
        setEndDate('');
        router.get(route('admin.synced-events'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-black text-slate-800 tracking-tight">Synced Events</h2>
                        <p className="text-sm font-medium text-slate-500 mt-1">
                            Browse Nasha Mukt J&K community events successfully synced with the official portal.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Total Synced: {totalSynced || 0}
                        </span>
                    </div>
                </div>
            }
        >
            <Head title="Synced Events | Nasha Mukt J&K" />

            <div className="py-8 bg-slate-50 min-h-screen">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                    {/* Beautiful Filter Panel */}
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                        <form onSubmit={handleFilter} className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-xs font-black uppercase tracking-widest text-slate-500 mb-2">
                                    Block
                                </label>
                                <select
                                    value={blockId}
                                    onChange={(e) => setBlockId(e.target.value)}
                                    className="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition focus:border-emerald-500 focus:bg-white focus:outline-none"
                                >
                                    <option value="">All Blocks</option>
                                    {Object.entries(blocks).map(([id, name]) => (
                                        <option key={id} value={id}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                            </div>

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

                    {/* Events List Grid */}
                    {events.data.length === 0 ? (
                        <div className="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
                            <div className="w-16 h-16 rounded-2xl bg-slate-50 flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                <svg className="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-black text-slate-800">No synced events found</h3>
                            <p className="text-sm font-medium text-slate-500 mt-1 max-w-md mx-auto">
                                No events have been synced matching the selected filters.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Tabular List View */}
                            <div className="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-x-auto">
                                <table className="w-full min-w-[1200px] text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b border-slate-200">
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500 w-16">S No.</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500 w-16">ID</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Event Details</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Block & Venue</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Demographics</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Event Date</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Attendance</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Coordinator</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Sync Time</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {events.data.map((event, index) => (
                                            <tr key={event.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="px-6 py-4 text-xs font-black text-slate-400">
                                                    {(events.from || 1) + index}
                                                </td>
                                                <td className="px-6 py-4 text-xs font-black text-slate-400">
                                                    #{event.id}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="font-extrabold text-slate-800 text-sm">{event.event_name}</div>
                                                    <div className="text-[10px] text-emerald-700 font-extrabold mt-0.5 uppercase tracking-wider bg-emerald-50 px-2 py-0.5 rounded-md inline-block">
                                                        {event.event_category ? (Array.isArray(event.event_category) ? event.event_category.join(', ') : JSON.parse(event.event_category).join(', ')) : 'N/A'}
                                                    </div>
                                                    {event.event_category_remark && (
                                                        <div className="text-[11px] text-rose-600 font-bold mt-1 bg-rose-50/50 px-2 py-1 rounded-lg border border-rose-100/50 max-w-xs">
                                                            Remark: {event.event_category_remark}
                                                        </div>
                                                    )}
                                                    
                                                    <div className="flex flex-wrap gap-2 mt-2.5 items-center text-[10px] font-bold tracking-tight text-slate-400">
                                                        {/* Portal Submission ID */}
                                                        {event.submission_id && (
                                                            <span className="inline-flex items-center gap-1 bg-slate-50 text-slate-500 border border-slate-200/60 px-2 py-0.5 rounded-md font-mono" title={`Portal Submission ID: ${event.submission_id}`}>
                                                                <svg className="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                                </svg>
                                                                <span>Sub ID: {event.submission_id.substring(0, 12)}...</span>
                                                            </span>
                                                        )}
                                                        {/* Device ID */}
                                                        {event.device_id && (
                                                            <span className="inline-flex items-center gap-1 bg-slate-50 text-slate-500 border border-slate-200/60 px-2 py-0.5 rounded-md font-mono">
                                                                Dev: {event.device_id.substring(0, 8)}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-extrabold text-slate-800">
                                                        {blocks[event.block_id] || 'Unknown Block'}
                                                    </div>
                                                    <div className="text-xs text-slate-500 mt-0.5 max-w-xs truncate">
                                                        {event.event_venue}
                                                        {[event.ward ? ` (Ward: ${event.ward})` : '', event.village ? ` (Village: ${event.village})` : ''].filter(Boolean).join('')}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex flex-wrap gap-1 max-w-[200px]">
                                                        {event.target_audience && (Array.isArray(event.target_audience) ? event.target_audience : JSON.parse(event.target_audience)).map((aud, idx) => (
                                                            <span key={idx} className="inline-block text-[9px] font-black uppercase tracking-wide bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded">
                                                                {aud}
                                                            </span>
                                                        ))}
                                                    </div>
                                                    <div className="flex flex-wrap gap-1 mt-1.5 max-w-[200px]">
                                                        {event.age_group && (Array.isArray(event.age_group) ? event.age_group : JSON.parse(event.age_group)).map((age, idx) => (
                                                            <span key={idx} className="inline-block text-[9px] font-black uppercase tracking-wide bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded">
                                                                {age}
                                                            </span>
                                                        ))}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-sm font-semibold text-slate-600">
                                                    {new Date(event.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-black text-slate-800">{event.actual_attendance}</div>
                                                    <div className="text-[10px] text-slate-400 font-extrabold uppercase tracking-wide">{event.attendance_range}</div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="text-sm font-extrabold text-slate-800">{event.event_coordinator_name}</div>
                                                    <div className="text-xs text-slate-400 font-bold">{event.event_coordinator_desig}</div>
                                                    {event.event_coordinator_contact_number && (
                                                        <div className="text-[11px] text-slate-500 font-bold mt-1 flex items-center gap-1.5">
                                                            <svg className="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                            </svg>
                                                            {event.event_coordinator_contact_number}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {event.synced_at_is_historical ? (
                                                        <div className="text-xs font-bold text-slate-500 bg-slate-100 border border-slate-200 rounded-xl px-3 py-1.5 inline-flex items-center gap-1.5">
                                                            <svg className="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            Historical
                                                        </div>
                                                    ) : (
                                                        <div className="text-sm font-black text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-xl px-3 py-1.5 inline-block shadow-sm">
                                                            {event.formatted_synced_at}
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Pagination Links */}
                            {events.links && events.links.length > 3 && (
                                <div className="flex justify-center mt-8">
                                    <nav className="flex items-center gap-1 bg-white p-1.5 rounded-xl border border-slate-200 shadow-sm">
                                        {events.links.map((link, key) => {
                                            if (link.url === null) {
                                                return (
                                                    <span
                                                        key={key}
                                                        className="px-3.5 py-2 text-sm font-semibold text-slate-300 pointer-events-none"
                                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                                    />
                                                );
                                            }
                                            return (
                                                <Link
                                                    key={key}
                                                    href={link.url}
                                                    className={`px-3.5 py-2 text-sm font-bold rounded-lg transition-colors ${link.active ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-50'}`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            );
                                        })}
                                    </nav>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
