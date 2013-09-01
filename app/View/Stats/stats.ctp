<h4>Stats and Graphs</h4>
<div class="span12">
	<form>
		<select class="pull-right" name="filter" onchange="this.parentElement.submit()">
			<?php foreach($filter_times as $key => $value) {
				echo "<option value='$key'";
				if ($selected_filter === $key) {
					echo " selected";
				}
				echo ">" . $value["label"] . "</option>";
			} ?>
		</select>
	</form>
</div>
<p id="no_data">There were no incidents reported in this time period</p>
<span id="graphs">
	<span class="span12" id="linechart" style="height: 350px"></span>
</span>
<script type="text/javascript">
	var no_data = true;
  <?php 
		echo $this->Reports->getChartArray("chartArray", $columns,
      $related_entries);
		echo "\n";
		echo $this->Reports->getLineChartData("linechart_data", $download_stats);

		if (count($download_stats) > 0) {
			echo "no_data = false;";
		}
	?>
  window.onload = function () {
		if(no_data) {
			return;
		} else {
			$('#no_data').remove();
		}
    chartArray.forEach(function(chart) {
      var span_id = "graph_" + chart.name;
      var $span = $("<span class='span5'>").attr("id", span_id);
      $("#graphs").append($span);
      piechart(span_id, chart.title, chart.values, chart.labels);
    });

		$.jqplot('linechart', [linechart_data], {
			title:'<h3>Incident frequency</h3>',
			axes:{
        xaxis:{
            renderer: $.jqplot.DateAxisRenderer,
						rendererOptions:{
							tickRenderer:$.jqplot.CanvasAxisTickRenderer
						},
						tickOptions: {
							fontSize:'12pt', 
							fontFamily:'Tahoma', 
							angle: -40
						}
        },
        yaxis:{
						rendererOptions:{
							tickRenderer:$.jqplot.CanvasAxisTickRenderer
						},
						tickOptions: {
							fontSize:'12pt', 
							fontFamily:'Tahoma', 
							markSize: 7,
						},
        }
			},
			series: [{
				markerOptions: {
					show: true
				},
				pointLabels: { show: false }
			}],
			highlighter: { show: true }
		});
  };
</script>
