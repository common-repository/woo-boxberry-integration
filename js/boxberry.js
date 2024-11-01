document.addEventListener('click', function(e) {
    if (e.which != 1){
        return;
    }
    if (e.target && (e.target instanceof HTMLElement) && e.target.getAttribute('data-boxberry-open') == 'true') {
        e.preventDefault();
        if(document.getElementById('bxb_point')){
            document.getElementById('bxb_point').remove();
        }
        var selectPointLink = e.target;

        (function(selectedPointLink) {
            var elements = document.querySelectorAll('a[data-boxberry-open="true"]');
            for (var i = 0; i < elements.length; i++) {
                elements.item(i).textContent = 'Выберите пункт выдачи';
            }
            var city = selectPointLink.getAttribute('data-boxberry-city') || undefined;
            var method = selectPointLink.getAttribute('data-method');
            var token = selectPointLink.getAttribute('data-boxberry-token');
            var targetStart = selectedPointLink.getAttribute('data-boxberry-target-start');
            var weight = selectPointLink.getAttribute('data-boxberry-weight');

            var surch = selectedPointLink.getAttribute('data-surch');

            var paymentSum = selectPointLink.getAttribute('data-paymentsum');
            var orderSum = selectPointLink.getAttribute('data-ordersum');
            var height = selectPointLink.getAttribute('data-height');
            var width = selectPointLink.getAttribute('data-width');
            var depth = selectPointLink.getAttribute('data-depth');
            var api = selectPointLink.getAttribute('data-api-url');
            var boxberryPointSelectedHandler = function (result) {

                var addresSplit = result.address.split(',');
                var insertAddres = 'ПВЗ: ' + addresSplit[2].trim() + addresSplit[3];

                var selectedPointName = result.name + ' (' + result.address + ')';

                if (document.getElementById('shipping_address_1')){
                     document.getElementById('shipping_address_1').value = insertAddres;
                            if (document.getElementById('billing_address_1')){
                                document.getElementById('billing_address_1').value = insertAddres;
                            }
                } else {
                    if (document.getElementById('billing_address_1')){
                        document.getElementById('billing_address_1').value = insertAddres;
                    }
                }

                selectedPointLink.parentNode.insertAdjacentHTML('afterbegin','<div id="bxb_point"></div>');

                selectedPointLink.style.color = 'inherit';

                selectedPointLink.textContent = selectedPointName;

                var xhr = new XMLHttpRequest();

                xhr.open('POST', window.wp_data.ajax_url, true);
                var fd = new FormData;
                fd.append('action','boxberry_update');
                fd.append('method', method);
                fd.append('code', result.id);
                fd.append('address', selectedPointName);
                xhr.send(fd);
                };
            boxberry.versionAPI(api);
            boxberry.checkLocation(1);
            boxberry.sucrh(surch);
            boxberry.open(boxberryPointSelectedHandler, token, city, targetStart, orderSum, weight, paymentSum, height, width, depth);
        })(selectPointLink);
    }
}, true);

function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function (n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}

jQuery(document).on('click','#place_order',function(e){

    var elements = jQuery('.shipping_method');
    for (var i = 0; i < elements.length; i++) {
     if (elements[i].checked === true && elements[i].id.indexOf('boxberry_self') && !document.getElementById('bxb_point')){
         elements[i].parentNode.childNodes[5].childNodes[0].attributes[2].value = 'color:#FF0000';
         return false;
        }
    }
});

jQuery(document).on('click','.shipping_method',function(){
    if (document.getElementById('shipping_address_1') && document.getElementById('shipping_address_1').value.indexOf('ПВЗ:')>=0){
        document.getElementById('shipping_address_1').value = '';
        if (document.getElementById('billing_address_1') && document.getElementById('billing_address_1').value.indexOf('ПВЗ:')>=0){
            document.getElementById('billing_address_1').value = '';
        }
    } else {
        if (document.getElementById('billing_address_1') && document.getElementById('billing_address_1').value.indexOf('ПВЗ:')>=0){
            document.getElementById('billing_address_1').value = '';
        }
    }
});

jQuery( document ).ready(function() {
    var upd = 0;
    if (location.pathname == '/checkout/' && upd == 0){
        upd = 1;
        if (jQuery('#billing_city')){
            jQuery('#billing_city').val('');
        }
        if (jQuery('#shipping_city')){
            jQuery('#shipping_city').val('');
        }
    } else {
        upd = 0;
    }

	jQuery('#billing_postcode').on('blur',function(){
		if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
			jQuery( document.body ).trigger( 'update_checkout' );
		}
	});
	jQuery('#billing_state').on('blur',function(){
		if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
			jQuery( document.body ).trigger( 'update_checkout' );
		}
	});
	jQuery('#billing_city').on('blur',function(){
		if (!jQuery('#ship-to-different-address-checkbox').prop('checked')){
			jQuery( document.body ).trigger( 'update_checkout' );
		}
	});
	jQuery('#shipping_city').on('focusout',function(){
		jQuery( document.body ).trigger( 'update_checkout' );
	});
	jQuery('#shipping_state').on('focusout',function(){
		jQuery( document.body ).trigger( 'update_checkout' );
	});
	jQuery('#shipping_postcode').on('focusout',function(){
		jQuery( document.body ).trigger( 'update_checkout' );
	});
});
