$(document).ready(function(){
	$('#claimlist .sortable th span[data-sort]').click(function() {
		if ($(this).hasClass('unsortable')) {
			return;
		}
		var sort = $(this).attr('data-sort');
		var selected = $(this).attr('data-selected');
		var dir = $('#claimlist .sortable').attr('data-sort-dir');

		if (selected == 'true' && dir == 'asc') {
			dir = 'desc';
		} else if (selected == 'true' && dir == 'desc') {
			dir = 'asc';
		} else {
			dir = 'asc';
		}
		document.location.href = document.location.protocol + '//' + document.location.host + document.location.pathname + "?sort=" + sort + '&sort_dir=' + dir;
	});
});
