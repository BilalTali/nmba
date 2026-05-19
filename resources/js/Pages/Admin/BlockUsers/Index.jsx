import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ users }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center w-full">
                    <div>
                        <h2 className="text-xl font-bold text-slate-800">Block Worker Accounts</h2>
                        <p className="text-sm text-slate-500 mt-1">Manage and assign credentials for block-level field agents.</p>
                    </div>
                    <Link
                        href={route('users.create')}
                        className="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl shadow-lg shadow-emerald-600/20 transition-all text-sm flex items-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fillRule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clipRule="evenodd" />
                        </svg>
                        Create Block User
                    </Link>
                </div>
            }
        >
            <Head title="Block Users" />

            <div className="py-12 bg-slate-50 min-h-screen">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-2xl border border-slate-100 p-8">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100">
                                <thead>
                                    <tr className="bg-slate-50/75">
                                        <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider rounded-l-xl">Name</th>
                                        <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Email</th>
                                        <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Assigned Block</th>
                                        <th scope="col" className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider rounded-r-xl">Role Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 bg-white">
                                    {users.data.length === 0 ? (
                                        <tr>
                                            <td colSpan="4" className="px-6 py-10 text-center text-slate-500">
                                                No block workers found. Click "Create Block User" to add one.
                                            </td>
                                        </tr>
                                    ) : (
                                        users.data.map((user) => (
                                            <tr key={user.id} className="hover:bg-slate-50/50 transition-colors">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900">{user.name}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">{user.email}</td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-emerald-800">
                                                    {user.block ? user.block.name : <span className="text-slate-400 italic">None</span>}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span className="px-3 py-1 text-xs font-bold rounded-full bg-emerald-50 text-emerald-700 uppercase tracking-wider">
                                                        {user.role}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
