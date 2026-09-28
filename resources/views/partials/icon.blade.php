@php
$paths = [
'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
'building'=>'M4 21V3h12v18 M16 9h4v12 M1 21h22 M8 7h4 M8 11h4 M8 15h4 M9 21v-3h2v3',
'clipboard'=>'M9 3H5v18h14V3h-4 M9 2h6v4H9z M8 11h8 M8 15h6',
'layers'=>'m12 3 10 5-10 5L2 8z M2 12l10 5 10-5 M2 16l10 5 10-5',
'calendar'=>'M3 5h18v16H3z M16 3v4 M8 3v4 M3 11h18 M8 15h2 M14 15h2',
'message'=>'M21 11a9 9 0 0 1-9 9H3l1.5-4A9 9 0 1 1 21 11Z M8 10h8 M8 14h5',
'folder'=>'M3 6h7l2 2h9v12H3z M3 6V4h6l2 2',
'chart'=>'M4 3v18h17 M8 16v-5 M13 16V7 M18 16v-8',
'mail'=>'M3 5h18v14H3z m0 0 9 7 9-7',
'edit'=>'m15 4 5 5 M4 20l5-1L21 7l-5-5L4 14z',
'book'=>'M12 5v16 M12 5C8 2 4 3 2 4v15c4-2 7-1 10 2 3-3 6-4 10-2V4c-2-1-6-2-10 1',
'settings'=>'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1z',
'trash'=>'M3 6h18 M5 6l1 15h12l1-15 M9 6V3h6v3 M10 10v7 M14 10v7',
'logout'=>'M9 3H3v18h6 M9 12h12 m-4-4 4 4-4 4',
'arrow'=>'M4 12h16 m-6-6 6 6-6 6',
'check'=>'m5 12 4 4L19 6',
'clock'=>'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18 M12 7v5l3 2',
'plus'=>'M12 5v14 M5 12h14',
'shield'=>'m12 3 9 4v5c0 5-9 9-9 9s-9-4-9-9V7z m-4 9 3 3 5-6',
];
@endphp<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>