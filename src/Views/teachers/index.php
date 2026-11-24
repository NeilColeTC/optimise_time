<?php
declare(strict_types=1);

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$hours = static fn (int|string $minutes): string => number_format(((int) $minutes) / 60, 2);
$appName = getenv('APP_NAME') ?: 'Academic Timetable Optimiser';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teachers � <?= $e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:w-64">
        <div class="border-b border-white/10 px-6 py-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Academic planning</p>
            <a href="/" class="mt-2 block text-lg font-semibold"><?= $e($appName) ?></a>
        </div>
        <nav class="flex gap-2 overflow-x-auto p-4 lg:flex-col" aria-label="Main navigation">
            <a href="/" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Overview</a>
            <a href="/teachers" aria-current="page" class="whitespace-nowrap rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white">Teachers</a>
            <a href="/availability" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Availability</a>
            <a href="/demand" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Teaching demand</a>
            <a href="/rooms" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Rooms</a>
            <a href="/week" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Week structure</a>
            <a href="#" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-500">Rules � Setup</a>
        </nav>
    </aside>
    <main class="min-w-0 flex-1">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-5 lg:px-10">
            <div><p class="text-sm text-slate-500">Staffing and workload</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Teachers</h1></div>
            <a href="/teachers/new" class="inline-flex items-center rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2">Add teacher</a>
        </header>
        <div class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-10">
            <?php if ($databaseMessage !== null): ?>
                <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-5 text-sm text-cyan-950"><strong>Database setup needed.</strong> <?= $e($databaseMessage) ?> Import <code class="rounded bg-white px-1.5 py-0.5">database/schema.sql</code> to enable staff records.</div>
            <?php endif; ?>
            <?php if ($flash): ?>
                <div role="status" class="rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>"><?= $e($flash['message']) ?></div>
            <?php endif; ?>
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div><h2 class="font-semibold">Workload profiles</h2><p class="mt-1 text-sm text-slate-500">All values are weekly hours. Expected teaching is allocation less remission and timetable reduction.</p></div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600"><?= count($teachers) ?> <?= count($teachers) === 1 ? 'teacher' : 'teachers' ?></span>
                </div>
                <?php if ($teachers === []): ?>
                    <div class="px-5 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-50 text-xl text-cyan-700" aria-hidden="true">+</div>
                        <h3 class="mt-4 font-semibold">No teacher profiles yet</h3>
                        <p class="mt-1 text-sm text-slate-500">Add staff allocations, remission, reductions, and contact-hour limits to begin.</p>
                        <?php if ($databaseMessage === null): ?><a href="/teachers/new" class="mt-4 inline-flex rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Add the first teacher</a><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[850px] text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr>
                                <th class="px-5 py-3 font-semibold">Teacher</th><th class="px-3 py-3 font-semibold">Allocation</th><th class="px-3 py-3 font-semibold">Remission</th><th class="px-3 py-3 font-semibold">Reduced timetable</th><th class="px-3 py-3 font-semibold">Expected teaching</th><th class="px-3 py-3 font-semibold">In-class maximum</th><th class="px-5 py-3"></th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($teachers as $teacher): ?>
                                    <tr class="<?= (int) $teacher['is_active'] === 0 ? 'opacity-60' : '' ?>">
                                        <td class="px-5 py-4"><div class="font-medium"><?= $e($teacher['first_name'] . ' ' . $teacher['last_name']) ?></div><div class="mt-0.5 text-xs text-slate-500"><?= $e($teacher['staff_code'] ?: 'No staff code') ?><?= (int) $teacher['is_active'] === 0 ? ' � Inactive' : '' ?></div></td>
                                        <td class="px-3 py-4 tabular-nums"><?= $hours($teacher['allocated_teaching_minutes']) ?> h</td>
                                        <td class="px-3 py-4 tabular-nums"><?= $hours($teacher['remission_minutes']) ?> h</td>
                                        <td class="px-3 py-4 tabular-nums"><?= $hours($teacher['reduced_timetable_minutes']) ?> h</td>
                                        <td class="px-3 py-4 font-semibold tabular-nums"><?= $hours($teacher['expected_teaching_minutes']) ?> h</td>
                                        <td class="px-3 py-4 tabular-nums"><?= $hours($teacher['max_in_class_minutes']) ?> h</td>
                                        <td class="px-5 py-4 text-right"><a href="/teachers/edit?id=<?= (int) $teacher['id'] ?>" class="font-medium text-cyan-800 hover:text-cyan-950">Edit</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <p class="text-xs leading-5 text-slate-500">The expected teaching figure is a planning target. The individual in-class maximum is a separate hard limit used when validating a timetable.</p>
        </div>
    </main>
</div>
</body>
</html>

