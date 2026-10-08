/* ==========================================================================
   BUBAPC - Website behaviour
   Plain JavaScript, no jQuery. Every part checks that the element it needs
   is really on the page, so the same file is safe to load anywhere.
   ========================================================================== */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        /* ------------------------------------------------------------------
           1. DARK MODE
           The colour is set before the page is painted by a tiny script in
           the head. This part only handles the button.
           ------------------------------------------------------------------ */
        var themeToggle = document.getElementById('theme-toggle');

        if (themeToggle) {
            var applyIcon = function () {
                var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                themeToggle.querySelector('.fa-moon').style.display = isDark ? 'none' : '';
                themeToggle.querySelector('.fa-sun').style.display = isDark ? '' : 'none';
            };

            applyIcon();

            themeToggle.addEventListener('click', function () {
                var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                var next = isDark ? 'light' : 'dark';

                document.documentElement.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('bubapc-theme', next);
                } catch (error) {
                    /* Private browsing can block local storage. Not a problem. */
                }
                applyIcon();
            });
        }

        /* ------------------------------------------------------------------
           2. ADMIN SIDE MENU (slides in on phones)
           ------------------------------------------------------------------ */
        var sidebar = document.getElementById('admin-sidebar');
        var sidebarBackdrop = document.querySelector('.admin-sidebar-backdrop');

        var setSidebar = function (open) {
            if (!sidebar) {
                return;
            }
            sidebar.classList.toggle('is-open', open);
            if (sidebarBackdrop) {
                sidebarBackdrop.hidden = !open;
            }
        };

        document.querySelectorAll('[data-sidebar-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                setSidebar(true);
            });
        });

        document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                setSidebar(false);
            });
        });

        /* ------------------------------------------------------------------
           3. SHOW AND HIDE PASSWORDS
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.querySelector(button.getAttribute('data-toggle-password'));
                if (!field) {
                    return;
                }
                var isHidden = field.getAttribute('type') === 'password';
                field.setAttribute('type', isHidden ? 'text' : 'password');
                button.setAttribute('aria-label', isHidden ? 'Hide the password' : 'Show the password');
            });
        });

        /* ------------------------------------------------------------------
           4. ASK BEFORE SOMETHING DESTRUCTIVE HAPPENS
           Any link or button with data-confirm shows a message first.
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-confirm]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                if (!window.confirm(element.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });

        /* ------------------------------------------------------------------
           5. COPY A LINK TO THE CLIPBOARD
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-copy-link]').forEach(function (button) {
            button.addEventListener('click', function () {
                var link = button.getAttribute('data-copy-link');
                var feedback = document.querySelector('[data-copy-feedback]');

                var showFeedback = function () {
                    if (!feedback) {
                        return;
                    }
                    feedback.textContent = 'Link copied';
                    window.setTimeout(function () {
                        feedback.textContent = '';
                    }, 2500);
                };

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(link).then(showFeedback);
                } else {
                    /* Older browsers: copy through a hidden text box. */
                    var helper = document.createElement('textarea');
                    helper.value = link;
                    document.body.appendChild(helper);
                    helper.select();
                    document.execCommand('copy');
                    document.body.removeChild(helper);
                    showFeedback();
                }
            });
        });

        /* ------------------------------------------------------------------
           6. BACK TO TOP BUTTON
           ------------------------------------------------------------------ */
        var backToTop = document.getElementById('back-to-top');

        if (backToTop) {
            window.addEventListener('scroll', function () {
                backToTop.hidden = window.scrollY < 500;
            }, { passive: true });

            backToTop.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        /* ------------------------------------------------------------------
           7. READING PROGRESS BAR (on the devotional page)
           ------------------------------------------------------------------ */
        var readingProgress = document.getElementById('reading-progress');

        if (readingProgress) {
            var updateProgress = function () {
                var article = document.getElementById('devotional-article');
                if (!article) {
                    return;
                }

                var articleTop = article.offsetTop;
                var articleHeight = article.offsetHeight;
                var viewport = window.innerHeight;

                var scrolled = window.scrollY - articleTop + viewport * 0.35;
                var percent = Math.min(100, Math.max(0, (scrolled / articleHeight) * 100));

                readingProgress.style.width = percent + '%';
            };

            window.addEventListener('scroll', updateProgress, { passive: true });
            window.addEventListener('resize', updateProgress);
            updateProgress();
        }

        /* ------------------------------------------------------------------
           8. COUNTDOWN TO AN EVENT
           Give any element data-countdown="2026-10-04T18:00:00". The boxes
           are plain Bootstrap badges, so no CSS is needed for them. The
           background is the theme's own surface colour rather than
           text-bg-light, so the boxes are dark when the page is dark.
           ------------------------------------------------------------------ */
        var countdowns = document.querySelectorAll('[data-countdown]');

        if (countdowns.length) {
            var unit = function (value, label) {
                return '<span class="badge rounded-3 bg-body-tertiary text-body border px-3 py-2 lh-sm">'
                    + '<span class="d-block fs-6 fw-bold">' + value + '</span>'
                    + '<span class="d-block text-body-secondary fw-normal">' + label + '</span>'
                    + '</span>';
            };

            var tickCountdown = function () {
                countdowns.forEach(function (element) {
                    var target = new Date(element.getAttribute('data-countdown'));
                    var distance = target.getTime() - Date.now();

                    if (isNaN(distance)) {
                        return;
                    }

                    if (distance <= 0) {
                        element.innerHTML = '<span class="badge rounded-pill badge-gold">Happening now</span>';
                        return;
                    }

                    var seconds = Math.floor(distance / 1000);
                    var days = Math.floor(seconds / 86400);
                    var hours = Math.floor((seconds % 86400) / 3600);
                    var minutes = Math.floor((seconds % 3600) / 60);

                    element.innerHTML = unit(days, 'days') + unit(hours, 'hrs') + unit(minutes, 'min');
                });
            };

            tickCountdown();
            window.setInterval(tickCountdown, 30000);
        }

        /* ------------------------------------------------------------------
           9. NEWSLETTER SIGN UP
           The form posts in the background so the page never reloads.
           It still works without JavaScript, because the same form is a
           normal POST to newsletter-subscribe.
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-newsletter-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var message = form.querySelector('[data-newsletter-message]');
                var button = form.querySelector('button[type="submit"]');
                var buttonText = form.querySelector('.newsletter-button-text');
                var email = form.querySelector('input[name="email"]');

                if (!email.value) {
                    return;
                }

                var originalText = buttonText ? buttonText.textContent : '';
                if (buttonText) {
                    buttonText.textContent = 'Please wait';
                }
                button.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        message.className = 'newsletter-message small mt-2 mb-0 text-' + (data.ok ? 'success' : 'danger');
                        message.textContent = data.message;

                        if (data.ok) {
                            form.reset();
                        }
                    })
                    .catch(function () {
                        message.className = 'newsletter-message small mt-2 mb-0 text-danger';
                        message.textContent = 'Something went wrong. Please try again.';
                    })
                    .then(function () {
                        button.disabled = false;
                        if (buttonText) {
                            buttonText.textContent = originalText;
                        }
                    });
            });
        });

        /* ------------------------------------------------------------------
           10. IMAGE UPLOAD WITH DRAG AND DROP
           A file input inside a .dropzone becomes a drop zone that shows
           a preview of the chosen image before it is uploaded.
           ------------------------------------------------------------------ */
        document.querySelectorAll('.dropzone').forEach(function (zone) {
            var input = zone.querySelector('input[type="file"]');
            var preview = zone.querySelector('.dropzone-preview');
            var previewImage = zone.querySelector('.dropzone-preview img');
            var removeButton = zone.querySelector('.dropzone-remove');

            if (!input) {
                return;
            }

            var showFile = function (file) {
                if (!file || !previewImage) {
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (event) {
                    previewImage.src = event.target.result;
                    zone.classList.add('has-image');
                };
                reader.readAsDataURL(file);
            };

            input.addEventListener('change', function () {
                showFile(input.files[0]);
            });

            /* Clicking anywhere in the zone opens the file picker. */
            zone.addEventListener('click', function (event) {
                if (event.target === removeButton) {
                    return;
                }
                if (zone.classList.contains('has-image') && event.target !== input) {
                    return;
                }
                input.click();
            });

            ['dragenter', 'dragover'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.add('is-dragging');
                });
            });

            ['dragleave', 'drop'].forEach(function (name) {
                zone.addEventListener(name, function (event) {
                    event.preventDefault();
                    zone.classList.remove('is-dragging');
                });
            });

            zone.addEventListener('drop', function (event) {
                if (event.dataTransfer && event.dataTransfer.files.length) {
                    input.files = event.dataTransfer.files;
                    showFile(input.files[0]);
                }
            });

            if (removeButton) {
                removeButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    input.value = '';
                    if (previewImage) {
                        previewImage.removeAttribute('src');
                    }
                    zone.classList.remove('has-image');
                });
            }
        });

        /* ------------------------------------------------------------------
           11. IMAGE PICKER
           A select box where choosing an option shows the matching image.
           Used for the placeholder art on a new record.
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-image-picker]').forEach(function (select) {
            var target = document.getElementById(select.getAttribute('data-image-picker'));

            if (!target) {
                return;
            }

            select.addEventListener('change', function () {
                if (select.value) {
                    target.src = select.value;
                }
            });
        });

        /* ------------------------------------------------------------------
           12. GALLERY MODAL
           A photograph button carries the two things the modal needs:
             data-gallery-image    the full size address of the photo
             data-gallery-caption  the words to show above it
           Bootstrap itself opens and closes the modal, so all this does is
           put the chosen photograph into it.
           ------------------------------------------------------------------ */
        var galleryModal = document.getElementById('gallery-photo');

        if (galleryModal) {
            var galleryImage = galleryModal.querySelector('img');
            var galleryCaption = galleryModal.querySelector('#gallery-photo-caption');

            document.querySelectorAll('[data-gallery-image]').forEach(function (button) {
                button.addEventListener('click', function () {
                    galleryImage.src = button.getAttribute('data-gallery-image');
                    galleryImage.alt = button.getAttribute('data-gallery-caption') || '';
                    galleryCaption.textContent = button.getAttribute('data-gallery-caption') || '';
                });
            });
        }

        /* ------------------------------------------------------------------
           13. FILTER BAR HELP
           When a filter changes it submits its form straight away.
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-auto-submit]').forEach(function (element) {
            element.addEventListener('change', function () {
                if (element.form) {
                    element.form.submit();
                }
            });
        });

        /* ------------------------------------------------------------------
           14. SLUG PREVIEW
           When a title is typed, show the URL it will get. Useful in the
           admin forms, so nothing has to be typed twice.

           Only while the address has still to be made. PHP writes
           [data-slug-new] on the preview for that case only; on an edit the
           address is already saved and fixed, and typing the title over it
           would show an address the page does not actually have.
           ------------------------------------------------------------------ */
        var slugSource = document.querySelector('[data-slug-source]');
        var slugPreview = document.querySelector('[data-slug-preview][data-slug-new]');

        if (slugSource && slugPreview) {
            var updateSlug = function () {
                var text = slugSource.value.toLowerCase().trim()
                    .replace(/[^a-z0-9\s\-_]/g, '')
                    .replace(/[\s\-_]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                slugPreview.textContent = text === '' ? 'your-title-here' : text;
            };

            slugSource.addEventListener('input', updateSlug);
            updateSlug();
        }

        /* ------------------------------------------------------------------
           15. GIVE A FEEDBACK AFTER A ROW ACTION
           A tiny "Saved" note under a form, so a click always feels like
           something happened even when the page barely moves.
           ------------------------------------------------------------------ */
        document.querySelectorAll('[data-submit-on-change]').forEach(function (element) {
            element.addEventListener('change', function () {
                if (element.form) {
                    element.form.submit();
                }
            });
        });
    });
})();
