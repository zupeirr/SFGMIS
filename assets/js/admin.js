/* ==========================================================================
   assets/js/admin.js  —  Admin Dashboard JavaScript
   ==========================================================================

   PURPOSE:
     This file adds interactive behaviour to all admin panel pages.

   WHAT IT DOES:
     1. Sidebar toggle on mobile (hamburger button in the top bar)
     2. Collapsible nav groups in the sidebar (expand/collapse sections)
     3. Delete confirmation dialogs (prevent accidental deletions)
     4. Live image preview when uploading a property image
     5. Auto-hide success/error alert messages after 4.5 seconds
     6. Tab navigation for Settings and Reports pages (read URL hash too)

   HOW IT'S LOADED:
     At the bottom of admin/includes/admin-footer.php:
         <script src="../assets/js/admin.js"></script>

   NOTE: All code runs inside DOMContentLoaded so the HTML is fully
   ready before we try to find or interact with elements on the page.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. SIDEBAR TOGGLE ───────────────────────────────────────────────
       Two behaviours depending on screen size:

       DESKTOP (>900px):  Toggles between expanded (250px) and collapsed
       (68px icon-only rail) by adding/removing "sidebar-collapsed" on
       <body>. State is persisted in localStorage so it survives page loads.

       MOBILE (<=900px):  Classic slide-in/out via "open" on the sidebar. */

    const sidebarToggle = document.getElementById('sidebarToggle'); // The ☰ button in the top bar
    const sidebar = document.getElementById('adminSidebar');   // The left-side navigation panel
    const COLLAPSED_KEY = 'sidebarCollapsed';                        // localStorage key

    function isDesktop() {
        return window.innerWidth > 900;
    }

    // Restore desktop collapsed state from localStorage on page load
    if (localStorage.getItem(COLLAPSED_KEY) === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            if (isDesktop()) {
                // Desktop: toggle collapsed rail
                const nowCollapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(COLLAPSED_KEY, nowCollapsed ? 'true' : 'false');
                // Also close any open dropdown groups when collapsing
                if (nowCollapsed) {
                    document.querySelectorAll('.nav-group.open').forEach(function (g) {
                        g.classList.remove('open');
                    });
                }
            } else {
                // Mobile: slide sidebar in/out
                sidebar.classList.toggle('open');
            }
        });
    }

    // If the window is resized across the 900px breakpoint, clean up state
    window.addEventListener('resize', function () {
        if (!isDesktop()) {
            // On mobile, remove the desktop collapsed class from body
            // (CSS overrides handle hiding it anyway, but keep body clean)
        } else {
            // On desktop, close the mobile open state
            sidebar.classList.remove('open');
        }
    });


    /* ── 2. SIDEBAR DROPDOWN GROUPS ──────────────────────────────────────
       The sidebar contains collapsible sections (e.g. Properties, Finance).
       Each section has a toggle button (.nav-group-toggle) that expands or
       collapses the child links (.nav-group-children) by toggling "open"
       on the parent .nav-group container.

       The "open" class on .nav-group is also set in PHP (sidebar.php) for
       whichever section is currently active, so the right group starts open. */

    document.querySelectorAll('.nav-group-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            // When sidebar is in collapsed rail mode on desktop, clicking a group icon should navigate to its primary page
            if (document.body.classList.contains('sidebar-collapsed')) {
                const parentGroup = this.closest('.nav-group');
                const firstChild = parentGroup ? parentGroup.querySelector('.nav-group-children a') : null;
                if (firstChild && firstChild.getAttribute('href')) {
                    e.preventDefault();
                    window.location.href = firstChild.getAttribute('href');
                    return;
                }
            }
            // Otherwise toggle accordion open/close
            this.closest('.nav-group').classList.toggle('open');
        });
    });


    /* ── 3. DELETE CONFIRMATION ──────────────────────────────────────────
       Any link or button with class="js-confirm-delete" and
       data-label="this property" will trigger a browser confirm() dialog.

       If the admin clicks "Cancel", e.preventDefault() stops the link
       from navigating, preventing the accidental delete.

       HTML EXAMPLE:
         <a href="delete-property.php?id=5"
            class="js-confirm-delete"
            data-label="Sunset Villa">Delete</a> */

    document.querySelectorAll('.js-confirm-delete').forEach(function (link) {
        link.addEventListener('click', function (e) {
            const label = link.getAttribute('data-label') || 'this item'; // Friendly name for the item
            // confirm() shows a native browser popup — returns true if OK, false if Cancel
            if (!confirm('Are you sure you want to delete ' + label + '? This action cannot be undone.')) {
                e.preventDefault(); // Cancel the navigation / deletion
            }
        });
    });


    /* ── 4. LIVE IMAGE PREVIEW ────────────────────────────────────────────
       On the Add Property and Edit Property pages, when the admin selects
       an image file, a live preview appears immediately without uploading.

       HOW IT WORKS:
         FileReader reads the selected file as a data URL (base64 string),
         then sets that as the <img> src so it displays instantly.

       HTML EXPECTED:
         <input type="file" id="propertyImage">
         <img id="imagePreview" style="display:none;"> */

    const imageInput = document.getElementById('propertyImage'); // The file input
    const imagePreview = document.getElementById('imagePreview');  // The preview <img> element

    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            const file = imageInput.files[0]; // Get the first selected file
            if (file) {
                const reader = new FileReader(); // FileReader reads file contents in the browser
                reader.onload = function (e) {
                    imagePreview.src = e.target.result; // Set the preview src to the base64 data URL
                    imagePreview.style.display = 'block'; // Make the preview visible
                };
                reader.readAsDataURL(file); // Start reading the file as a base64 URL
            }
        });
    }


    /* ── 5. AUTO-HIDE ALERTS ─────────────────────────────────────────────
       Success and error alerts (.alert) are shown after form submissions.
       After 4.5 seconds they fade out automatically so they don't clutter
       the screen if the admin doesn't close them manually.

       HOW IT WORKS:
         setTimeout delays 4500ms, then adds an opacity transition + sets
         opacity to 0 for a fade effect. After the 0.4s fade, remove() deletes
         the element from the DOM entirely. */

    document.querySelectorAll('.alert').forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease'; // Smooth 0.4s fade-out
            alert.style.opacity = '0';                  // Start fading
            setTimeout(function () {
                alert.remove(); // Remove from DOM after fade completes
            }, 400); // 400ms = matches the CSS transition duration above
        }, 4500); // Wait 4.5 seconds before starting the fade
    });


    /* ── 6. TAB NAVIGATION ───────────────────────────────────────────────
       Used on the Settings page and the Reports & Analytics page.
       These pages have multiple sections that are shown/hidden as "tabs".

       FEATURES:
         - Clicking a tab link (data-tab="section-id") shows that section
         - URL hash (#section-id) is updated so direct links work
         - On page load, the hash from the URL is read to activate the right tab
         - If no hash, the first tab is activated by default

       HTML EXAMPLE:
         <a href="#general" data-tab="general">General</a>
         <div id="general" class="settings-section">...</div>

       SIDEBAR LINKS like <a href="settings.php#notifications"> work because
       the hash is read on page load and the correct tab is shown. */

    /**
     * activateTab() — Show one tab section and hide all others.
     *
     * @param {string} tabId - The id of the section to show (e.g. 'general').
     */
    function activateTab(tabId) {
        var sections = document.querySelectorAll('.settings-section'); // All tab content panels
        var tabLinks = document.querySelectorAll('[data-tab]');         // All tab buttons/links
        if (!sections.length) return; // Exit if no tabs exist on this page

        // Hide ALL sections first
        sections.forEach(function (s) {
            s.style.display = 'none';
            s.classList.remove('active');
        });
        // Remove 'active' highlight from all tab links
        tabLinks.forEach(function (a) { a.classList.remove('active'); });

        // Show the requested tab section
        var target = document.getElementById(tabId);
        if (target) {
            target.style.display = ''; // Show the section
            target.classList.add('active');
        } else if (sections[0]) {
            // If the requested ID doesn't exist, fall back to the first section
            sections[0].style.display = '';
            sections[0].classList.add('active');
        }

        // Highlight the matching tab link
        var activeLink = document.querySelector('[data-tab="' + tabId + '"]');
        if (activeLink) activeLink.classList.add('active');
    }

    // When a tab link is clicked, activate that tab and update the URL hash
    document.querySelectorAll('[data-tab]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault(); // Stop the link from jumping to the hash anchor
            var tabId = this.getAttribute('data-tab');
            activateTab(tabId);
            // Update the URL hash without reloading the page (allows bookmarking)
            history.replaceState(null, '', '#' + tabId);
        });
    });

    // On page load: read the URL hash and activate the matching tab
    // This makes direct links like "settings.php#notifications" work correctly
    (function () {
        var tabLinks = document.querySelectorAll('[data-tab]');
        if (!tabLinks.length) return; // No tabs on this page — stop here

        var hash = window.location.hash.replace('#', ''); // Remove the # from the hash

        if (hash) {
            activateTab(hash); // Activate the tab matching the URL hash
        } else {
            // No hash in URL — activate the first tab by default
            var first = tabLinks[0];
            if (first) activateTab(first.getAttribute('data-tab'));
        }
    })(); // IIFE — runs immediately

}); // End DOMContentLoaded

