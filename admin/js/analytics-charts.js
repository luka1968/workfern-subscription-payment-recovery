/**
 * Recovery Analytics Charts.
 *
 * Renders Chart.js charts on the Recovery Analytics Dashboard.
 *
 * Charts:
 *  1. Recovery Rate â€?doughnut chart
 *  2. Failed vs Recovered Payments â€?bar chart
 *  3. Recovered Revenue Trend â€?line chart
 *
 * Data is fetched from the WordPress REST API endpoint:
 *   /wp-json/workfern/v1/analytics
 *
 * @since 1.0.0
 * @package WooStripeRecoveryPro
 */

/* global Chart, workfernAnalytics */

/* <fs_premium_only> */
(function () {
	'use strict';

	/*
	|--------------------------------------------------------------------------
	| Configuration
	|--------------------------------------------------------------------------
	*/

	var API_ENDPOINT = (workfernAnalytics && workfernAnalytics.restUrl)
		? workfernAnalytics.restUrl
		: '/wp-json/workfern/v1/analytics';

	var NONCE = (workfernAnalytics && workfernAnalytics.nonce)
		? workfernAnalytics.nonce
		: '';

	var COLORS = {
		blue:       'rgba(0, 115, 170, 1)',
		blueFaded:  'rgba(0, 115, 170, 0.15)',
		green:      'rgba(70, 180, 80, 1)',
		greenFaded: 'rgba(70, 180, 80, 0.15)',
		red:        'rgba(220, 50, 50, 1)',
		redFaded:   'rgba(220, 50, 50, 0.15)',
		purple:     'rgba(130, 110, 180, 1)',
		purpleFaded:'rgba(130, 110, 180, 0.15)',
		yellow:     'rgba(255, 185, 0, 1)',
		grey:       'rgba(200, 200, 200, 1)',
		greyFaded:  'rgba(200, 200, 200, 0.3)',
	};

	var FONT_FAMILY = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

	/*
	|--------------------------------------------------------------------------
	| Chart.js Global Defaults
	|--------------------------------------------------------------------------
	*/

	Chart.defaults.font.family = FONT_FAMILY;
	Chart.defaults.font.size   = 13;
	Chart.defaults.color       = '#50575e';
	Chart.defaults.plugins.legend.labels.usePointStyle = true;
	Chart.defaults.plugins.legend.labels.padding       = 16;
	Chart.defaults.plugins.tooltip.cornerRadius        = 6;
	Chart.defaults.plugins.tooltip.padding             = 10;

	/*
	|--------------------------------------------------------------------------
	| Initialization
	|--------------------------------------------------------------------------
	*/

	document.addEventListener('DOMContentLoaded', function () {
		fetchAnalyticsData()
			.then(function (data) {
				renderRecoveryRateChart(data);
				renderFailedVsRecoveredChart(data);
				renderRevenueTrendChart(data);
			})
			.catch(function (err) {
				console.error('[WORKFERN Analytics] Failed to load chart data:', err);
				showFallbackMessage();
			});
	});

	/*
	|--------------------------------------------------------------------------
	| Data Fetching
	|--------------------------------------------------------------------------
	*/

	/**
	 * Fetch analytics data from the REST API.
	 *
	 * @return {Promise<Object>} The analytics data.
	 */
	function fetchAnalyticsData() {
		var headers = {
			'Content-Type': 'application/json',
		};

		if (NONCE) {
			headers['X-WP-Nonce'] = NONCE;
		}

		return fetch(API_ENDPOINT, {
			method:      'GET',
			credentials: 'same-origin',
			headers:     headers,
		})
		.then(function (response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		});
	}

	/**
	 * Show a fallback message if data cannot be loaded.
	 */
	function showFallbackMessage() {
		var containers = document.querySelectorAll('.workfern-chart-container');
		containers.forEach(function (el) {
			el.innerHTML = '<p style="text-align:center;color:#646970;padding:40px 0;">Unable to load chart data.</p>';
		});
	}

	/*
	|--------------------------------------------------------------------------
	| 1. Recovery Rate Chart (Doughnut)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render the Recovery Rate doughnut chart.
	 *
	 * @param {Object} data Analytics data from the REST API.
	 */
	function renderRecoveryRateChart(data) {
		var canvas = document.getElementById('workfern-chart-recovery-rate');
		if (!canvas) return;

		var recovered = data.total_recovered || 0;
		var failed    = (data.total_failed || 0) - recovered;
		if (failed < 0) failed = 0;

		var rate = data.recovery_rate || 0;

		new Chart(canvas, {
			type: 'doughnut',
			data: {
				labels: ['Recovered', 'Unrecovered'],
				datasets: [{
					data:            [recovered, failed],
					backgroundColor: [COLORS.green, COLORS.greyFaded],
					borderColor:     [COLORS.green, COLORS.grey],
					borderWidth:     2,
					hoverOffset:     6,
				}],
			},
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				cutout:              '70%',
				plugins: {
					legend: {
						position: 'bottom',
					},
					tooltip: {
						callbacks: {
							label: function (context) {
								var label = context.label || '';
								var value = context.parsed || 0;
								return ' ' + label + ': ' + value + ' payments';
							},
						},
					},
				},
			},
			plugins: [{
				id: 'centerText',
				afterDraw: function (chart) {
					var ctx    = chart.ctx;
					var width  = chart.width;
					var height = chart.height;

					ctx.save();
					ctx.textAlign    = 'center';
					ctx.textBaseline = 'middle';

					// Rate value.
					ctx.font      = 'bold 28px ' + FONT_FAMILY;
					ctx.fillStyle = '#1d2327';
					ctx.fillText(rate + '%', width / 2, height / 2 - 8);

					// Label.
					ctx.font      = '12px ' + FONT_FAMILY;
					ctx.fillStyle = '#646970';
					ctx.fillText('Recovery Rate', width / 2, height / 2 + 16);

					ctx.restore();
				},
			}],
		});
	}

	/*
	|--------------------------------------------------------------------------
	| 2. Failed vs Recovered Payments (Bar)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render the Failed vs Recovered bar chart by month.
	 *
	 * @param {Object} data Analytics data from the REST API.
	 */
	function renderFailedVsRecoveredChart(data) {
		var canvas = document.getElementById('workfern-chart-failed-vs-recovered');
		if (!canvas) return;

		var monthly = data.monthly || [];

		var labels    = [];
		var failedArr = [];
		var recovArr  = [];

		monthly.forEach(function (row) {
			labels.push(formatMonth(row.month));
			failedArr.push(row.failed_count || 0);
			recovArr.push(row.recovered_count || 0);
		});

		// If no monthly data, show message.
		if (labels.length === 0) {
			canvas.parentElement.innerHTML = '<p style="text-align:center;color:#646970;padding:40px 0;">No monthly data available yet.</p>';
			return;
		}

		new Chart(canvas, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						label:           'Failed',
						data:            failedArr,
						backgroundColor: COLORS.redFaded,
						borderColor:     COLORS.red,
						borderWidth:     2,
						borderRadius:    4,
						barPercentage:   0.6,
					},
					{
						label:           'Recovered',
						data:            recovArr,
						backgroundColor: COLORS.greenFaded,
						borderColor:     COLORS.green,
						borderWidth:     2,
						borderRadius:    4,
						barPercentage:   0.6,
					},
				],
			},
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				interaction: {
					mode:      'index',
					intersect: false,
				},
				scales: {
					x: {
						grid: { display: false },
					},
					y: {
						beginAtZero: true,
						ticks: {
							stepSize: 1,
							precision: 0,
						},
						grid: {
							color: 'rgba(0,0,0,.04)',
						},
					},
				},
				plugins: {
					legend: { position: 'top' },
					tooltip: {
						callbacks: {
							label: function (context) {
								return ' ' + context.dataset.label + ': ' + context.parsed.y + ' payments';
							},
						},
					},
				},
			},
		});
	}

	/*
	|--------------------------------------------------------------------------
	| 3. Recovered Revenue Trend (Line)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Render the Recovered Revenue trend line chart.
	 *
	 * @param {Object} data Analytics data from the REST API.
	 */
	function renderRevenueTrendChart(data) {
		var canvas = document.getElementById('workfern-chart-revenue-trend');
		if (!canvas) return;

		var monthly = data.monthly || [];

		var labels    = [];
		var amounts   = [];
		var cumulative = 0;
		var cumArr    = [];

		monthly.forEach(function (row) {
			labels.push(formatMonth(row.month));
			amounts.push(row.recovered_amount || 0);
			cumulative += (row.recovered_amount || 0);
			cumArr.push(parseFloat(cumulative.toFixed(2)));
		});

		if (labels.length === 0) {
			canvas.parentElement.innerHTML = '<p style="text-align:center;color:#646970;padding:40px 0;">No revenue data available yet.</p>';
			return;
		}

		new Chart(canvas, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label:            'Monthly Revenue',
						data:             amounts,
						borderColor:      COLORS.blue,
						backgroundColor:  COLORS.blueFaded,
						borderWidth:      2,
						pointRadius:      5,
						pointHoverRadius: 7,
						pointBackgroundColor: COLORS.blue,
						tension:          0.3,
						fill:             true,
					},
					{
						label:            'Cumulative Revenue',
						data:             cumArr,
						borderColor:      COLORS.purple,
						backgroundColor:  'transparent',
						borderWidth:      2,
						borderDash:       [6, 3],
						pointRadius:      4,
						pointHoverRadius: 6,
						pointBackgroundColor: COLORS.purple,
						tension:          0.3,
						fill:             false,
					},
				],
			},
			options: {
				responsive:          true,
				maintainAspectRatio: false,
				interaction: {
					mode:      'index',
					intersect: false,
				},
				scales: {
					x: {
						grid: { display: false },
					},
					y: {
						beginAtZero: true,
						ticks: {
							callback: function (value) {
								return '$' + value.toLocaleString();
							},
						},
						grid: {
							color: 'rgba(0,0,0,.04)',
						},
					},
				},
				plugins: {
					legend: { position: 'top' },
					tooltip: {
						callbacks: {
							label: function (context) {
								var val = context.parsed.y || 0;
								return ' ' + context.dataset.label + ': $' + val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
							},
						},
					},
				},
			},
		});
	}

	/*
	|--------------------------------------------------------------------------
	| Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Format a YYYY-MM string into a readable month label.
	 *
	 * @param {string} ym The YYYY-MM string.
	 * @return {string} Formatted label, e.g. "Jan 2026".
	 */
	function formatMonth(ym) {
		if (!ym || ym.length < 7) return ym;

		var parts  = ym.split('-');
		var year   = parts[0];
		var month  = parseInt(parts[1], 10);

		var names = [
			'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
			'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
		];

		return (names[month - 1] || month) + ' ' + year;
	}

})();
/* </fs_premium_only> */
