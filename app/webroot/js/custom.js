$(document).ready(function() {
	oTable = $('#reports_table').dataTable( {
		"bProcessing": true,
		"bServerSide": true,
		"sAjaxSource": $('#reports_table').data('ajax-url'),
    "aoColumnDefs": [
      { "bSearchable": false, "aTargets": [ 0 ] },
      { "sClass": "center", "aTargets": [ -1, -2 ] },
      { "fnRender": function ( oObj ) {
					return '<a href="/reports/view/' + oObj.aData[0] + '">'+oObj.aData[0]+'</a>';
				},
				"aTargets": [ 0 ]
      },
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

  $('#toggle-stacktrace').click(function(e) {
    if($('#stacktrace').hasClass('shown')) {
      $('#toggle-stacktrace').html('Show stacktrace');

      $('#stacktrace').slideUp(function() {
        $(this).removeClass('shown');
      })
    } else {
      $('#toggle-stacktrace').html('Hide stacktrace');

      $('#stacktrace').slideDown(function() {
        $(this).addClass('shown');
      })
    }
    return false;
  });

  setTimeout(function(){$(".alert").slideUp()}, 2000)
} );
