import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, AreaChart, Area, CartesianGrid } from 'recharts';

export default function Dashboard({ metrics, recentEvents, recentFailures, autoSyncPaused, statusData = [], eventsByBlock = [], eventsOverTime = [], portalConfig = {} }) {
    const { auth } = usePage().props;
    const isDistrictAdmin = auth.user.role === 'district_admin';

    const [filterDate, setFilterDate] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [settingsError, setSettingsError] = useState('');
    const [settingsSuccess, setSettingsSuccess] = useState('');

    const [healthState, setHealthState] = useState({
        status: 'probing',
        pending_count: 0,
        triggered_sync: false,
        auto_sync_paused: autoSyncPaused,
    });

    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    useEffect(() => {
        const checkPortalHealth = async () => {
            try {
                const response = await fetch(route('events.check-portal'));
                if (!response.ok) throw new Error('Network error');
                const data = await response.json();
                setHealthState(data);
            } catch (error) {
                console.error('Portal health check failed:', error);
                setHealthState({ status: 'offline', auto_sync_paused: false });
            }
        };

        checkPortalHealth();
        const interval = setInterval(checkPortalHealth, 15000);
        return () => clearInterval(interval);
    }, []);

    const { data, setData, post, processing, errors, reset } = useForm({
        portal_url: portalConfig.portal_url || '',
        admin_id: portalConfig.admin_id || '',
        admin_password: portalConfig.admin_password || '',
    });

    useEffect(() => {
        if (portalConfig.portal_url || portalConfig.admin_id || portalConfig.admin_password) {
            setData({
                portal_url: portalConfig.portal_url || '',
                admin_id: portalConfig.admin_id || '',
                admin_password: portalConfig.admin_password || '',
            });
        }
    }, [portalConfig.portal_url, portalConfig.admin_id, portalConfig.admin_password]);

    const submitEnv = (e) => {
        e.preventDefault();
        setSettingsError('');
        setSettingsSuccess('');
        post(route('settings.env'), {
            onSuccess: () => {
                setSettingsSuccess('Credentials updated successfully.');
            },
            onError: (errs) => {
                if (errs.admin_password) setSettingsError(errs.admin_password);
                else if (errs.admin_id) setSettingsError(errs.admin_id);
                else setSettingsError('Failed to update credentials.');
            }
        });
    };

    const toggleSync = (eventId) => post(route('events.toggleSync', eventId));
    const retrySync = (eventId) => post(route('events.retrySync', eventId));
    const toggleAutoSync = () => post(route('events.toggleAutoSync'));
    const forceSyncQueue = () => post(route('events.force-sync'));

    const filteredEvents = recentEvents.filter(event => {
        if (filterDate && !event.event_date.startsWith(filterDate)) return false;
        if (filterStatus && event.sync_status !== filterStatus) return false;
        return true;
    });

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h2 className="text-2xl font-extrabold text-slate-800 tracking-tight">
                            {isDistrictAdmin ? 'District Admin Overview' : 'Event Creator Portal'}
                        </h2>
                        <p className="text-sm font-medium text-slate-500 mt-1">Manage and synchronize your Nasha Mukt Abhiyaan events.</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <a href={route('events.create')} className="px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-500 shadow-lg shadow-emerald-500/20 transition-all text-sm flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" /></svg>
                            Create Event
                        </a>
                        {isDistrictAdmin && (
                            <>
                                <button
                                    onClick={toggleAutoSync}
                                    className={`px-5 py-2.5 rounded-xl text-white font-bold shadow-lg transition-all text-sm flex items-center gap-2 ${healthState.auto_sync_paused ? 'bg-amber-500 hover:bg-amber-400 shadow-amber-500/20' : 'bg-teal-600 hover:bg-teal-500 shadow-teal-500/20'}`}
                                >
                                    {healthState.auto_sync_paused ? 'Resume Auto-Sync' : 'Pause Auto-Sync'}
                                </button>
                                <button
                                    onClick={forceSyncQueue}
                                    disabled={processing}
                                    className="px-5 py-2.5 bg-slate-800 text-white font-bold rounded-xl hover:bg-slate-700 shadow-lg shadow-slate-800/20 transition-all flex items-center gap-2 text-sm disabled:opacity-50"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Force Sync
                                </button>
                                <a
                                    href={route('events.export')}
                                    className="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-500 shadow-lg shadow-indigo-500/20 transition-all flex items-center gap-2 text-sm"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Export CSV
                                </a>
                                <button
                                    onClick={() => setIsSidebarOpen(!isSidebarOpen)}
                                    className="px-5 py-2.5 bg-white text-slate-700 border border-slate-200 font-bold rounded-xl hover:bg-slate-50 shadow-sm transition-all flex items-center gap-2 text-sm"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Settings
                                </button>
                            </>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Dashboard | Nasha Mukt J&K" />

            <div className="flex relative bg-slate-50 min-h-screen">
                <div className={`py-8 flex-1 transition-all duration-300 ${isSidebarOpen ? 'mr-80' : ''}`}>
                    <div className="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-8">

                        {/* Portal Health Banner */}
                        {isDistrictAdmin && (
                            <div className={`px-6 py-5 rounded-2xl border transition-all duration-500 shadow-sm flex flex-col sm:flex-row justify-between items-center gap-4 
                                ${healthState.status === 'online' 
                                    ? (healthState.auto_sync_paused ? 'bg-amber-50/80 border-amber-200' : 'bg-emerald-50/80 border-emerald-200') 
                                    : healthState.status === 'offline' ? 'bg-rose-50/80 border-rose-200' : 'bg-white border-slate-200'}`}>
                                <div className="flex items-center gap-4">
                                    <div className="relative flex h-4 w-4">
                                        <span className={`animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 
                                            ${healthState.status === 'online' ? (healthState.auto_sync_paused ? 'bg-amber-400' : 'bg-emerald-400') : healthState.status === 'offline' ? 'bg-rose-400' : 'bg-slate-400'}`}></span>
                                        <span className={`relative inline-flex rounded-full h-4 w-4 
                                            ${healthState.status === 'online' ? (healthState.auto_sync_paused ? 'bg-amber-500' : 'bg-emerald-500') : healthState.status === 'offline' ? 'bg-rose-500' : 'bg-slate-500'}`}></span>
                                    </div>
                                    <div>
                                        <span className="text-xs font-bold uppercase tracking-widest text-slate-500 mb-1 block">Connection Status</span>
                                        <h4 className="text-base font-extrabold text-slate-800">
                                            {healthState.status === 'probing' && 'Probing NashaMukt J&K Portal...'}
                                            {healthState.status === 'offline' && <span className="text-rose-700">Offline (Timeout or 522 Error). Waiting for recovery.</span>}
                                            {healthState.status === 'online' && (
                                                healthState.auto_sync_paused 
                                                    ? <span className="text-amber-700">Online — Auto-Sync PAUSED ({healthState.pending_count} pending)</span>
                                                    : <span className="text-emerald-700">Online — Systems Operational {healthState.triggered_sync && `(Auto-syncing ${healthState.pending_count} events)`}</span>
                                            )}
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        )}
                    
                        {/* Metrics Section */}
                        {isDistrictAdmin && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                                {[
                                    { label: 'Total Events', value: metrics.total, color: 'text-slate-900', border: 'border-slate-200' },
                                    { label: 'Successfully Synced', value: metrics.synced, color: 'text-emerald-600', border: 'border-emerald-200' },
                                    { label: 'Pending Sync', value: metrics.pending, color: 'text-amber-600', border: 'border-amber-200' },
                                    { label: 'Permanently Failed', value: metrics.failed_perm, color: 'text-rose-600', border: 'border-rose-200' }
                                ].map((stat, i) => (
                                    <div key={i} className={`bg-white p-6 rounded-3xl shadow-sm border ${stat.border} hover:shadow-md transition-shadow`}>
                                        <h3 className="text-slate-500 text-xs font-bold uppercase tracking-wider mb-2">{stat.label}</h3>
                                        <p className={`text-4xl font-black ${stat.color}`}>{stat.value}</p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Charts Section */}
                        {isDistrictAdmin && (
                            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                {/* Sync Status Pie */}
                                <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 col-span-1">
                                    <h3 className="text-slate-800 text-lg font-bold mb-4">Sync Status</h3>
                                    <div className="h-64">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <PieChart>
                                                <Pie data={statusData} dataKey="value" nameKey="name" cx="50%" cy="50%" innerRadius={60} outerRadius={80} paddingAngle={5}>
                                                    {statusData.map((entry, index) => (
                                                        <Cell key={`cell-${index}`} fill={entry.fill} />
                                                    ))}
                                                </Pie>
                                                <Tooltip />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </div>
                                    <div className="flex flex-wrap justify-center gap-4 mt-2">
                                        {statusData.map(entry => (
                                            <div key={entry.name} className="flex items-center gap-1.5 text-sm font-medium text-slate-600">
                                                <div className="w-3 h-3 rounded-full" style={{ backgroundColor: entry.fill }}></div>
                                                {entry.name} ({entry.value})
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                {/* Events Over Time Area */}
                                <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 col-span-1 lg:col-span-2">
                                    <h3 className="text-slate-800 text-lg font-bold mb-4">Events Over Last 30 Days</h3>
                                    <div className="h-64">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <AreaChart data={eventsOverTime} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                                <defs>
                                                    <linearGradient id="colorCount" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="5%" stopColor="#10b981" stopOpacity={0.8}/>
                                                        <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                                                    </linearGradient>
                                                </defs>
                                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                                <XAxis dataKey="date" tickLine={false} axisLine={false} tick={{fill: '#64748b', fontSize: 12}} />
                                                <YAxis tickLine={false} axisLine={false} tick={{fill: '#64748b', fontSize: 12}} />
                                                <Tooltip contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }} />
                                                <Area type="monotone" dataKey="count" stroke="#10b981" strokeWidth={3} fillOpacity={1} fill="url(#colorCount)" />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>

                                {/* Events By Block Bar */}
                                <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 col-span-1 lg:col-span-3">
                                    <h3 className="text-slate-800 text-lg font-bold mb-4">Events by Block</h3>
                                    <div className="h-72">
                                        <ResponsiveContainer width="100%" height="100%">
                                            <BarChart data={eventsByBlock} margin={{ top: 10, right: 10, left: -20, bottom: 20 }}>
                                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="#e2e8f0" />
                                                <XAxis dataKey="name" angle={-45} textAnchor="end" tickLine={false} axisLine={false} tick={{fill: '#64748b', fontSize: 12}} height={60} />
                                                <YAxis tickLine={false} axisLine={false} tick={{fill: '#64748b', fontSize: 12}} />
                                                <Tooltip cursor={{fill: '#f8fafc'}} contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 4px 6px -1px rgb(0 0 0 / 0.1)' }} />
                                                <Bar dataKey="count" fill="#3b82f6" radius={[4, 4, 0, 0]} />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Table Section */}
                        <div className="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                                <h3 className="text-xl font-bold text-slate-800">Recent Events</h3>
                                <div className="flex flex-wrap gap-3 w-full md:w-auto">
                                    <input
                                        type="date"
                                        value={filterDate}
                                        onChange={e => setFilterDate(e.target.value)}
                                        className="rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm w-full sm:w-auto bg-slate-50 py-2.5 px-4"
                                    />
                                    <select
                                        value={filterStatus}
                                        onChange={e => setFilterStatus(e.target.value)}
                                        className="rounded-xl border-slate-200 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm w-full sm:w-auto bg-slate-50 py-2.5 px-4 font-medium text-slate-700"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="synced">Synced</option>
                                        <option value="pending">Pending</option>
                                        <option value="failed_permanently">Failed</option>
                                    </select>
                                </div>
                            </div>

                        {/* Mobile Card View (xs screens) */}
                            <div className="block sm:hidden space-y-3">
                                {filteredEvents.map(event => (
                                    <div key={event.id} className="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                                        <div className="flex justify-between items-start gap-2">
                                            <div className="flex-1 min-w-0">
                                                <p className="font-bold text-slate-900 text-sm truncate">{event.event_name}</p>
                                                <p className="text-xs text-slate-500 mt-0.5 truncate">{event.event_venue}</p>
                                                <p className="text-xs text-slate-400 mt-1">{new Date(event.event_date).toLocaleDateString()}</p>
                                            </div>
                                            <span className={`shrink-0 px-3 py-1 text-xs leading-5 font-bold rounded-full shadow-sm
                                                ${event.sync_status === 'synced' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' :
                                                event.sync_status === 'pending' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                                                'bg-rose-100 text-rose-800 border border-rose-200'}`}>
                                                {event.sync_status.replace('_', ' ').toUpperCase()}
                                            </span>
                                        </div>
                                        {isDistrictAdmin && (
                                            <div className="mt-3 pt-3 border-t border-slate-200 flex gap-3">
                                                <button
                                                    onClick={() => toggleSync(event.id)}
                                                    className="flex-1 py-1.5 text-xs font-bold rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors"
                                                >
                                                    Toggle
                                                </button>
                                                {event.sync_status !== 'synced' && (
                                                    <button
                                                        onClick={() => retrySync(event.id)}
                                                        className="flex-1 py-1.5 text-xs font-bold rounded-lg bg-teal-50 border border-teal-200 text-teal-700 hover:bg-teal-100 transition-colors"
                                                    >
                                                        Retry
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {filteredEvents.length === 0 && (
                                    <div className="py-12 text-center">
                                        <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4 text-slate-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                        </div>
                                        <p className="text-slate-500 font-medium text-sm">No events found matching criteria.</p>
                                    </div>
                                )}
                            </div>

                            {/* Desktop Table View */}
                            <div className="hidden sm:block overflow-x-auto rounded-xl border border-slate-100">
                                <table className="min-w-full divide-y divide-slate-100">
                                    <thead className="bg-slate-50">
                                        <tr>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Date</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Event Name</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Venue</th>
                                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Status</th>
                                            {isDistrictAdmin && <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest">Actions</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-slate-100">
                                        {filteredEvents.map(event => (
                                            <tr key={event.id} className="hover:bg-slate-50/80 transition-colors group">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-700">{new Date(event.event_date).toLocaleDateString()}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-900">{event.event_name}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{event.event_venue}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        className={`px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full cursor-help shadow-sm
                                                        ${event.sync_status === 'synced' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' :
                                                        event.sync_status === 'pending' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                                                        'bg-rose-100 text-rose-800 border border-rose-200'}`}
                                                        title={event.last_error_log || (event.sync_status === 'pending' ? 'Waiting in queue...' : 'Synced')}
                                                    >
                                                        {event.sync_status.replace('_', ' ').toUpperCase()}
                                                    </span>
                                                </td>
                                                {isDistrictAdmin && (
                                                    <td className="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                                        <button onClick={() => toggleSync(event.id)} className="text-slate-500 hover:text-slate-900 mr-4 transition-colors">Toggle</button>
                                                        {event.sync_status !== 'synced' && (
                                                            <button onClick={() => retrySync(event.id)} className="text-teal-600 hover:text-teal-900 transition-colors">Retry</button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                        {filteredEvents.length === 0 && (
                                            <tr>
                                                <td colSpan={isDistrictAdmin ? 5 : 4} className="px-6 py-12 text-center">
                                                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4 text-slate-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                                    </div>
                                                    <p className="text-slate-500 font-medium">No events found matching criteria.</p>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            {/* Mobile Sidebar Backdrop */}
                {isDistrictAdmin && isSidebarOpen && (
                    <div
                        className="fixed inset-0 bg-black/40 z-40 sm:hidden"
                        onClick={() => setIsSidebarOpen(false)}
                        aria-hidden="true"
                    />
                )}

                {/* Settings Sidebar — full-width on mobile, fixed 320px on desktop */}
                {isDistrictAdmin && (
                    <div className={`fixed top-0 right-0 h-full w-full sm:w-80 bg-white shadow-2xl border-l border-slate-200 transform transition-transform duration-300 ease-in-out z-50 pt-20 ${isSidebarOpen ? 'translate-x-0' : 'translate-x-full'}`}>
                        <div className="p-6">
                            <div className="flex justify-between items-center mb-8">
                                <h3 className="text-2xl font-black text-slate-800">Settings</h3>
                                <button onClick={() => setIsSidebarOpen(false)} className="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition-colors">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>

                            <div className="bg-slate-50 p-6 rounded-2xl border border-slate-200">
                                <h4 className="text-xs font-black text-emerald-600 mb-4 uppercase tracking-widest">Portal API Credentials</h4>
                                <form onSubmit={submitEnv} className="space-y-5">
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Portal URL</label>
                                        <input
                                            type="url"
                                            value={data.portal_url}
                                            onChange={e => setData('portal_url', e.target.value)}
                                            className="block w-full rounded-xl border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2.5 px-4"
                                            placeholder="https://example.com"
                                            required
                                        />
                                        {errors.portal_url && <p className="text-rose-500 text-xs mt-1 font-medium">{errors.portal_url}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">API ID</label>
                                        <input
                                            type="text"
                                            value={data.admin_id}
                                            onChange={e => setData('admin_id', e.target.value)}
                                            className="block w-full rounded-xl border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2.5 px-4"
                                            placeholder="Enter API ID"
                                        />
                                        {errors.admin_id && <p className="text-rose-500 text-xs mt-1 font-medium">{errors.admin_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">API Password</label>
                                        <div className="relative">
                                            <input
                                                type={showPassword ? "text" : "password"}
                                                value={data.admin_password}
                                                onChange={e => setData('admin_password', e.target.value)}
                                                className="block w-full rounded-xl border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm py-2.5 px-4 pr-10"
                                                placeholder="Enter Password"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => setShowPassword(!showPassword)}
                                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-emerald-600 focus:outline-none"
                                            >
                                                {showPassword ? (
                                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 015.71-2.29c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0l-3.29-3.29" /></svg>
                                                ) : (
                                                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                )}
                                            </button>
                                        </div>
                                        {errors.admin_password && <p className="text-rose-500 text-xs mt-1 font-medium">{errors.admin_password}</p>}
                                    </div>
                                    {settingsSuccess && (
                                        <p className="text-emerald-700 text-sm font-semibold bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2">{settingsSuccess}</p>
                                    )}
                                    {settingsError && (
                                        <p className="text-rose-600 text-sm font-medium bg-rose-50 border border-rose-200 rounded-xl px-4 py-2">{settingsError}</p>
                                    )}
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-500 shadow-md shadow-emerald-500/20 transition-all disabled:opacity-50"
                                    >
                                        {processing ? 'Saving...' : 'Update Credentials'}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
