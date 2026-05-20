import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const { flash } = usePage().props;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [flashVisible, setFlashVisible] = useState(true);

    return (
        <div className="min-h-screen bg-slate-50">
            {/* Top Navigation Bar */}
            <nav className="border-b border-slate-200 bg-white shadow-sm sticky top-0 z-30">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between items-center">

                        {/* Left: Logo + Nav Links */}
                        <div className="flex items-center gap-8">
                            <Link href="/" className="flex items-center gap-2.5 group shrink-0">
                                <div className="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center shadow-sm group-hover:bg-emerald-500 transition-colors">
                                    <span className="text-white font-black text-xs">JK</span>
                                </div>
                                <span className="font-extrabold text-slate-800 text-sm tracking-tight hidden sm:block">
                                    Nasha Mukt J&K
                                </span>
                            </Link>

                            <div className="hidden sm:flex items-center gap-1">
                                {user.role === 'admin' && (
                                    <>
                                        <NavLink href={route('dashboard')} active={route().current('dashboard')} className="text-sm font-semibold">Dashboard</NavLink>
                                        <NavLink href={route('events.index')} active={route().current('events.index')} className="text-sm font-semibold">View Events</NavLink>
                                        <NavLink href={route('events.create')} active={route().current('events.create')} className="text-sm font-semibold">Create Event</NavLink>
                                        <NavLink href={route('users.index')} active={route().current('users.index')} className="text-sm font-semibold">Manage Users</NavLink>
                                    </>
                                )}
                                {user.role === 'district_creator' && (
                                    <>
                                        <NavLink href={route('events.index')} active={route().current('events.index')} className="text-sm font-semibold">View Events</NavLink>
                                        <NavLink href={route('events.create')} active={route().current('events.create')} className="text-sm font-semibold">Create Event</NavLink>
                                    </>
                                )}
                                {user.role === 'block_worker' && (
                                    <>
                                        <NavLink href={route('block.events.index')} active={route().current('block.events.index')} className="text-sm font-semibold">View Events</NavLink>
                                        <NavLink href={route('block.events.create')} active={route().current('block.events.create')} className="text-sm font-semibold">Create Event</NavLink>
                                    </>
                                )}
                            </div>
                        </div>

                        {/* Right: User Dropdown (desktop) */}
                        <div className="hidden sm:flex items-center gap-3">
                            <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 border border-slate-200">
                                <div className="w-6 h-6 rounded-full bg-emerald-600 flex items-center justify-center shrink-0">
                                    <span className="text-white font-bold text-xs">
                                        {user.name.charAt(0).toUpperCase()}
                                    </span>
                                </div>
                                <span className="text-sm font-semibold text-slate-700 max-w-[120px] truncate">{user.name}</span>
                            </div>

                            <div className="relative">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition-all hover:bg-slate-50 hover:border-slate-300 focus:outline-none"
                                        >
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            Account
                                            <svg className="h-3.5 w-3.5 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                            </svg>
                                        </button>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        {user.role === 'admin' && (
                                            <Dropdown.Link href={route('profile.edit')}>
                                                Profile
                                            </Dropdown.Link>
                                        )}
                                        <Dropdown.Link href={route('logout')} method="post" as="button">
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        {/* Mobile hamburger */}
                        <button
                            onClick={() => setShowingNavigationDropdown(prev => !prev)}
                            className="sm:hidden inline-flex items-center justify-center rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 focus:outline-none"
                            aria-label="Toggle navigation"
                        >
                            {showingNavigationDropdown ? (
                                <svg className="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            ) : (
                                <svg className="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            )}
                        </button>
                    </div>
                </div>

                {/* Mobile Navigation Drawer */}
                <div className={`sm:hidden transition-all duration-200 ease-in-out overflow-hidden ${showingNavigationDropdown ? 'max-h-96 border-t border-slate-100' : 'max-h-0'}`}>
                    <div className="px-4 py-3 space-y-1">
                        {user.role === 'admin' && (
                            <>
                                <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>Dashboard</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('events.index')} active={route().current('events.index')}>View Events</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('events.create')} active={route().current('events.create')}>Create Event</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('users.index')} active={route().current('users.index')}>Manage Users</ResponsiveNavLink>
                            </>
                        )}
                        {user.role === 'district_creator' && (
                            <>
                                <ResponsiveNavLink href={route('events.index')} active={route().current('events.index')}>View Events</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('events.create')} active={route().current('events.create')}>Create Event</ResponsiveNavLink>
                            </>
                        )}
                        {user.role === 'block_worker' && (
                            <>
                                <ResponsiveNavLink href={route('block.events.index')} active={route().current('block.events.index')}>View Events</ResponsiveNavLink>
                                <ResponsiveNavLink href={route('block.events.create')} active={route().current('block.events.create')}>Create Event</ResponsiveNavLink>
                            </>
                        )}
                    </div>

                    <div className="border-t border-slate-100 px-4 py-3">
                        <div className="flex items-center gap-3 mb-3">
                            <div className="w-8 h-8 rounded-full bg-emerald-600 flex items-center justify-center shrink-0">
                                <span className="text-white font-bold text-sm">{user.name.charAt(0).toUpperCase()}</span>
                            </div>
                            <div>
                                <div className="text-sm font-bold text-slate-800">{user.name}</div>
                                <div className="text-xs text-slate-500">{user.email}</div>
                            </div>
                        </div>
                        <div className="space-y-1">
                            {user.role === 'admin' && (
                                <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                            )}
                            <ResponsiveNavLink method="post" href={route('logout')} as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {/* Page Header */}
            {header && (
                <header className="bg-white border-b border-slate-200 shadow-sm">
                    <div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Flash Messages */}
            {flash?.success && flashVisible && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className="flex items-start justify-between gap-4 bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-4 shadow-sm animate-[fadeIn_0.5s_ease-out]">
                        <div className="flex items-center gap-3">
                            <div className="shrink-0 w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center shadow-sm">
                                <svg className="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div className="text-sm font-medium text-emerald-800" dangerouslySetInnerHTML={{ __html: flash.success }}></div>
                        </div>
                        <button
                            onClick={() => setFlashVisible(false)}
                            className="shrink-0 text-emerald-400 hover:text-emerald-600 transition-colors mt-1"
                            aria-label="Dismiss"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            {flash?.error && flashVisible && (
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-4">
                    <div className="flex items-start justify-between gap-4 bg-rose-50 border border-rose-200 rounded-2xl px-5 py-4 shadow-sm">
                        <div className="flex items-center gap-3">
                            <div className="shrink-0 w-6 h-6 rounded-full bg-rose-500 flex items-center justify-center">
                                <svg className="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <p className="text-sm font-semibold text-rose-800">{flash.error}</p>
                        </div>
                        <button
                            onClick={() => setFlashVisible(false)}
                            className="shrink-0 text-rose-400 hover:text-rose-600 transition-colors"
                            aria-label="Dismiss"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
