import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

import { SiDocusaurus } from 'react-icons/si';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=inter:400,500,600" rel="stylesheet" />
            </Head>

            <div className="bg-background text-foreground flex min-h-screen flex-col items-center justify-center px-4 py-10 transition-colors duration-300 dark:bg-neutral-950 dark:text-neutral-100">
                {/* Navbar */}
                <nav className="absolute top-6 right-6 flex gap-4 text-sm font-medium">
                    {auth.user ? (
                        <Link
                            href={route('dashboard')}
                            className="border-border bg-background hover:bg-muted rounded-md border px-4 py-2 text-sm transition dark:hover:bg-neutral-800"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <Link href={route('login')} className="px-4 py-2 hover:underline">
                                Log in
                            </Link>
                            <Link
                                href={route('register')}
                                className="border-border bg-background hover:bg-muted rounded-md border px-4 py-2 text-sm transition dark:hover:bg-neutral-800"
                            >
                                Register
                            </Link>
                        </>
                    )}
                </nav>

                {/* Hero Card */}
                <div className="group border-border bg-card mt-10 w-full max-w-md rounded-2xl border p-10 text-center shadow-sm transition-all hover:shadow-md dark:bg-neutral-900">
                    <SiDocusaurus className="text-muted-foreground mx-auto mb-4 h-12 w-12 transition-transform duration-300 group-hover:scale-110" />

                    <h1 className="text-foreground mb-2 text-2xl font-semibold">
                        Welcome to{' '}
                        <span className="hover:underline">
                            <span className="underline">Dino Share</span>
                        </span>
                    </h1>
                    <p className="text-muted-foreground mb-6 text-sm">Simple and secure file sharing for you and your team.</p>

                    <Link
                        href={route('login')}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 inline-block rounded-md px-6 py-2 text-sm font-medium transition"
                    >
                        Get Started
                    </Link>
                </div>

                {/* Footer */}
                <footer className="text-muted-foreground mt-10 text-xs">© 2025 Sharing. All rights reserved.</footer>
            </div>
        </>
    );
}
