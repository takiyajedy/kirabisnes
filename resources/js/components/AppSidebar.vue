<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ChartColumn,
    FileText,
    FolderKanban,
    HandCoins,
    LayoutGrid,
    PiggyBank,
    Receipt,
    Repeat,
    Settings,
    Users,
    Wallet,
} from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
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
import { dashboard } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';
import { edit as editBusiness } from '@/routes/business';
import { index as clientsIndex } from '@/routes/clients';
import { index as fundsIndex } from '@/routes/funds';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as ownerPayIndex } from '@/routes/owner-pay';
import { index as projectsIndex } from '@/routes/projects';
import { index as recurringIndex } from '@/routes/recurring';
import { profitLoss } from '@/routes/reports';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as transactionsIndex } from '@/routes/transactions';
import type { NavItem } from '@/types';

/**
 * Menu order follows Section 4.3 of PLAN.md. Items whose module is not built
 * yet are disabled rather than hidden, so the shape of the app is visible.
 *
 * Gaji Sendiri is not in that list — it arrived with phase 2 — and sits next
 * to Tabung, since it is the fund it draws from.
 */
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: wTrans('nav.dashboard').value,
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: wTrans('nav.transactions').value,
        href: transactionsIndex(),
        icon: ArrowLeftRight,
    },
    {
        title: wTrans('nav.invoices').value,
        href: invoicesIndex(),
        icon: FileText,
    },
    {
        title: wTrans('nav.quotations').value,
        href: quotationsIndex(),
        icon: Receipt,
    },
    {
        title: wTrans('nav.clients').value,
        href: clientsIndex(),
        icon: Users,
    },
    {
        title: wTrans('nav.projects').value,
        href: projectsIndex(),
        icon: FolderKanban,
    },
    {
        title: wTrans('nav.funds').value,
        href: fundsIndex(),
        icon: PiggyBank,
    },
    {
        title: wTrans('nav.owner_pay').value,
        href: ownerPayIndex(),
        icon: HandCoins,
    },
    {
        title: wTrans('nav.accounts').value,
        href: accountsIndex(),
        icon: Wallet,
    },
    {
        title: wTrans('nav.recurring').value,
        href: recurringIndex(),
        icon: Repeat,
    },
    {
        title: wTrans('nav.reports').value,
        // The reports share a tab strip; this is the one it opens on.
        href: profitLoss(),
        icon: ChartColumn,
    },
    {
        title: wTrans('nav.settings').value,
        href: editBusiness(),
        icon: Settings,
    },
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
