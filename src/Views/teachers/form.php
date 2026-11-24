<?php
declare(strict_types=1);

$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$appName = getenv('APP_NAME') ?: 'Academic Timetable Optimiser';
$value = static fn (string $key, string $fallback = ''): string => (string) ($teacher[$key] ?? $fallback);
if ($remissions === []) {
    $remissions = [['remission_type_id' => '', 'hours' => '']];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> � <?= $e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen lg:flex">
    <aside class="bg-slate-950 text-white lg:w-64">
        <div class="border-b border-white/10 px-6 py-6"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">Academic planning</p><a href="/" class="mt-2 block text-lg font-semibold"><?= $e($appName) ?></a></div>
        <nav class="flex gap-2 overflow-x-auto p-4 lg:flex-col" aria-label="Main navigation">
            <a href="/" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Overview</a>
            <a href="/teachers" aria-current="page" class="whitespace-nowrap rounded-lg bg-white/10 px-3 py-2 text-sm font-medium text-white">Teachers</a>
            <a href="/demand" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Teaching demand</a><a href="/rooms" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Rooms</a><a href="/week" class="whitespace-nowrap rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-white/5 hover:text-white">Week structure</a>
        </nav>
    </aside>
    <main class="min-w-0 flex-1">
        <header class="flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-6 py-5 lg:px-10"><div><p class="text-sm text-slate-500"><a href="/teachers" class="hover:text-cyan-800">Teachers</a> / workload profile</p><h1 class="mt-1 text-2xl font-semibold tracking-tight"><?= $e($title) ?></h1></div><a href="/teachers" class="text-sm font-medium text-slate-600 hover:text-slate-950">Cancel</a></header>
        <div class="mx-auto max-w-4xl space-y-6 px-6 py-8 lg:px-10">
            <?php if ($flash): ?><div role="status" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= $e($flash['message']) ?></div><?php endif; ?>
            <?php if ($errors !== []): ?><div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900"><p class="font-semibold">Please correct these details:</p><ul class="mt-2 list-inside list-disc space-y-1"><?php foreach ($errors as $error): ?><li><?= $e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" action="<?= $e($action) ?>" class="space-y-6">
                <input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6">
                    <h2 class="font-semibold">Staff details</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-medium">First name<input name="first_name" required maxlength="100" value="<?= $e($value('first_name')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                        <label class="block text-sm font-medium">Last name<input name="last_name" required maxlength="100" value="<?= $e($value('last_name')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                        <label class="block text-sm font-medium sm:col-span-2">Staff code <span class="font-normal text-slate-500">(optional)</span><input name="staff_code" maxlength="40" value="<?= $e($value('staff_code')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                    </div>
                    <label class="mt-5 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" <?= (int) ($teacher['is_active'] ?? 1) === 1 ? 'checked' : '' ?> class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-700">Active for timetable planning</label>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6">
                    <h2 class="font-semibold">Weekly teaching hours</h2><p class="mt-1 text-sm text-slate-500">Enter hours as decimals, for example 18.5. These values are stored as minutes.</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-3">
                        <label class="block text-sm font-medium">Allocated teaching hours<input type="number" name="allocated_hours" min="0" max="168" step="0.25" required value="<?= $e($value('allocated_hours')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                        <label class="block text-sm font-medium">Reduced timetable hours<input type="number" name="reduced_hours" min="0" max="168" step="0.25" required value="<?= $e($value('reduced_hours', '0')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"><span class="mt-1 block text-xs font-normal text-slate-500">Weekly reduction from the teaching allocation.</span></label>
                        <label class="block text-sm font-medium">Maximum in-class hours<input type="number" name="max_in_class_hours" min="0" max="168" step="0.25" required value="<?= $e($value('max_in_class_hours')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"><span class="mt-1 block text-xs font-normal text-slate-500">Individual hard cap for scheduled contact time.</span></label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">Remission</h2><p class="mt-1 text-sm text-slate-500">Record each remission responsibility separately. These hours reduce the expected teaching target.</p></div><button type="button" id="add-remission" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Add remission</button></div>
                    <div id="remission-rows" class="mt-4 space-y-3">
                        <?php foreach ($remissions as $remission): ?>
                            <div class="remission-row grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_180px_auto] sm:items-end">
                                <label class="block text-sm font-medium">Responsibility<select name="remission_type_id[]" class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"><option value="">Choose a remission type</option><?php foreach ($remissionTypes as $type): ?><option value="<?= (int) $type['id'] ?>" <?= (string) ($remission['remission_type_id'] ?? '') === (string) $type['id'] ? 'selected' : '' ?>><?= $e($type['name']) ?></option><?php endforeach; ?></select></label>
                                <label class="block text-sm font-medium">Hours per week<input type="number" name="remission_hours[]" min="0" max="168" step="0.25" value="<?= $e((string) ($remission['hours'] ?? '')) ?>" class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label>
                                <button type="button" class="remove-remission rounded-lg px-3 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <template id="remission-template"><div class="remission-row grid gap-3 rounded-lg bg-slate-50 p-3 sm:grid-cols-[1fr_180px_auto] sm:items-end"><label class="block text-sm font-medium">Responsibility<select name="remission_type_id[]" class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"><option value="">Choose a remission type</option><?php foreach ($remissionTypes as $type): ?><option value="<?= (int) $type['id'] ?>"><?= $e($type['name']) ?></option><?php endforeach; ?></select></label><label class="block text-sm font-medium">Hours per week<input type="number" name="remission_hours[]" min="0" max="168" step="0.25" class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-normal shadow-sm focus:border-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-700/20"></label><button type="button" class="remove-remission rounded-lg px-3 py-2.5 text-sm font-medium text-rose-700 hover:bg-rose-50">Remove</button></div></template>
                </section>
                <div class="flex flex-wrap items-center justify-between gap-3"><a href="/teachers" class="text-sm font-medium text-slate-600 hover:text-slate-950">Cancel</a><button type="submit" class="rounded-lg bg-cyan-700 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-600 focus:ring-offset-2">Save teacher</button></div>
            </form>
            <?php if (isset($teacher['id'])): ?>
                <form method="post" action="/teachers/delete" onsubmit="return confirm('Deactivate this teacher? Their workload and timetable records will be retained.')" class="border-t border-slate-200 pt-5"><input type="hidden" name="_csrf" value="<?= $e($csrfToken) ?>"><input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>"><button type="submit" class="text-sm font-medium text-rose-700 hover:text-rose-900">Deactivate teacher</button></form>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
const rows = document.getElementById('remission-rows');
document.getElementById('add-remission').addEventListener('click', () => rows.append(document.getElementById('remission-template').content.cloneNode(true)));
rows.addEventListener('click', event => {
    if (event.target.closest('.remove-remission')) {
        const row = event.target.closest('.remission-row');
        if (rows.querySelectorAll('.remission-row').length > 1) row.remove();
        else row.querySelectorAll('select, input').forEach(field => field.value = '');
    }
});
</script>
</body>
</html>

