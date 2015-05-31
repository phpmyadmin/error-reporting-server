function piechart(element_id, title, values, labels, radius) {
  radius = radius || 100;
  var rad = Math.PI / 180,
      width = $('#' + element_id).width(),
      pincrease = 0.1,
      height = radius * (1 + pincrease) * 2 + 50,
      r = Raphael(element_id, width, height),
      delta = radius * pincrease + 20,
      y = radius + 50,
      x = 500 - radius * (1 + pincrease) - 55;
  var pie = r.piechart(
    x,
    y,
    radius,
    values,
    {
      legend: labels,
      legendpos: "west",
    }
  );

  // Move the legend away a bit
  pie.labels.transform("...T-"+delta+",0");

  r.text(x, 10, title).attr({ font: "20px sans-serif" });

  // pie.each(function() {
  //   var label = labels[this.value.order];
  //   this.sector.label = r.text(
  //     this.cx + (this.r + delta + 5) * Math.cos(-this.mangle * rad),
  //     this.cy + (this.r + delta + 5) * Math.sin(-this.mangle * rad),
  //     label).attr({
  //       fill: this.sector.attr("fill"),
  //       opacity: 0,
  //       stroke: "yellow",
  //       "stroke-width": 0.1,
  //       "font-size": 20,
  //       "font-weight": "bolder",
  //     });
  // });
  
  // Adapted from one of the demos on g.raphael website
  // http://g.raphaeljs.com/piechart2.html
  pie.hover(function () {
      this.sector.stop();
      this.sector.transform('s' + (1 + pincrease) + ' ' + (1 + pincrease) +
          ' ' + this.cx + ' ' + this.cy);
      if (this.label) {
        this.label[0].stop();
        this.label[0].attr({ r: 7.5 });
        this.label[1].attr({ "font-weight": 800 });
      }
  }, function () {
      this.sector.animate({ transform: 's1 1 ' + this.cx + ' ' + this.cy }, 500, "bounce");
      if (this.label) {
        this.label[0].animate({ r: 5 }, 500, "bounce");
        this.label[1].attr({ "font-weight": 400 });
      }
  });
}
