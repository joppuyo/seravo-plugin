/*
 * Description: common JS file for the ApexCharts on Seravo Plugin.
 */

/**
 * Load the charts
 */
jQuery(document).ready(
  function () {
    jQuery('[data-section="chart-test"]').on('seravoAjaxSuccess', generate_test_chart);
    jQuery('[data-section="disk-usage"]').on('seravoAjaxSuccess', generate_disk_donut);
    jQuery('[data-section="disk-usage"]').on('seravoAjaxSuccess', generate_disk_bars);
    jQuery('[data-section="cache-status-ajax"]').on('seravoAjaxSuccess', generate_redis_hitchart);
    jQuery('[data-section="cache-status-ajax"]').on('seravoAjaxSuccess', generate_http_hitchart);
    jQuery('[data-section="table-sizes"]').on('seravoAjaxSuccess', generate_database_bars);
  }
);

/**
 * Test chart for demonstration
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_test_chart(event, response) {
  series = [];
  Object.entries(response.random_data).forEach(
    function (entry) {
      series.push(
        {
          name: entry[0],
          data: [entry[1]],
        }
      )
    }
  );

  var options = {
    series: series,
    chart: {
      type: 'bar',
      height: 150,
      stacked: true,
      stackType: '100%',
      toolbar: {
        show: false
      }
    },
    plotOptions: {
      bar: {
        horizontal: true,
      },
    },
    stroke: {
      width: 1,
      colors: ['#fff']
    },
    yaxis: {
      show: false
    },
    tooltip: {
      y: {
        formatter: function (val) {
          return val
        }
      },
      x: {
        show: false
      }
    },
    fill: {
      opacity: 1
    },
    legend: {
      position: 'bottom',
      horizontalAlign: 'left',
      offsetX: 0
    },
    colors: ['#6aa84f', '#ef7c1a']
  };

  new ApexCharts(document.querySelector('#test-page-test-chart'), options).render();
};

/**
 * Generate database diskbars.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_database_bars(event, response) {
  var data = [];
  var labels = [];
  var human_vals = [];

  var axis = {
    categories: labels,
    axisBorder: {
      show: false
    },
    axisTicks: {
      show: false
    },
    labels: {
      show: false
    }
  };

  Object.keys(response.folders).forEach(
    function (folder) {
      data.push(response.folders[folder].percentage);
      labels.push(folder);
      human_vals.push(response.folders[folder].human);
    }
  );

  // Sort the data in a descending order
  var list = [];
  var dataItems = data.length;
  for (var j = 0; j < dataItems; j++) {
    list.push({ 'datapoint': data[j], 'label': labels[j], 'human': human_vals[j] });
  }

  list.sort(
    function (a, b) {
      return ((a.datapoint > b.datapoint) ? -1 : ((a.datapoint == b.datapoint) ? 0 : 1));
    }
  );

  for (var k = 0; k < dataItems; k++) {
    data[k] = list[k].datapoint;
    labels[k] = list[k].label;
    human_vals[k] = list[k].human;
  }

  var options = {
    series: [{
      data: data
    }],
    colors: ['#44A1CB'],
    chart: {
      type: 'bar',
      height: labels.length * 40,
      toolbar: {
        show: false,
      }
    },
    plotOptions: {
      bar: {
        horizontal: true,
        dataLabels: {
          position: 'bottom'
        }
      }
    },
    dataLabels: {
      enabled: true,
      textAnchor: 'start',
      style: {
        colors: ['#444']
      },
      formatter: function (val, opt) {
        return opt.w.globals.labels[opt.dataPointIndex] + ": " + human_vals[opt.dataPointIndex];
      },
      offsetX: 0,
      dropShadow: {
        enabled: false
      }
    },
    xaxis: axis,
    yaxis: axis,
    grid: {
      show: false,
      padding: {
        left: 0,
        right: 0,
        top: 0,
        bottom: 0
      }
    },
    tooltip: {
      enabled: true,
      shared: true,
      intersect: false,
      custom: function ({ series, seriesIndex, dataPointIndex, w }) {
        return '<div class="arrow-box">' +
          '<span>' + w.globals.labels[dataPointIndex] + ": " + human_vals[dataPointIndex] + '</span>' +
          '</div>'
      },
    },
    legend: {
      show: false
    }
  };

  var chart = new ApexCharts(document.querySelector("#database-bars-single"), options);
  chart.render();
}

/**
 * Generate disk donut for disk space usage.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_disk_donut(event, response) {
  var disk_use;
  var max_disk;

  Object.keys(response.data).forEach(
    function (data) {
      disk_use = response.data['size'] / 1e9;
      max_disk = response.data['disk_limit'];
    }
  );

  var available = max_disk - disk_use;
  var colors = ['#ef7c1a', '#47aedc'];

  if (available < 0) {
    var available = 0;
    var colors = ['#cc0000'];
    jQuery('#disk-use-notification').css("display", "block");
  }

  // Generate donut chart
  var options = {
    series: [disk_use, available],
    colors: colors,
    chart: {
      type: 'donut',
      height: 120,
    },
    labels: [seravo_charts_l10n.used, seravo_charts_l10n.available],
    responsive: [{
      breakpoint: 480
    }],
    legend: {
      show: false
    },
    dataLabels: {
      enabled: false,
    },
    tooltip: {
      enabled: true,
      x: {
        show: false
      },
      y: {
        formatter: function (value) {
          return value.toFixed(2) + ' GB';
        }
      },
    }
  };

  new ApexCharts(document.querySelector("#disk-usage-donut"), options).render();
}

/**
 * Generate the basic disk bars.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_disk_bars(event, response) {
  var data = [];
  var labels = [];
  var human_vals = [];

  var axis = {
    categories: labels,
    axisBorder: {
      show: false
    },
    axisTicks: {
      show: false
    },
    labels: {
      show: false,
    }
  };

  Object.keys(response.folders).forEach(
    function (folder) {
      data.push(response.folders[folder].percentage);
      labels.push(folder);
      human_vals.push(response.folders[folder].human);
    }
  );

  var options = {
    series: [{
      data: data
    }],
    colors: ['#44A1CB'],
    chart: {
      type: 'bar',
      height: labels.length * 40,
      toolbar: {
        show: false,
      }
    },
    plotOptions: {
      bar: {
        horizontal: true,
        dataLabels: {
          position: 'bottom',
        },
        barWidth: '30%',
      }
    },
    dataLabels: {
      enabled: true,
      textAnchor: 'start',
      style: {
        colors: ['#444']
      },
      formatter: function (val, opt) {
        return opt.w.globals.labels[opt.dataPointIndex] + ": " + human_vals[opt.dataPointIndex];
      },
      offsetX: 0,
      dropShadow: {
        enabled: false
      }
    },
    xaxis: axis,
    yaxis: axis,
    grid: {
      show: false,
      padding: {
        left: 0,
        right: 0,
        top: 0,
        bottom: 0
      }
    },
    tooltip: {
      enabled: true,
      shared: true,
      intersect: false,
      custom: function ({ series, seriesIndex, dataPointIndex, w }) {
        return '<div class="arrow-box">' +
          '<span>' + w.globals.labels[dataPointIndex] + ": " + human_vals[dataPointIndex] + '</span>' +
          '</div>'
      },
    }
  };

  var chart = new ApexCharts(document.querySelector("#disk-bars-single"), options);
  chart.render();
}

/**
 * Generate Redis hitchart.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_redis_hitchart(event, response) {
  var hits = response.redis_data['hits'];
  var misses = response.redis_data['misses'];

  var options = {
    series: [{
      name: seravo_charts_l10n.keyspace_hits,
      data: [hits]
    }, {
      name: seravo_charts_l10n.keyspace_misses,
      data: [misses]
    }],
    chart: {
      type: 'bar',
      height: 150,
      stacked: true,
      stackType: '100%',
      toolbar: {
        show: false
      }
    },
    plotOptions: {
      bar: {
        horizontal: true,
      },
    },
    stroke: {
      width: 1,
      colors: ['#fff']
    },
    yaxis: {
      show: false
    },
    tooltip: {
      y: {
        formatter: function (val) {
          return val
        }
      },
      x: {
        show: false
      }
    },
    fill: {
      opacity: 1
    },
    legend: {
      position: 'bottom',
      horizontalAlign: 'left',
      offsetX: 0
    },
    colors: ['#6aa84f', '#ef7c1a']
  };

  var redis_hit_chart = new ApexCharts(document.querySelector("#redis-hit-rate-chart"), options);
  redis_hit_chart.render();
};

/**
 * Generate HTTP hitchart.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_http_hitchart(event, response) {
  var hits = response.http_data.hit;
  var misses = response.http_data.miss;
  var stales = response.http_data.stale;

  var options = {
    series: [{
      name: seravo_charts_l10n.hits,
      data: [hits]
    }, {
      name: seravo_charts_l10n.misses,
      data: [misses]
    }, {
      name: seravo_charts_l10n.stales,
      data: [stales]
    }],
    chart: {
      type: 'bar',
      height: 150,
      stacked: true,
      stackType: '100%',
      toolbar: {
        show: false
      }
    },
    plotOptions: {
      bar: {
        horizontal: true,
      },
    },
    stroke: {
      width: 1,
      colors: ['#fff']
    },
    yaxis: {
      show: false
    },
    tooltip: {
      y: {
        formatter: function (val) {
          return val
        }
      },
      x: {
        show: false
      }
    },
    fill: {
      opacity: 1
    },
    legend: {
      position: 'bottom',
      horizontalAlign: 'left',
      offsetX: 0
    },
    colors: ['#6aa84f', '#ef7c1a', '#47aedc']
  };

  var http_hit_chart = new ApexCharts(document.querySelector("#http-hit-rate-chart"), options);
  http_hit_chart.render();
};

/**
 * Generate speed test chart.
 * @param {Event}  event    Trigger event for the function.
 * @param {Object} response Response of the AJAX call containing chart data.
 */
function generate_speed_test_chart(speedChart, speedData, speedDataCached) {
  // If an instance of the chart already exists, wipe it out
  if (speedChart instanceof ApexCharts) {
    speedChart.destroy();
    speedData = [];
    speedDataCached = [];
  }

  var options = {
    series: [{
      name: seravo_charts_l10n.latency,
      data: speedData,
    },
    {
      name: seravo_charts_l10n.cached_latency,
      data: speedDataCached,
    }
    ],
    chart: {
      type: 'line',
      height: 350,
      zoom: {
        enabled: false
      }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        endingShape: 'flat',
      },
    },
    dataLabels: {
      enabled: false
    },
    stroke: {
      show: true,
      width: 2,
      curve: 'smooth'
    },
    yaxis: {
      title: {
        text: 'ms'
      },
      min: 0
    },
    xaxis: {
      min: 1,
      max: speedNumberOfTests,
      tickAmount: speedNumberOfTests,
      tickPlacement: 'on'
    },
    fill: {
      opacity: 1
    },
    tooltip: {
      y: {
        formatter: function (val) {
          return val + " ms"
        }
      }
    },
    legend: {
      onItemClick: {
        toggleDataSeries: false
      },
    }
  };

  speedChart = new ApexCharts(document.querySelector("#speed-test-results"), options);
  speedChart.render();

  return { 'chart': speedChart, 'data': speedData, 'data_cached': speedDataCached };
}
