<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    CalendarDays,
    LayoutGrid,
    Scissors,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { agenda, dashboard } from '@/routes';
import { index as services } from '@/routes/services';
import { index as team } from '@/routes/team';
import { show as member } from '@/routes/team/members';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Agenda',
        href: agenda(),
        icon: CalendarDays,
    },
    ...(page.props.auth.can.manageServices
        ? [{ title: 'Services', href: services(), icon: Scissors }]
        : []),
    ...(page.props.auth.can.manageTeam
        ? [{ title: 'Team', href: team(), icon: Users }]
        : [
              {
                  title: 'My availability',
                  href: member(page.props.auth.user.id),
                  icon: CalendarClock,
              },
          ]),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
