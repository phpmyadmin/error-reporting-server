$(document).ready(function() {
	oTable = $('#reports_table').dataTable( {
		"bProcessing": true,
		"bServerSide": true,
		"sAjaxSource": $('#reports_table').data('ajax-url'),
    "aoColumnDefs": [
      { "bSearchable": false, "aTargets": [ 0 ] },
      { "sClass": "centered", "aTargets": [ -1, -2 ] },
    ],
    "aoColumns": [
      { "sWidth": "10%" },
      { "sWidth": "20%" },
      { "sWidth": "40%" },
      { "sWidth": "15%" },
      { "sWidth": "10%" },
    ],
    "fnServerData": function (sSource, aoData, fnCallback) {
      $.getJSON( sSource, aoData, function (json) {
          fnCallback(json);
      });
    },
	} );
  oTable.find("input").on('keyup', function(e) {
    // only search when enter is pressed
    if(e.keyCode == 13) {
      oTable.fnFilter($(this).val(), oTable.find("th").index($(this).parent()));
    }
  });
  oTable.find("select").on('change', function(e) {
    oTable.fnFilter($(this).val(), oTable.find("th").index($(this).parent()));
  });
} );
