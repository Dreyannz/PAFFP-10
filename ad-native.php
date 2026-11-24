<?php
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow,noarchive,nosnippet,noimageindex">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            overflow: hidden;
            background: transparent;
        }

        #container-56654e454952e0df06d7ceb45e6398ad {
            width: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
<script async="async"
        data-cfasync="false"
        src="https://pl31668689.profitableratecpmnetwork.com/56654e454952e0df06d7ceb45e6398ad/invoke.js"></script>
<div id="container-56654e454952e0df06d7ceb45e6398ad"></div>

<script>
(function () {
    function reportHeight() {
        var height = Math.max(
            document.body.scrollHeight,
            document.documentElement.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.offsetHeight
        );

        window.parent.postMessage({
            type: "paffp-native-ad-height",
            height: height
        }, "*");
    }

    window.addEventListener("load", function () {
        reportHeight();
        setTimeout(reportHeight, 500);
        setTimeout(reportHeight, 1500);
        setTimeout(reportHeight, 3000);
    });

    if ("ResizeObserver" in window) {
        var observer = new ResizeObserver(reportHeight);
        observer.observe(document.documentElement);
        observer.observe(document.body);
    } else {
        setInterval(reportHeight, 1000);
    }
})();
</script>
</body>
</html>
