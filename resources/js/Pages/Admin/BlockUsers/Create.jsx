import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ blocks }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        block_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-emerald-900">Create Block User</h2>}
        >
            <Head title="Create Block User" />

            <div className="py-12 bg-slate-50 min-h-screen">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8">
                        <form onSubmit={submit} className="space-y-6">
                            <div>
                                <label className="block text-sm font-bold text-slate-700">Name</label>
                                <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                    className="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 py-3 px-4 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required />
                                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-bold text-slate-700">Email</label>
                                <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                    className="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 py-3 px-4 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required />
                                {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
                            </div>

                            <div>
                                <label className="block text-sm font-bold text-slate-700">Assign Block</label>
                                <select value={data.block_id} onChange={e => setData('block_id', e.target.value)}
                                    className="mt-2 block w-full rounded-xl border-slate-200 bg-slate-50 py-3 px-4 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                                    <option value="">Select Block</option>
                                    {blocks.map(block => (
                                        <option key={block.id} value={block.id}>{block.name}</option>
                                    ))}
                                </select>
                                {errors.block_id && <p className="text-red-500 text-xs mt-1">{errors.block_id}</p>}
                            </div>

                            <div className="bg-emerald-50 p-4 rounded-xl text-sm text-emerald-800">
                                <p><strong>Note:</strong> The default password will be set to <code>Nmba@budgam</code>. The user must change this upon first login.</p>
                            </div>

                            <button type="submit" disabled={processing}
                                className="w-full px-8 py-3.5 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-500 shadow-lg shadow-emerald-500/30 transition-all disabled:opacity-50">
                                {processing ? 'Creating...' : 'Create User'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
