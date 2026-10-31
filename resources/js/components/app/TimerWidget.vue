<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Square, Timer } from '@lucide/vue';
import { wTrans } from 'laravel-vue-i18n';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatDuration } from '@/lib/duration';
import { stop } from '@/routes/time';

/**
 * The running timer, shown wherever the owner happens to be.
 *
 * The elapsed figure is worked out in the browser from the start time rather
 * than polled: the server already said when it started, and a clock that
 * needs a request every second to tick would be a poor trade.
 */
const page = usePage();

const timer = computed(() => page.props.runningTimer ?? null);

// Re-read every ten seconds. The display is in whole minutes, so a tighter
// tick would redraw the same text.
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    ticker = setInterval(() => {
        now.value = Date.now();
    }, 10_000);
});

onBeforeUnmount(() => {
    clearInterval(ticker);
});

const elapsed = computed(() => {
    const running = timer.value;

    if (running === null) {
        return 0;
    }

    const started = new Date(running.started_at).getTime();

    return Math.max(0, Math.round((now.value - started) / 60_000));
});

const label = wTrans('time.running');

const stopTimer = () => {
    router.post(stop().url, {}, { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="timer !== null"
        class="flex items-center gap-1.5 rounded-lg border border-income/40 bg-income-muted pr-1 pl-2.5"
        :title="label"
    >
        <Timer class="size-3.5 shrink-0 animate-pulse text-income" />
        <span class="hidden min-w-0 text-xs text-income sm:block">
            <span class="truncate font-medium">{{ timer.project_name }}</span>
        </span>
        <span class="text-xs font-medium text-income tabular-nums">
            {{ formatDuration(elapsed) }}
        </span>
        <Button
            variant="ghost"
            size="icon"
            class="size-7 text-income hover:bg-income/10 hover:text-income"
            :aria-label="$t('time.stop')"
            @click="stopTimer"
        >
            <Square class="size-3.5" />
        </Button>
    </div>
</template>
