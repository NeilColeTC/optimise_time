<?php
declare(strict_types=1);
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$appName = getenv('APP_NAME') ?: 'Academic Timetable Optimiser';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rooms � <?= $e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:w-64">
        <div class="border-b border-white/10 px-6 py-6"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Academic planning</p><a href="/" class="mt-2 block text-lg font-semibold"><?= $e($appName) ?></a></div>
        <nav class="flex gap-2 overflow-x-auto p-4 lg:flex-col" aria-label="Main navigation">
            <a href="/" class="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5">Overview</a>
            <a href="/teachers" class="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5">Teachers</a>
            <a href="/demand" class="rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5">Teaching demand</a>
            <a href="/rooms" aria-current="page" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-medium">Rooms</a>
            <a href="#" class="rounded-lg px-3 py-2 text-sm text-slate-500">Timetable rules � Setup</a>
        </nav>
    </aside>
    <main class="min-w-0 flex-1">
        <header class="border-b border-slate-200 bg-white px-6 py-5 lg:px-10"><p class="text-sm text-slate-500">Finite teaching resources</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">Rooms</h1></header>
        <div class="mx-auto max-w-7xl space-y-6 px-6 py-8 lg:px-10">
            <?php if ($flash): ?><div role="status" class="rounded-xl border px-4 py-3 text-sm <?= $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>"><?= $e($flash['message']) ?></div><?php endif; ?>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold">Add a room</h2><p class="mt-1 text-sm text-slate-500">Capacity and room type will be used when checking teaching requirements.</p>
                <form method="post" action="/rooms/save" class="mt-4 grid gap-3 sm:grid-cols-[1fr_160px_1fr_auto] sm:items-end">
                    <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                    <label class="block text-sm font-medium">Room name<input name="name" required maxlength="120" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20" placeholder="e.g. A204"></label>
                    <label class="block text-sm font-medium">Capacity<input type="number" name="capacity" required min="1" max="5000" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                    <label class="block text-sm font-medium">Room type<input name="room_type" maxlength="100" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20" placeholder="e.g. Computer lab"></label>
                    <button class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-800" type="submit">Add room</button>
                </form>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold">Room inventory</h2><p class="mt-1 text-sm text-slate-500">Inactive rooms remain available for historical records.</p></div>
                <?php if ($rooms === []): ?>
                    <div class="px-5 py-14 text-center"><h3 class="font-semibold">No rooms entered yet</h3><p class="mt-1 text-sm text-slate-500">Add available teaching rooms to include them in planning.</p></div>
                <?php else: ?>
                    <div class="overflow-x-auto"><table class="w-full min-w-[750px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Room</th><th class="px-3 py-3">Capacity</th><th class="px-3 py-3">Room type</th><th class="px-3 py-3">Status</th><th class="px-5 py-3"></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($rooms as $room): $formId = 'room-form-' . (int) $room['id']; ?>
                            <tr class="<?= (int) $room['is_active'] === 0 ? 'opacity-60' : '' ?>">
                                <td class="px-5 py-3"><input form="<?= $formId ?>" aria-label="Room name" name="name" required maxlength="120" value="<?= $e($room['name']) ?>" class="w-full rounded-md border border-slate-200 px-2.5 py-2 font-medium"></td>
                                <td class="px-3 py-3"><input form="<?= $formId ?>" aria-label="Room capacity" type="number" name="capacity" min="1" max="5000" required value="<?= (int) $room['capacity'] ?>" class="w-24 rounded-md border border-slate-200 px-2.5 py-2 tabular-nums"></td>
                                <td class="px-3 py-3"><input form="<?= $formId ?>" aria-label="Room type" name="room_type" maxlength="100" value="<?= $e((string) ($room['room_type'] ?? '')) ?>" class="w-full rounded-md border border-slate-200 px-2.5 py-2"></td>
                                <td class="px-3 py-3"><label class="inline-flex items-center gap-2"><input form="<?= $formId ?>" type="checkbox" name="is_active" value="1" <?= (int) $room['is_active'] === 1 ? 'checked' : '' ?> class="rounded border-slate-300 text-cyan-700"><span><?= (int) $room['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></label></td>
                                <td class="px-5 py-3 text-right"><div class="flex justify-end gap-3"><form id="<?= $formId ?>" method="post" action="/rooms/save"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $room['id'] ?>"><button type="submit" class="font-medium text-cyan-800">Save</button></form>
                                    <?php if ((int) $room['is_active'] === 1): ?><form method="post" action="/rooms/deactivate" onsubmit="return confirm('Deactivate this room?')"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $room['id'] ?>"><button type="submit" class="font-medium text-rose-700">Deactivate</button></form><?php endif; ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
</body>
</html>

