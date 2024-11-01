var firstScriptElement = document.getElementsByTagName("script")[0];
var scriptElement = document.createElement("script");
var placeScript = function () {
    firstScriptElement.parentNode.insertBefore(scriptElement, firstScriptElement);
};
scriptElement.type = "text/javascript";
scriptElement.src = (document.location.protocol == "https:" ? "https:" : "http:") + "//points.boxberry.de/js/boxberry.js";
if (window.opera == "[object Opera]") {
    document.addEventListener("DOMContentLoaded", placeScript, false);
} else {
    placeScript();
}
document.addEventListener('click', function(e) {
    if (e.target && (e.target instanceof HTMLElement) && e.target.getAttribute('data-boxberry-open') == 'true') {
        e.preventDefault();

        var selectPointLink = e.target;
        (function(selectedPointLink) {

            var city = selectPointLink.getAttribute('data-boxberry-city') || undefined;
            var token = selectPointLink.getAttribute('data-boxberry-token');
            var data_id = selectPointLink.getAttribute('data-id');
            var boxberryPointSelectedHandler = function (result) {

                var selectedPointName = result.name + ' (' + result.address + ')';
                selectedPointLink.textContent = selectedPointName;
                var xhr = new XMLHttpRequest();

                xhr.open('POST', window.wp_data.ajax_url, true);
                var fd = new FormData;

                fd.append('action','boxberry_admin_update');
                fd.append('id', data_id);
                fd.append('code', result.id);
                fd.append('address', selectedPointName);
                xhr.onreadystatechange = function()
                {
                    if (xhr.readyState == 4 && xhr.status == 200)
                    {
                        location.reload();
                    }
                }
                xhr.send(fd);
            };

            boxberry.open(boxberryPointSelectedHandler,'', city);
        })(selectPointLink);
    }
}, true);