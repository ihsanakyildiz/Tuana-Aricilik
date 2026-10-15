(function () {
    var frame = document.getElementById("paytriframe");
    if (!frame) {
        return;
    }
    var script = document.createElement("script");
    script.src = "https://www.paytr.com/js/iframeResizer.min.js";
    script.onload = function () {
        if (typeof iFrameResize === "function") {
            iFrameResize({}, "#paytriframe");
        }
    };
    document.body.appendChild(script);
}());
