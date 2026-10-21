/* ==========================================================================
   assets/js/main.js  —  Public-Facing Site JavaScript
   ==========================================================================

   PURPOSE:
     This file adds interactive behaviour to the public website pages
     (index.php, properties.php, contact.php, etc.).

   WHAT IT DOES:
     1. Mobile navigation toggle (hamburger menu open/close)
     2. Contact form client-side validation (before sending to server)
     3. Search form cleanup (remove empty fields from the URL)
     4. Animated number counters (stats strip on the home page)

   HOW IT'S LOADED:
     At the bottom of includes/footer.php:
         <script src="assets/js/main.js"></script>

   NOTE: All code runs inside DOMContentLoaded so we know the HTML is
   fully ready before we try to find elements on the page.
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* ── 1. MOBILE NAVIGATION TOGGLE ─────────────────────────────────────
       The hamburger button (#navToggle) is only visible on small screens.
       Clicking it toggles the "open" CSS class on the nav menu (#navMenu),
       which slides/shows the navigation links.
       We also lock page scrolling while the menu is open so the background
       doesn't scroll behind the overlay. */

    const navToggle = document.getElementById('navToggle'); // The ☰ hamburger button
    const navMenu   = document.getElementById('navMenu');   // The slide-in nav panel

    if (navToggle && navMenu) { // Only run if both elements exist on the page
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('open');   // Add or remove the 'open' class
            navToggle.classList.toggle('open'); // Animate the burger → X icon

            if (navMenu.classList.contains('open')) {
                document.body.style.overflow = 'hidden'; // Prevent background scroll when menu is open
            } else {
                document.body.style.overflow = ''; // Restore scrolling when menu closes
            }
        });
    }


    /* ── 2. CONTACT FORM CLIENT-SIDE VALIDATION ──────────────────────────
       Before the contact form is submitted to the server, we do a quick
       check in JavaScript to catch obvious mistakes (empty fields, bad email).

       WHY CLIENT-SIDE:
         Gives immediate feedback without a page reload. The server still
         validates too (in contact.php) \u2014 this is just a convenience layer.

       NOTE: The form must have id="contactForm" in the HTML. */

    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            // Get references to the form fields we need to validate
            const name    = contactForm.querySelector('[name="name"]');
            const email   = contactForm.querySelector('[name="email"]');
            const message = contactForm.querySelector('[name="message"]');

            let valid    = true;   // Assume valid until a check fails
            let errorMsg = '';

            // Check 1: Name must not be empty
            if (!name.value.trim()) {
                valid = false;
                errorMsg = 'Please enter your name.';
            }
            // Check 2: Email must look like a real email address
            // The regex /^[^\s@]+@[^\s@]+\.[^\s@]+$/ checks for:
            //   [^\s@]+   = one or more chars that are not space or @
            //   @         = the @ symbol
            //   [^\s@]+   = domain name
            //   \.        = a dot
            //   [^\s@]+   = top-level domain (e.g. com, net)
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                valid = false;
                errorMsg = 'Please enter a valid email address.';
            }
            // Check 3: Message must not be empty
            else if (!message.value.trim()) {
                valid = false;
                errorMsg = 'Please write your message.';
            }

            // If any check failed, stop the form submission and show the error
            if (!valid) {
                e.preventDefault(); // Cancel the form POST
                showFormError(contactForm, errorMsg);
            }
        });
    }

    /**
     * showFormError() — Create or update an error box inside a form.
     *
     * If a .js-error-box already exists in the form, we reuse it.
     * Otherwise we create a new <div> and insert it at the top of the form.
     *
     * @param {HTMLFormElement} form - The form element.
     * @param {string}          msg  - The error message to display.
     */
    function showFormError(form, msg) {
        let box = form.querySelector('.js-error-box'); // Look for an existing error box
        if (!box) {
            // No box yet \u2014 create a new one and add it to the top of the form
            box = document.createElement('div');
            box.className = 'alert alert-error js-error-box';
            form.prepend(box); // prepend = insert as the first child
        }
        box.textContent = msg; // Set the error text (textContent is safe \u2014 no HTML injection)
    }


    /* ── 3. SEARCH FORM CLEANUP ──────────────────────────────────────────
       The property search form (id="searchForm") submits via GET, which
       adds all fields to the URL as query parameters.

       If the user leaves a field empty, we remove its "name" attribute
       before submission so empty params (e.g. ?location=&type=) don't
       appear in the URL \u2014 keeping URLs clean and meaningful. */

    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            // Loop through all inputs and selects in the form
            searchForm.querySelectorAll('input, select').forEach(function (el) {
                // If the field has no value, remove its name so it won't appear in the URL
                if (!el.value) el.removeAttribute('name');
            });
            // The form then submits normally with only filled fields in the URL
        });
    }


    /* ── 4. ANIMATED NUMBER COUNTERS ─────────────────────────────────────
       On the home page there is a "stats strip" showing counts like
       "42 Properties", "18 For Sale", etc.

       These counters animate from 0 up to their target value when they
       scroll into view (using IntersectionObserver).

       HOW IT WORKS:
         - Each counter element has class="counter" and data-target="42"
         - IntersectionObserver fires when an element enters the viewport
         - requestAnimationFrame creates a smooth 1-second counting animation
         - Each counter only animates once (observer.unobserve stops it repeating)

       HTML EXAMPLE:
         <h3 class="counter" data-target="42">0</h3>   */

    const counters = document.querySelectorAll('.counter'); // Find all counter elements

    if (counters.length > 0) {
        // IntersectionObserver options:
        // root: null          = observe relative to the browser viewport
        // rootMargin: '0px'   = no extra margin around the viewport
        // threshold: 0.1      = fire when 10% of the element is visible
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        // Create the observer — it calls our function when elements enter the screen
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { // The counter is now visible in the viewport
                    const counter  = entry.target;
                    const target   = +counter.getAttribute('data-target'); // +  converts string to number
                    const duration = 1000; // Animation runs for 1000ms = 1 second
                    const suffix   = counter.getAttribute('data-suffix') || ''; // Optional suffix like '%'
                    let startTime  = null;

                    // This function is called by requestAnimationFrame on every animation frame (~60fps)
                    const step = (timestamp) => {
                        if (!startTime) startTime = timestamp; // Record the start time on first frame

                        // Calculate how far through the animation we are (0.0 to 1.0)
                        const progress   = Math.min((timestamp - startTime) / duration, 1);
                        // Calculate what the current displayed number should be
                        const currentVal = Math.floor(progress * target);
                        counter.innerText = currentVal + suffix;

                        if (progress < 1) {
                            window.requestAnimationFrame(step); // Keep animating until done
                        } else {
                            counter.innerText = target + suffix; // Ensure exact final value
                        }
                    };

                    window.requestAnimationFrame(step); // Start the animation
                    observer.unobserve(counter); // Stop watching this counter (animate only once)
                }
            });
        }, observerOptions);

        // Tell the observer to watch each counter element
        counters.forEach(counter => {
            observer.observe(counter);
        });
    }

}); // End DOMContentLoaded

