import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-emerald-900 via-emerald-800 to-teal-900 relative selection:bg-emerald-500 selection:text-white">
            <div className="absolute inset-0 opacity-10" style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")' }}></div>
            
            <div className="z-10 text-center mb-8">
                <Link href="/" className="inline-flex items-center gap-3 group">
                    <div className="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-lg group-hover:scale-105 transition transform">
                        <span className="text-emerald-700 font-bold text-2xl">JK</span>
                    </div>
                    <div className="text-left">
                        <h1 className="text-2xl font-bold text-white tracking-tight">Nasha Mukt J&K</h1>
                        <p className="text-emerald-200 text-sm font-medium tracking-wide">Authorized Portal Access</p>
                    </div>
                </Link>
            </div>

            <div className="z-10 w-full sm:max-w-md px-8 py-10 bg-white/95 backdrop-blur-md shadow-2xl overflow-hidden sm:rounded-2xl">
                {children}
            </div>
            
            <p className="z-10 mt-10 text-emerald-200/60 text-sm">
                &copy; 2026 Government of Jammu & Kashmir
            </p>
        </div>
    );
}
