// Compose/reply reuses Core's own <x-k::editor> (Tiptap) and its compiled `editor` bundle --
// see email-client.md's UX plan, step 6. Nothing to bundle here yet.

// Selecting a thread only swaps #mail-reading-pane (see messages/list.blade.php) -- the list
// itself never re-renders, so its own active-row highlight has to be kept in sync here rather
// than server-side. Blade still computes the correct initial state on first page load; this
// just takes over for every click after that.
//
// Assigned onto `window` explicitly -- this file loads as an ES module (Vite's own convention,
// `<script type="module">`), and a module's top-level declarations are scoped to the module
// itself, never exposed globally the way a classic script's would be. The Blade template calls
// this from a plain inline `onclick="..."` attribute, which can only reach `window`-scoped names.
window.kopMailSelectThread = function kopMailSelectThread(link) {
    document.querySelectorAll('#mail-message-list .kop-mail-thread-active').forEach((row) => {
        row.classList.remove('kop-mail-thread-active');
        row.querySelector('[data-thread-chevron]')?.classList.add('kop-mail-chevron-hidden');
    });

    const row = link.closest('li');
    row.classList.add('kop-mail-thread-active');
    row.querySelector('[data-thread-chevron]')?.classList.remove('kop-mail-chevron-hidden');
};
