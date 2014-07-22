$(document).ready(function () {
	oTable = $('#reports_table').dataTable({
		"bSortCellsTop": true,
		"bProcessing": true,
		"bServerSide": true,
		"sAjaxSource": $('#reports_table').data('ajax-url'),
		"aoColumnDefs": [
			{ "bSearchable": false, "aTargets": [ 1 ] },
			{ "sClass": "center", "aTargets": [ 0, 1, 4, 5, 6] },
			{ "fnRender": function (oObj) {
					return '<a class="block" href="/reports/view/' + oObj.aData[1] +
						'">' + oObj.aData[1] + '</a>';
				},
				"aTargets": [ 1 ]
			}
		],
		"aoColumns": [
			{ "sWidth": "1%" },
			{ "sWidth": "10%" },
			{ "sWidth": "20%" },
			{ "sWidth": "40%" },
			{ "sWidth": "15%" },
			{ "sWidth": "10%" },
			{ "sWidth": "10%" }
		],
		"fnServerData": function (sSource, aoData, fnCallback) {
			$.getJSON(sSource, aoData, function (json) {
				fnCallback(json);
			});
		}
	});

	$('#notifications_table').dataTable({
		"bSortCellsTop": true,
		"bProcessing": true,
		"bServerSide": true,
		"sAjaxSource": $('#notifications_table').data('ajax-url'),
		"aoColumnDefs": [
			{ "bSearchable": false, "aTargets": [ 1 ] },
			{ "sClass": "center", "aTargets": [ 0, 1, 2, 3, 4, 5 ] }
		],
		"aoColumns": [
			{ "sWidth": "5%" },
			{ "sWidth": "10%" },
			{ "sWidth": "15%" },
			{ "sWidth": "30%" },
			{ "sWidth": "10%" },
			{ "sWidth": "10%" },
			{ "sWidth": "10%" }
		],
		"fnServerData": function (sSource, aoData, fnCallback) {
			$.getJSON(sSource, aoData, function (json) {
				fnCallback(json);
				// setup necessary CSS for linkable rows.
				$('#notifications_table tbody tr').hover(function() {
					$(this).css('cursor', 'pointer');
				}, function() {
					$(this).css('cursor', 'auto');
				});
				// Stop Redirecting upon checkbox click event
				$('#notifications_table td input').click(function (e) {
					e.stopPropagation();
				});
			});
		},
		"fnRowCallback": function( nRow, aData, iDisplayIndex ) {
			// click on the row anywhere to go to the report.
			$(nRow).click(function () {
				// extract the href from the anchor string
				document.location.href = $($.parseHTML(aData[1])).attr('href');
			});
		}
	});

	oTable.find("input").on('keyup', function (e) {
		// only search when enter is pressed
		if (e.keyCode == 13) {
			oTable.fnFilter($(this).val(), oTable.find("tr:last-child th").index($(this).parent()));
		}
	});

	oTable.find("select").on('change', function (e) {
		oTable.fnFilter($(this).val(), oTable.find("tr:last-child th").index($(this).parent()));
	});

	$('#toggle-stacktrace').click(function (e) {
		if ($('#stacktrace').hasClass('shown')) {
			$('#toggle-stacktrace').html('Show stacktrace');

			$('#stacktrace').slideUp(function () {
				$(this).removeClass('shown');
			});
		} else {
			$('#toggle-stacktrace').html('Hide stacktrace');

			$('#stacktrace').slideDown(function () {
				$(this).addClass('shown');
			});
		}
		return false;
	});

		$('#resultsForm_checkall').click(function () {
			if($(this).attr('checked') == 'checked') {
				$('#reports_table td input').attr('checked', 'checked');
			} else {
				$('#reports_table td input').removeAttr('checked');
			}
		});

	// display notifications count
	if (notifications_count > 0)
	{
		$('#nav_notifications a').html($('#nav_notifications a').html() + '(' + notifications_count + ')');
	}

	setTimeout(
		function () {
			$(".alert.alert-success").slideUp();
		},
		2000
	);

	SyntaxHighlighter.defaults.toolbar = false;
	SyntaxHighlighter.all();
});

function showStateForm() {
	$('#state-form').slideToggle();
}
