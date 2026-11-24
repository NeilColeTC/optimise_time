<?php
declare(strict_types=1);
$e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$appName = getenv('APP_NAME') ?: 'Academic Timetable Optimiser';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Database setup � <?= $e($appName) ?></title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased"><main class="mx-auto max-w-2xl px-6 py-16"><a href="/" class="text-sm font-medium text-cyan-800"> Back to overview</a><section class="mt-6 rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Setup required</p><h1 class="mt-2 text-2xl font-semibold">Connect the timetable database</h1><p class="mt-3 text-slate-600"><?= $e($databaseMessage ?? 'The database is not available.') ?></p><p class="mt-4 text-sm text-slate-600">Create the database, import <code class="rounded bg-slate-100 px-1.5 py-0.5">database/schema.sql</code>, then set the values in <code class="rounded bg-slate-100 px-1.5 py-0.5">.env</code>.</p></section></main></body></html>

