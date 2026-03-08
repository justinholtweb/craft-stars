/* Stars plugin CP scripts */

(function() {
    'use strict';

    // Interactive star selector for the edit page
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.querySelector('.stars-selector');
        if (!container) return;

        var input = container.querySelector('input[type="hidden"]');
        var buttons = container.querySelectorAll('.star-btn');

        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var value = parseInt(this.dataset.value, 10);
                input.value = value;
                updateStars(buttons, value);
            });

            btn.addEventListener('mouseenter', function() {
                var value = parseInt(this.dataset.value, 10);
                previewStars(buttons, value);
            });
        });

        container.addEventListener('mouseleave', function() {
            var currentValue = parseInt(input.value, 10);
            updateStars(buttons, currentValue);
        });

        function updateStars(buttons, value) {
            buttons.forEach(function(btn) {
                var btnValue = parseInt(btn.dataset.value, 10);
                btn.classList.toggle('active', btnValue <= value);
                btn.textContent = btnValue <= value ? '\u2605' : '\u2606';
            });
        }

        function previewStars(buttons, value) {
            buttons.forEach(function(btn) {
                var btnValue = parseInt(btn.dataset.value, 10);
                btn.textContent = btnValue <= value ? '\u2605' : '\u2606';
            });
        }
    });
})();
