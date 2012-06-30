// Function from http://www.quirksmode.org/js/cookies.html
function wp_selective_reading_create_cookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

// Function from http://www.quirksmode.org/js/cookies.html
function wp_selective_reading_read_cookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function wp_selective_reading_set_category_state( $categoryID, $newState ) {
	// Update show/hide links
	var elements = document.getElementsByClassName('wp-selective-reading-toggle-' + $categoryID);
	for( var i = 0; i < elements.length; i++ ) {
		elements[i].innerHTML = ($newState == 1 ? '(hide)' : '(show)');
		elements[i].onclick = function() { wp_selective_reading_set_category_state($categoryID, ($newState == 0 ? '1' : '0')); };
	}
	
	// Set cookie appropriately
	wp_selective_reading_create_cookie('wp-selective-reading-' + $categoryID, ($newState == 1 ? '1' : '0'), 30);
}
