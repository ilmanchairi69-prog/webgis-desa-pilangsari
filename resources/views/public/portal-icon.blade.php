<svg class="portal-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('map') <path d="m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2zM9 3v16M15 5v16"/> @break
        @case('pin') <path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/> @break
        @case('arrow') <path d="M4 12h16m-6-6 6 6-6 6"/> @break
        @case('chevron') <path d="m6 9 6 6 6-6"/> @break
        @case('menu') <path d="M4 6h16M4 12h16M4 18h16"/> @break
        @case('house') <path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/> @break
        @case('shield') <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/> @break
        @case('book') <path d="M12 5C9 3 5 3 2 4v16c3-1 7-1 10 1 3-2 7-2 10-1V4c-3-1-7-1-10 1zm0 0v16"/> @break
        @case('search') <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/> @break
        @case('globe') <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/> @break
    @endswitch
</svg>
