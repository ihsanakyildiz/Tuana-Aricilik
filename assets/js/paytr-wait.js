(function () {
    var root = document.querySelector("[data-paytr-wait]");
    if (!root) {
        return;
    }
    var key = "paytr-wait:" + window.location.pathname + window.location.search;
    var count = parseInt(window.sessionStorage.getItem(key) || "0", 10);
    if (!count || count < 0) {
        count = 0;
    }
    if (count >= 5) {
        return;
    }
    window.setTimeout(function () {
        window.sessionStorage.setItem(key, String(count + 1));
        window.location.reload();
    }, 4000);
}());
