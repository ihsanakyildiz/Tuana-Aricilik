<?php

declare(strict_types=1);

function admin_icon(string $name): string
{
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/>',
        'list' => '<path d="M9 7h11M9 12h11M9 17h11"/><circle cx="5" cy="7" r="1"/><circle cx="5" cy="12" r="1"/><circle cx="5" cy="17" r="1"/>',
        'pages' => '<path d="M7 3.5h7l5 5V20a1.5 1.5 0 0 1-1.5 1.5h-10.5A1.5 1.5 0 0 1 5.5 20V5A1.5 1.5 0 0 1 7 3.5z"/><path d="M14 3.5V9h5.5"/>',
        'image' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m6 16 3.2-3.2a1 1 0 0 1 1.4 0L16 18"/><path d="m13 14 1.4-1.4a1 1 0 0 1 1.4 0L20 16.5"/>',
        'layers' => '<path d="M12 3.5 20.5 8 12 12.5 3.5 8 12 3.5z"/><path d="M3.5 12 12 16.5 20.5 12"/><path d="M3.5 16 12 20.5 20.5 16"/>',
        'cards' => '<rect x="3" y="6" width="13" height="14" rx="2"/><path d="M8 6V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-1"/>',
        'article' => '<path d="M6 4h12a1.5 1.5 0 0 1 1.5 1.5v13A1.5 1.5 0 0 1 18 20H6A1.5 1.5 0 0 1 4.5 18.5v-13A1.5 1.5 0 0 1 6 4z"/><path d="M8 8.5h8M8 12h8M8 15.5h5"/>',
        'bag' => '<path d="M6 8h12l-.8 11.2a1.5 1.5 0 0 1-1.5 1.3H8.3a1.5 1.5 0 0 1-1.5-1.3L6 8z"/><path d="M9 8V6.8A3 3 0 0 1 12 3.8 3 3 0 0 1 15 6.8V8"/>',
        'folder' => '<path d="M3.5 7.5A1.5 1.5 0 0 1 5 6h4l2 2h8a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5v-10z"/>',
        'receipt' => '<path d="M7 3.5h10a1 1 0 0 1 1 1V21l-2-1.2-2 1.2-2-1.2-2 1.2-2-1.2L6 21V4.5a1 1 0 0 1 1-1z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'file' => '<path d="M7 3.5h6.5L19 9v10.5A1.5 1.5 0 0 1 17.5 21h-10A1.5 1.5 0 0 1 6 19.5v-14A1.5 1.5 0 0 1 7.5 4z"/><path d="M13.5 3.5V9H19"/>',
        'plus' => '<path d="M12 6v12M6 12h12"/>',
        'users' => '<circle cx="9" cy="9" r="3"/><path d="M4.5 18.5a4.5 4.5 0 0 1 9 0"/><circle cx="16.5" cy="9.5" r="2.2"/><path d="M16 14.2a3.8 3.8 0 0 1 4 3.3"/>',
        'cart' => '<circle cx="9" cy="19" r="1.3"/><circle cx="17" cy="19" r="1.3"/><path d="M4 5h2l1.6 9.2a1.5 1.5 0 0 0 1.5 1.3H17a1.5 1.5 0 0 0 1.5-1.2L20 8H7"/>',
        'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
        'star' => '<path d="m12 3.8 2.1 4.4 4.8.7-3.5 3.4.8 4.8L12 15l-4.2 2.1.8-4.8L5.1 8.9l4.8-.7L12 3.8z"/>',
        'archive' => '<path d="M4 8h16v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5V8z"/><path d="M3 4.5h18V8H3zM10 12h4"/>',
        'chart' => '<path d="M4 19V5M4 19h16"/><path d="m7 14 3.2-3.2 2.6 2.2L18 8"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M4.8 6.8l1.6 1.6M17.6 15.6l1.6 1.6M3.5 12h2.2M18.3 12h2.2M4.8 17.2l1.6-1.6M17.6 8.4l1.6-1.6"/>',
        'message' => '<path d="M5 6.5h14a1.5 1.5 0 0 1 1.5 1.5v7A1.5 1.5 0 0 1 19 16.5H9l-4 3v-3H5A1.5 1.5 0 0 1 3.5 15V8A1.5 1.5 0 0 1 5 6.5z"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/>',
        'bell' => '<path d="M6 16.5h12l-1.2-1.6V11a4.8 4.8 0 0 0-9.6 0v3.9L6 16.5z"/><path d="M10 18.2a2 2 0 0 0 4 0"/>',
        'sun' => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3.5v2M12 18.5v2M4.8 4.8l1.4 1.4M17.8 17.8l1.4 1.4M3.5 12h2M18.5 12h2M4.8 19.2l1.4-1.4M17.8 6.2l1.4-1.4"/>',
        'refresh' => '<path d="M19 12a7 7 0 1 1-2-4.9"/><path d="M19 4.5V8h-3.5"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'calendar' => '<rect x="4" y="5.5" width="16" height="14" rx="2"/><path d="M8 3.5v4M16 3.5v4M4 10h16"/>',
        'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.5l3 2"/>',
        'more' => '<circle cx="6" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="18" cy="12" r="1.2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'export' => '<path d="M12 4v10"/><path d="m8 8 4-4 4 4"/><path d="M5 15v3.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'import' => '<path d="M12 14V4"/><path d="m8 10 4 4 4-4"/><path d="M5 15v3.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'trend' => '<path d="M4 16.5 9.2 11l3.2 3L20 7"/><path d="M14 7h6v6"/>',
        'logout' => '<path d="M10 5H7.5A1.5 1.5 0 0 0 6 6.5v11A1.5 1.5 0 0 0 7.5 19H10"/><path d="M11 12h8"/><path d="m16 8 4 4-4 4"/>',
        'external' => '<path d="M10 6H6.5A1.5 1.5 0 0 0 5 7.5v10A1.5 1.5 0 0 0 6.5 19h10a1.5 1.5 0 0 0 1.5-1.5V14"/><path d="M13 5h6v6"/><path d="M19 5 11 13"/>',
        'globe' => '<circle cx="12" cy="12" r="8"/><path d="M4 12h16M12 4c2.2 2.4 2.2 13.6 0 16M12 4c-2.2 2.4-2.2 13.6 0 16"/>',
        'drop' => '<path d="M12 3.2c3.2 4.6 6.2 8 6.2 11.2a6.2 6.2 0 0 1-12.4 0C5.8 11.2 8.8 7.8 12 3.2z"/>',
        'lock' => '<rect x="6" y="10" width="12" height="9" rx="2"/><path d="M8.5 10V8a3.5 3.5 0 0 1 7 0v2"/>',
        'eye' => '<path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="2.5"/>',
        'pencil' => '<path d="M4 20h4l11-11-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/>',
        'draft' => '<path d="M7 4h7l4 4v12H7z"/><path d="M14 4v4h4"/><path d="M9.5 13h5M9.5 16h3"/>',
        'trash' => '<path d="M5 7h14"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7"/><path d="M7 7l.8 12.2A1.5 1.5 0 0 0 9.3 20.5h5.4a1.5 1.5 0 0 0 1.5-1.3L17 7"/>',
    ];

    $body = $icons[$name] ?? $icons['grid'];

    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $body . '</svg>';
}
