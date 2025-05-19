import { NavFooter } from '@/components/nav-footer';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Boxes, ChartPie, Folder, FolderOpen, Handshake, LayoutGrid, Share2, Star } from 'lucide-react';
import AppLogo from './app-logo';
import { NavMain } from './nav-main';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Folders',
        href: '/welcome',
        icon: FolderOpen,
    },
    {
        title: 'Recents',
        href: '/welcome',
        icon: ChartPie,
    },
    {
        title: 'Friends',
        href: '/welcome',
        icon: Handshake,
    },
    {
        title: 'Groups',
        href: '/welcome',
        icon: Boxes,
    },
    {
        title: 'Favorites',
        href: '/welcome',
        icon: Star,
    },
    {
        title: 'Shares',
        href: '/welcome',
        icon: Share2,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
