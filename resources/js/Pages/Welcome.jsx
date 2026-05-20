import { Head, Link } from '@inertiajs/react';
import { PieChart, Pie, Cell, AreaChart, Area, BarChart, Bar, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, Legend } from 'recharts';

export default function Welcome({ auth, liveMetrics = [], eventsOverTime = [], eventsByBlock = [] }) {
    return (
        <>
            <Head title="Nasha Mukt J&K Abhiyaan" />
            <div className="min-h-screen selection:bg-emerald-500 selection:text-white font-sans text-slate-900 relative">
                {/* Hero Background with Gradient & Pattern */}
                <div className="fixed inset-0 z-0 bg-gradient-to-br from-emerald-900 via-emerald-800 to-teal-900">
                    <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")' }}></div>
                </div>

                <div className="relative z-10 flex flex-col min-h-screen">
                    {/* Header Navbar */}
                    <header className="w-full py-6 px-4 sm:px-12 flex flex-col sm:flex-row justify-between items-center gap-4 backdrop-blur-md bg-white/5 border-b border-white/10">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                                <span className="text-emerald-700 font-bold text-xl">JK</span>
                            </div>
                            <div>
                                <h1 className="text-xl font-bold text-white tracking-tight">Nasha Mukt J&K</h1>
                                <p className="text-emerald-200 text-xs font-medium tracking-wide">Govt. of Jammu & Kashmir</p>
                            </div>
                        </div>
                        <nav className="flex gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="px-5 py-2.5 rounded-full bg-white text-emerald-900 font-bold hover:bg-emerald-50 transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={route('login')}
                                    className="px-6 py-2.5 rounded-full bg-emerald-500 text-white font-bold hover:bg-emerald-400 transition shadow-lg shadow-emerald-500/30 hover:shadow-emerald-500/50 transform hover:-translate-y-0.5 border border-emerald-400/50"
                                >
                                    Portal Login
                                </Link>
                            )}
                        </nav>
                    </header>

                    {/* Main Content */}
                    <main className="flex-grow flex flex-col items-center justify-center px-6 py-12 text-center">
                        <div className="max-w-4xl mx-auto space-y-8 animate-fade-in-up">
                            <span className="inline-block py-1.5 px-4 rounded-full bg-emerald-800/50 border border-emerald-500/30 text-emerald-200 text-sm font-semibold tracking-wide backdrop-blur-sm">
                                A DRUG-FREE SOCIETY INITIATIVE
                            </span>
                            
                            <h2 className="text-5xl md:text-7xl font-extrabold text-white leading-tight tracking-tight">
                                Nasha Mukt <br className="hidden md:block"/>
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 to-teal-100">Jammu Kashmir Abhiyaan</span>
                            </h2>
                            
                            <p className="text-lg md:text-xl text-emerald-100/90 max-w-2xl mx-auto leading-relaxed">
                                Join the movement to eradicate drug abuse from our beautiful Union Territory. 
                                Together, we build an environment of awareness, prevention, and rehabilitation.
                            </p>

                            <div className="flex flex-col sm:flex-row justify-center gap-4 pt-4">
                                <a href="#pledge" className="px-8 py-4 rounded-full bg-white text-emerald-900 font-bold text-lg hover:bg-gray-100 transition shadow-xl transform hover:scale-105">
                                    Take the E-Pledge
                                </a>
                                <a href="#stats" className="px-8 py-4 rounded-full bg-emerald-800/40 text-white font-bold text-lg hover:bg-emerald-800/60 transition shadow-xl border border-emerald-500/30 backdrop-blur-md transform hover:scale-105">
                                    View Live Stats
                                </a>
                            </div>
                        </div>

                        {/* Floating Stats Cards */}
                        <div id="stats" className="w-full max-w-6xl mx-auto mt-16 md:mt-24 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                            {(liveMetrics || []).map((stat, i) => (
                                <div key={i} className="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl p-6 text-left shadow-2xl hover:bg-white/20 transition transform hover:-translate-y-1">
                                    <h4 className="text-emerald-200 text-sm font-bold uppercase tracking-wider mb-2">{stat.label}</h4>
                                    <p className="text-4xl font-extrabold text-white">{stat.value}</p>
                                </div>
                            ))}
                        </div>

                        {/* Live Charts Section */}
                        <div className="w-full max-w-6xl mx-auto mt-12 grid grid-cols-1 md:grid-cols-2 gap-6">

                            {/* Events Over Time Area */}
                            <div className="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl p-6 shadow-2xl col-span-1">
                                <h3 className="text-white text-lg font-bold mb-4">Events Registered (Last 7 Days)</h3>
                                <div className="h-80">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <AreaChart data={eventsOverTime} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                            <defs>
                                                <linearGradient id="colorCountWelcome" x1="0" y1="0" x2="0" y2="1">
                                                    <stop offset="5%" stopColor="#6ee7b7" stopOpacity={0.8}/>
                                                    <stop offset="95%" stopColor="#6ee7b7" stopOpacity={0}/>
                                                </linearGradient>
                                            </defs>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="rgba(255,255,255,0.1)" />
                                            <XAxis dataKey="date" tickLine={false} axisLine={false} tick={{fill: '#a7f3d0', fontSize: 12}} />
                                            <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{fill: '#a7f3d0', fontSize: 12}} />
                                            <Tooltip contentStyle={{ backgroundColor: 'rgba(0,0,0,0.8)', border: 'none', borderRadius: '8px', color: '#fff' }} itemStyle={{ color: '#fff' }} />
                                            <Area type="monotone" dataKey="count" name="Events" stroke="#6ee7b7" strokeWidth={3} fillOpacity={1} fill="url(#colorCountWelcome)" />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>

                            {/* Block-wise Weekly Status Bar Chart */}
                            <div className="bg-white/10 backdrop-blur-lg border border-white/20 rounded-2xl p-6 shadow-2xl col-span-1">
                                <h3 className="text-white text-lg font-bold mb-4">Block-Wise Events (Last 7 Days)</h3>
                                <div className="h-80">
                                    <ResponsiveContainer width="100%" height="100%">
                                        <BarChart data={eventsByBlock} margin={{ top: 10, right: 10, left: -20, bottom: 20 }}>
                                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="rgba(255,255,255,0.1)" />
                                            <XAxis dataKey="name" angle={-45} textAnchor="end" tickLine={false} axisLine={false} tick={{fill: '#a7f3d0', fontSize: 12}} height={60} />
                                            <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{fill: '#a7f3d0', fontSize: 12}} />
                                            <Tooltip cursor={{fill: 'rgba(255,255,255,0.05)'}} contentStyle={{ backgroundColor: 'rgba(0,0,0,0.8)', border: 'none', borderRadius: '8px', color: '#fff' }} />
                                            <Bar dataKey="total" name="Total Events" fill="#10b981" radius={[4, 4, 0, 0]} />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            </div>
                        </div>
                    </main>

                    {/* Footer */}
                    <footer className="w-full py-8 text-center text-emerald-200/60 text-sm border-t border-white/10 mt-12 bg-black/20 backdrop-blur-sm">
                        <p>&copy; 2026 Department of Information & Public Relations, Government of Jammu & Kashmir.</p>
                        <p className="mt-1">Nasha Mukt Abhiyaan Reporting Portal</p>
                    </footer>
                </div>
            </div>
            <style jsx>{`
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-fade-in-up {
                    animation: fadeInUp 0.8s ease-out forwards;
                }
            `}</style>
        </>
    );
}
