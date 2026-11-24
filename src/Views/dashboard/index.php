<?php
declare(strict_types=1);

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$navItems = ['Overview', 'Timetable', 'Teachers', 'Teaching demand', 'Rooms', 'Rules'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:flex lg:w-64 lg:flex-col">
        <div class="border-b border-white/10 px-6 py-6">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Academic planning</p>
            <h1 class="mt-2 text-lg font-semibold"><?= $e($appName) ?></h1>
        </div>
        <nav class="flex gap-2 overflow-x-auto p-4 lg:flex-col" aria-label="Main navigation">
            <?php foreach ($navItems as $index => $item): ?>
                <a href="<?= $index === 0 ? '/' : '#' ?>" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm <?= $index === 0 ? 'bg-white/10 font-medium text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
                    <?= $e($item) ?><?= $index > 0 ? '<span class="ml-2 text-xs text-slate-500">Setup</span>' : '' ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="mt-auto hidden border-t border-white/10 p-5 text-xs leading-5 text-slate-400 lg:block">
            Weekly timetable workspace<br>Rules are checked before a timetable is accepted.
        </div>
    </aside>

    <main class="min-w-0 flex-1">
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-5 lg:px-10">
            <div>
                <p class="text-sm text-slate-500">Planning workspace</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">Overview</h2>
            </div>
            <span class="rounded-full bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-800">Setup in progress</span>
        </header>

        <div class="mx-auto max-w-7xl space-y-8 px-6 py-8 lg:px-10">
            <?php if ($databaseMessage !== null): ?>
                <section class="rounded-xl border border-cyan-200 bg-cyan-50 p-5" aria-labelledby="setup-heading">
                    <h3 id="setup-heading" class="font-semibold text-cyan-950">Connect your planning data</h3>
                    <p class="mt-1 text-sm text-cyan-900"><?= $e($databaseMessage) ?></p>
                    <p class="mt-2 text-sm text-cyan-900">Import <code class="rounded bg-white px-1.5 py-0.5">database/schema.sql</code> after creating the database.</p>
                </section>
            <?php endif; ?>

            <section>
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">Planning data</h3>
                        <p class="mt-1 text-sm text-slate-500">The core resources that determine whether a weekly schedule is feasible.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ([['Teachers', 'teachers', 'Individual allocations and contact-hour limits'], ['Teaching groups', 'groups', 'Weekly subject-hour requirements'], ['Rooms', 'rooms', 'Capacity and room availability'], ['Scheduled sessions', 'sessions', 'Placed lessons in the current timetable']] as [$label, $key, $description]): ?>
                        <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40">
                            <p class="text-sm font-medium text-slate-500"><?= $e($label) ?></p>
                            <p class="mt-3 text-3xl font-semibold tracking-tight"><?= $stats[$key] ?></p>
                            <p class="mt-2 text-sm leading-5 text-slate-500"><?= $e($description) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
                <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/40">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold">Build a timetable</h3>
                            <p class="mt-1 text-sm text-slate-500">Set up the inputs, then generate and review a weekly schedule.</p>
                        </div>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">Not generated</span>
                    </div>
                    <ol class="mt-6 grid gap-3 sm:grid-cols-2">
                        <?php foreach ([['01', 'Set the week', 'Teaching days, periods, and availability'], ['02', 'Enter teaching demand', 'Groups, subjects, and hours per week'], ['03', 'Add staff and rooms', 'Workload profiles, caps, capacity, and access'], ['04', 'Apply rules', 'Evening duties, lieu time, and other constraints']] as [$number, $title, $detail]): ?>
                            <li class="flex gap-3 rounded-lg bg-slate-50 p-4">
                                <span class="mt-0.5 text-xs font-bold text-cyan-700"><?= $e($number) ?></span>
                                <span><strong class="block text-sm font-medium"><?= $e($title) ?></strong><span class="mt-1 block text-sm text-slate-500"><?= $e($detail) ?></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </article>

                <article class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm shadow-slate-200/40">
                    <h3 class="text-lg font-semibold">Rules enforced</h3>
                    <p class="mt-1 text-sm text-slate-500">A schedule is valid only when these checks pass.</p>
                    <ul class="mt-5 space-y-3 text-sm">
                        <?php foreach (['No teacher is booked in two places at once', 'No room or teaching group is double-booked', 'Room capacity meets group size', 'Teacher contact time stays within their personal maximum', 'Evening duty receives its configured half-day in lieu'] as $rule): ?>
                            <li class="flex gap-2.5"><span class="text-emerald-600" aria-hidden="true">V</span><span><?= $e($rule) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            </section>
        </div>
    </main>
</div>
</body>
</html>

