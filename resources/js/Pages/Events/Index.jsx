import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ events, blocks, filters }) {
    const [blockId, setBlockId] = useState(filters.block_id || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('events.index'), {
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
        router.get(route('events.index'), {}, {
            preserveState: true,
            replace: true,
        });
    };

    const getStatusBadge = (status) => {
        switch (status) {
            case 'synced':
                return (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
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
                        <h2 className="text-2xl font-black text-slate-800 tracking-tight">Event Directory</h2>
                        <p className="text-sm font-medium text-slate-500 mt-1">
                            Browse and filter all Nasha Mukt J&K community events.
                        </p>
                    </div>
                    <Link
                        href={route('events.create')}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-extrabold text-white shadow-sm hover:bg-emerald-500 transition-all"
                    >
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        Log New Event
                    </Link>
                </div>
            }
        >
            <Head title="Events Directory | Nasha Mukt J&K" />

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
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 002-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-black text-slate-800">No events found</h3>
                            <p className="text-sm font-medium text-slate-500 mt-1 max-w-md mx-auto">
                                Try adjusting your filters, or log a new event using the action button above.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Desktop Tabular List View */}
                            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden hidden md:block">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b border-slate-200">
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500 w-16">ID</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Event Details</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Block & Venue</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Date</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Attendance</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500">Coordinator</th>
                                            <th className="px-6 py-4 text-xs font-black uppercase tracking-widest text-slate-500 text-right">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {events.data.map((event) => (
                                            <tr key={event.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="px-6 py-4 text-xs font-black text-slate-400">
                                                    #{event.id}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="font-extrabold text-slate-800 text-sm">{event.event_name}</div>
                                                    <div className="text-[10px] text-emerald-700 font-extrabold mt-0.5 uppercase tracking-wider bg-emerald-50 px-2 py-0.5 rounded-md inline-block">
                                                        {event.event_category ? (Array.isArray(event.event_category) ? event.event_category.join(', ') : JSON.parse(event.event_category).join(', ')) : 'N/A'}
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
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    {getStatusBadge(event.sync_status)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile responsive item list view */}
                            <div className="space-y-4 md:hidden">
                                {events.data.map((event) => (
                                    <div key={event.id} className="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                                        <div className="flex justify-between items-start gap-4">
                                            <div>
                                                <span className="text-[10px] font-black uppercase tracking-widest text-slate-400 block">
                                                    {blocks[event.block_id] || 'Unknown Block'}
                                                </span>
                                                <h3 className="text-base font-black text-slate-800 mt-0.5">
                                                    {event.event_name}
                                                </h3>
                                            </div>
                                            {getStatusBadge(event.sync_status)}
                                        </div>

                                        <div className="grid grid-cols-2 gap-4 text-xs font-semibold text-slate-500 py-3 border-y border-slate-100">
                                            <div>
                                                <span className="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Date</span>
                                                <span className="text-slate-800">{new Date(event.event_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</span>
                                            </div>
                                            <div>
                                                <span className="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Attendance</span>
                                                <span className="text-slate-800 font-extrabold">{event.actual_attendance} ({event.attendance_range})</span>
                                            </div>
                                        </div>

                                        <div className="text-xs text-slate-500 space-y-1">
                                            <p className="line-clamp-2"><span className="font-bold text-slate-700">Venue:</span> {event.event_venue}</p>
                                            <p><span className="font-bold text-slate-700">Coordinator:</span> {event.event_coordinator_name} ({event.event_coordinator_desig})</p>
                                        </div>
                                    </div>
                                ))}
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
