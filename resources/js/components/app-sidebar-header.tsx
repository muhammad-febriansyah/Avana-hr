import { usePage } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import type { SharedData } from '@/types';

export function AppSidebarHeader() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();
    const roleLabel = auth.isSuperAdmin ? 'Super Admin' : 'Company Admin';

    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-border/60 bg-background/80 px-4 backdrop-blur md:px-6">
            <SidebarTrigger className="-ml-1" />

            <div className="relative hidden w-full max-w-md md:block">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    placeholder="Cari karyawan, menu..."
                    className="h-10 rounded-full bg-muted/50 pl-9"
                />
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative rounded-full text-muted-foreground"
                    aria-label="Notifikasi"
                >
                    <Bell className="size-5" />
                    <span className="absolute top-2 right-2.5 size-2 rounded-full bg-red-500 ring-2 ring-background" />
                </Button>

                {auth.user && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="h-11 gap-2.5 rounded-full px-1.5 md:px-2"
                                data-test="topbar-user-menu"
                            >
                                <Avatar className="size-8">
                                    <AvatarImage src={auth.user.avatar} />
                                    <AvatarFallback className="bg-primary text-primary-foreground">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="hidden text-left leading-tight md:block">
                                    <div className="text-sm font-medium text-foreground">
                                        {auth.user.name}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {roleLabel}
                                    </div>
                                </div>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>
        </header>
    );
}
