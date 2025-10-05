<?php
/**
 * Default theme navigation configuration.
 * Returned structure:
 *  [ 'nav' => [...], 'drawer' => [...] ]
 * Both arrays contain associative arrays with: url, label, (optional) active, icon (drawer only)
 */
return [
    'nav' => [
        [ 'url' => '/index.php',         'label' => 'Home' ],
        [ 'url' => '/dashboard.php',     'label' => 'Dashboard' ],
        [ 'url' => '/theme-example.php', 'label' => 'Theme Demo' ],
    ],
    'drawer' => [
        [
            'url' => '/dashboard.php',
            'label' => 'Dashboard',
            'icon' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10Z"/><path d="M3 21h8v-6H3v6Z"/><path d="M13 21h8V3h-8v18Z"/></svg>'
        ],
        [
            'url' => '/theme-example.php',
            'label' => 'Theme Demo',
            'icon' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>'
        ],
        [
            'url' => '/settings.php',
            'label' => 'Settings',
            'icon' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6h.09A1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09c0 .69.4 1.31 1.01 1.51H15a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.69 0 1.31.4 1.51 1.01V10c0 .74.56 1.37 1.29 1.46H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.54Z"/></svg>'
        ],
    ],
];

