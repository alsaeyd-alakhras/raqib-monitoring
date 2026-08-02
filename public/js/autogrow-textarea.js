document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-autogrow]').forEach(function (textarea) {
        const resize = function () {
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(textarea.scrollHeight, textarea.offsetHeight) + 'px';
        };

        textarea.addEventListener('input', resize);
        resize();
    });
});
