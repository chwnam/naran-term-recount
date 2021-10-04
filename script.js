(function ($) {
    $(document).ready(function () {
        $('form').submit(function (e) {
            if (!confirm('Did you backup your database? Are you sure?')) {
                e.preventDefault();
                return false;
            }
        });
    });
})(jQuery);