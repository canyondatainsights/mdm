{{-- Auto-logout the admin after 15 minutes with no *user* activity. Timer resets only on real input
     events (not background Livewire polling), so an idle tab logs out even while the Sources table polls. --}}
<script>
(function () {
    var IDLE_MS = 15 * 60 * 1000;
    var LOGOUT_URL = @js(filament()->getCurrentPanel()?->getLogoutUrl() ?? url('/admin/logout'));
    var TOKEN = @js(csrf_token());
    var timer = null;

    function logout() {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = LOGOUT_URL;
        var t = document.createElement('input');
        t.type = 'hidden'; t.name = '_token'; t.value = TOKEN;
        form.appendChild(t);
        document.body.appendChild(form);
        form.submit();
    }

    function reset() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(logout, IDLE_MS);
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function (evt) {
        window.addEventListener(evt, reset, { passive: true });
    });
    reset();
})();
</script>
