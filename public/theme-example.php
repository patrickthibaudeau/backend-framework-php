<?php
// Example page demonstrating use of $OUTPUT->header() and $OUTPUT->footer()
// Accessible via /theme-example.php (assuming document root is public/)

declare(strict_types=1);

// Early vendor deprecation suppression (Mustache PHP 8.4 parse warnings)
// Simplified & open_basedir-safe loader
(function(){
    $rootCandidate = realpath(__DIR__ . '/../early_bootstrap.php');
    $projectRoot   = realpath(__DIR__ . '/..');

    $loaded = false;
    if ($rootCandidate && $projectRoot && str_starts_with($rootCandidate, $projectRoot)) {
        if (is_file($rootCandidate)) {
            require_once $rootCandidate;
            $loaded = true;
        }
    }
    if (!$loaded) {
        $env = getenv('MUSTACHE_SUPPRESS_DEPRECATIONS');
        $suppress = true;
        if ($env !== false) {
            $norm = strtolower(trim($env));
            if (in_array($norm, ['0','false','off','no'], true)) { $suppress = false; }
        }
        if ($suppress) {
            set_error_handler(function($errno, $errstr, $errfile = null) {
                if (($errno & E_DEPRECATED) && $errfile && str_contains($errfile, '/mustache/mustache/')) { return true; }
                return false;
            });
            error_log('theme-example.php: early_bootstrap.php not found or outside allowed paths; inline suppression active');
        } else {
            error_log('theme-example.php: suppression disabled by MUSTACHE_SUPPRESS_DEPRECATIONS');
        }
    }
})();

// Load Composer autoloader FIRST (required for framework classes)
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    error_log('theme-example.php: Composer autoloader not found at ' . $autoloader);
}

require_once __DIR__ . '/../src/Core/helpers.php';

global $OUTPUT;

// Sample navigation data for the header
$nav = [
    ['url' => '/index.php', 'label' => 'Home'],
    ['url' => '/dashboard.php', 'label' => 'Dashboard', 'active' => true],
    ['url' => '/theme-example.php', 'label' => 'Theme Demo'],
];

// User context (would normally come from auth system)
$user = [ 'username' => 'admin' ];

// Render header
echo $OUTPUT->header([
    'page_title' => 'Theme Demo',
    'site_name' => 'DevFramework',
    'nav' => $nav,
    'user' => $user,
    'logout_url' => '/logout.php',
    'meta_description' => 'Demonstration of default core theme header/footer convenience methods.'
]);

?>

<div class="space-y-10">
    <!-- Page Heading -->
    <div class="flex items-start justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-800">Default Theme Showcase</h1>
            <p class="mt-2 text-slate-500 max-w-2xl text-sm">Below are example components styled with Tailwind to demonstrate the base layout, cards, tables, alerts and interactive widgets.</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="px-4 py-2 rounded-md bg-brand-600 text-white text-sm font-medium shadow hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Primary Action</button>
            <button class="px-4 py-2 rounded-md border border-slate-300 bg-white text-sm font-medium hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">Secondary</button>
        </div>
    </div>

    <!-- KPI Cards -->
    <section>
        <h2 class="sr-only">Key Metrics</h2>
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
            <?php
            $stats = [
                ['label' => 'Active Users', 'value' => '1,248', 'change' => '+5.2%', 'trend' => 'up'],
                ['label' => 'New Signups', 'value' => '93', 'change' => '+2.1%', 'trend' => 'up'],
                ['label' => 'Failed Logins', 'value' => '7', 'change' => '-12%', 'trend' => 'down'],
                ['label' => 'System Load', 'value' => '32%', 'change' => '+1.4%', 'trend' => 'up'],
            ];
            foreach ($stats as $s):
                $trendColor = $s['trend'] === 'up' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50';
            ?>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= htmlspecialchars($s['label']) ?></p>
                    <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-medium <?= $trendColor ?>"><?= htmlspecialchars($s['change']) ?></span>
                </div>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-slate-800"><?= htmlspecialchars($s['value']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Alerts -->
    <section class="space-y-4">
        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">System status: All services operational.</div>
        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">Planned maintenance scheduled for Saturday 02:00 UTC.</div>
        <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">3 login attempts blocked due to rate limiting.</div>
    </section>

    <!-- Data Table -->
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700 tracking-wide">Recent Activity</h2>
            <button class="text-xs font-medium text-brand-600 hover:underline">View All</button>
        </div>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">User</th>
                        <th class="px-4 py-2">Action</th>
                        <th class="px-4 py-2">IP</th>
                        <th class="px-4 py-2">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php
                    $rows = [
                        ['user' => 'admin', 'action' => 'Logged in', 'ip' => '192.168.0.10', 'time' => 'Just now'],
                        ['user' => 'jane', 'action' => 'Updated profile', 'ip' => '192.168.0.23', 'time' => '2m ago'],
                        ['user' => 'system', 'action' => 'Cron job executed', 'ip' => '127.0.0.1', 'time' => '5m ago'],
                        ['user' => 'mark', 'action' => 'Password reset', 'ip' => '192.168.0.44', 'time' => '12m ago'],
                    ];
                    foreach ($rows as $r): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 font-medium text-slate-700"><?= htmlspecialchars($r['user']) ?></td>
                        <td class="px-4 py-2 text-slate-600"><?= htmlspecialchars($r['action']) ?></td>
                        <td class="px-4 py-2 text-slate-500 font-mono text-xs"><?= htmlspecialchars($r['ip']) ?></td>
                        <td class="px-4 py-2 text-slate-500 text-xs"><?= htmlspecialchars($r['time']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Two Column Content -->
    <section class="grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">System Overview</h3>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">This demonstration layout illustrates how to compose pages using <code class="bg-slate-100 px-1 py-0.5 rounded text-xs">$OUTPUT->header()</code> and <code class="bg-slate-100 px-1 py-0.5 rounded text-xs">$OUTPUT->footer()</code>. Insert your dynamic application logic and components between these calls. Utilize Tailwind utility classes for rapid UI composition.</p>
                <ul class="mt-4 space-y-2 text-sm text-slate-600 list-disc list-inside">
                    <li>Consistent top navigation & branding</li>
                    <li>Semantic, accessible HTML structure</li>
                    <li>Composable content areas and cards</li>
                    <li>Easy theming via centralized templates</li>
                </ul>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">Next Steps</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-md border border-slate-200 p-4 bg-slate-50">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Customization</p>
                        <p class="text-sm text-slate-600">Add more partials (sidebar, breadcrumbs) and register them as reusable UI fragments.</p>
                    </div>
                    <div class="rounded-md border border-slate-200 p-4 bg-slate-50">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Dynamic Data</p>
                        <p class="text-sm text-slate-600">Replace static arrays with real database-driven metrics and activity logs.</p>
                    </div>
                </div>
            </div>
        </div>
        <aside class="space-y-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Environment</h4>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between"><dt>PHP</dt><dd><?= phpversion() ?></dd></div>
                    <div class="flex justify-between"><dt>Server Time</dt><dd><?= date('H:i:s') ?></dd></div>
                    <div class="flex justify-between"><dt>Memory</dt><dd><?= number_format(memory_get_usage(true)/1048576, 2) ?> MB</dd></div>
                </dl>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Quick Tips</h4>
                <ul class="space-y-2 text-xs text-slate-600 list-disc list-inside">
                    <li>Use partials (Mustache includes) to DRY up UI.</li>
                    <li>Leverage utility-first styling for speed.</li>
                    <li>Encapsulate repeated patterns into helpers.</li>
                </ul>
            </div>
        </aside>
    </section>
</div>

<?php
// Render footer
echo $OUTPUT->footer([
    'site_name' => 'DevFramework',
    'footer_links' => [
        ['url' => '/privacy.php', 'label' => 'Privacy'],
        ['url' => '/terms.php',   'label' => 'Terms'],
        ['url' => 'https://github.com/', 'label' => 'GitHub'],
    ],
]);

